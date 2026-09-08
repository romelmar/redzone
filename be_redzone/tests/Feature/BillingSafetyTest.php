<?php

namespace Tests\Feature;

use App\Models\{Payment, Plan, ServiceCredit, Subscriber, Subscription, User};
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(): Subscription
    {
        $this->actingAs(User::factory()->create());
        $subscriber = Subscriber::create(['name' => 'Test subscriber', 'email' => 'subscriber@example.com']);
        $plan = Plan::create(['name' => 'Test plan', 'price' => 310]);

        return Subscription::create(['subscriber_id' => $subscriber->id, 'plan_id' => $plan->id, 'start_date' => '2026-01-31']);
    }

    public function test_business_routes_require_authentication(): void
    {
        foreach (['subscribers', 'subscriptions', 'payments', 'plans', 'dues', 'collection-sheet', 'serviceCredits'] as $path) {
            $this->getJson('/api/'.$path)->assertUnauthorized();
            if (!in_array($path, ['dues', 'collection-sheet'])) {
                $this->postJson('/api/'.$path, [])->assertUnauthorized();
                $this->deleteJson('/api/'.$path.'/1')->assertUnauthorized();
            }
        }
    }

    public function test_credit_is_saved_recalculated_and_applied_only_once(): void
    {
        $sub = $this->subscription();
        $credit = $this->postJson('/api/serviceCredits', [
            'subscription_id' => $sub->id, 'credit_month' => '2026-01-15', 'outage_days' => 1,
        ])->assertCreated()->json('id');
        $this->assertDatabaseHas('service_credits', ['id' => $credit, 'amount' => 10]);
        $this->assertSame('2026-01-01', ServiceCredit::findOrFail($credit)->credit_month->toDateString());
        $billing = app(BillingService::class)->computeFor($sub->fresh(), Carbon::parse('2026-02-01'));
        $this->assertEquals(610, $billing['total_due']);
        $this->assertEquals(0, $billing['outage_credit']);
        $this->putJson('/api/serviceCredits/'.$credit, ['outage_days' => 2])->assertOk();
        $this->assertDatabaseHas('service_credits', ['id' => $credit, 'amount' => 20]);
        $this->putJson('/api/serviceCredits/'.$credit, ['credit_month' => '2026-02-10'])->assertOk();
        $this->assertDatabaseHas('service_credits', ['id' => $credit, 'amount' => 22.14]);
        $this->assertSame('2026-02-01', ServiceCredit::findOrFail($credit)->credit_month->toDateString());
    }

    public function test_deletion_preserves_subscriptions_and_payments(): void
    {
        $sub = $this->subscription();
        $payment = Payment::create(['subscription_id' => $sub->id, 'amount' => 100, 'payment_date' => '2026-01-31']);
        $this->deleteJson('/api/plans/'.$sub->plan_id)->assertUnprocessable();
        $this->deleteJson('/api/subscribers/'.$sub->subscriber_id)->assertUnprocessable();
        $this->deleteJson('/api/subscriptions/'.$sub->id)->assertUnprocessable();
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('subscriptions', ['id' => $sub->id]);
    }

    public function test_list_and_soa_balances_match_under_each_sort(): void
    {
        $this->travelTo(Carbon::parse('2026-02-10'));
        $sub = $this->subscription();
        Payment::create(['subscription_id' => $sub->id, 'amount' => 100, 'payment_date' => '2026-01-31']);
        foreach (['subscriber_name', 'plan_name', 'start_date'] as $sort) {
            $this->getJson('/api/subscriptions?sort_by='.$sort)->assertOk()->assertJsonPath('data.0.current_balance', 520);
        }
        $this->getJson('/api/subscriptions/'.$sub->id.'/soa-json?month=2026-02-01')
            ->assertOk()->assertJsonPath('total_due', 520);
    }

    public function test_unpaid_billing_history_cannot_be_deleted(): void
    {
        $this->travelTo(Carbon::parse('2026-02-10'));
        $sub = $this->subscription();
        $this->deleteJson('/api/subscriptions/'.$sub->id)->assertUnprocessable();
        $this->assertDatabaseHas('subscriptions', ['id' => $sub->id]);
    }

    public function test_rate_changes_preserve_previous_and_current_months(): void
    {
        $this->travelTo(Carbon::parse('2026-02-10'));
        $sub = $this->subscription();
        $this->putJson('/api/plans/'.$sub->plan_id, ['name' => 'Updated plan', 'price' => 620])->assertOk();
        $this->putJson('/api/subscriptions/'.$sub->id, ['monthly_discount' => 20])->assertOk();
        $billing = app(BillingService::class);
        $this->assertEquals(310, $billing->computeFor($sub->fresh(), Carbon::parse('2026-01-01'))['total_due']);
        $this->assertEquals(620, $billing->computeFor($sub->fresh(), Carbon::parse('2026-02-01'))['total_due']);
        $this->assertEquals(1220, $billing->computeFor($sub->fresh(), Carbon::parse('2026-03-01'))['total_due']);
    }

    public function test_month_end_dates_are_clamped_and_periods_are_contiguous(): void
    {
        $sub = $this->subscription();
        $this->assertSame('2026-02-28', $sub->dueDateForMonth(Carbon::parse('2026-02-01'))->toDateString());
        $this->assertSame('2026-02-27', $sub->billingPeriodEndForMonth(Carbon::parse('2026-01-01'))->toDateString());
        $this->assertSame('2026-03-30', $sub->billingPeriodEndForMonth(Carbon::parse('2026-02-01'))->toDateString());
        $this->assertSame('2028-02-29', $sub->dueDateForMonth(Carbon::parse('2028-02-01'))->toDateString());
    }

    public function test_retrying_a_payment_does_not_create_a_duplicate(): void
    {
        $sub = $this->subscription();
        $data = ['subscription_id' => $sub->id, 'amount' => 100, 'payment_date' => '2026-02-01',
            'payment_type' => 'payment', 'request_key' => 'e5a3bcee-83d3-4d17-bf7f-b751eddd6b04'];
        $id = $this->postJson('/api/payments', $data)->assertCreated()->json('id');
        $this->postJson('/api/payments', $data)->assertCreated()->assertJsonPath('id', $id);
        $this->postJson('/api/payments', array_replace($data, ['amount' => 200]))->assertConflict();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_repaired_routes_and_invalid_pagination(): void
    {
        $sub = $this->subscription();
        $this->getJson('/api/subscribers-with-dues')->assertOk();
        $this->postJson('/api/subscriptions/'.$sub->id.'/suspend')->assertOk();
        $this->getJson('/api/dues?per_page=0')->assertUnprocessable();
    }
}
