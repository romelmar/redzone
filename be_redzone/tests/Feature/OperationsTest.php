<?php

namespace Tests\Feature;

use App\Models\{Payment, PaymentAudit, Plan, Subscriber, Subscription, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private function setupAccount(): Subscription
    {
        $this->travelTo(Carbon::parse('2026-09-08 04:00:00'));
        $this->actingAs(User::factory()->create(['name' => 'Cashier']));
        $subscriber = Subscriber::create(['name' => 'Subscriber', 'email' => 'test@example.com']);
        $plan = Plan::create(['name' => 'Plan', 'price' => 500]);
        return Subscription::create(['subscriber_id' => $subscriber->id, 'plan_id' => $plan->id, 'start_date' => '2026-09-01']);
    }

    private function payment(Subscription $sub, array $extra = []): array
    {
        return array_replace(['subscription_id' => $sub->id, 'amount' => 100, 'payment_date' => '2026-09-08',
            'payment_type' => 'payment', 'collector_name' => 'Alex', 'payment_method' => 'cash',
            'request_key' => (string) Str::uuid()], $extra);
    }

    public function test_payment_audit_records_actor_old_values_and_void_without_erasing_record(): void
    {
        $sub = $this->setupAccount();
        $payload = $this->payment($sub);
        $id = $this->postJson('/api/payments', $payload)->assertCreated()->json('id');
        $this->postJson('/api/payments', $payload)->assertCreated();
        $this->assertDatabaseCount('payment_audits', 1);
        $this->putJson('/api/payments/'.$id, ['amount' => 200])->assertUnprocessable();
        $this->putJson('/api/payments/'.$id, ['amount' => 200, 'reason' => 'Correct receipt amount'])->assertOk();
        $audit = PaymentAudit::latest('id')->first();
        $this->assertSame('Cashier', $audit->actor_name);
        $this->assertEquals(100, $audit->before['amount']);
        $this->assertEquals(200, $audit->after['amount']);
        $this->assertArrayNotHasKey('request_hash', $audit->after);
        $this->deleteJson('/api/payments/'.$id, [])->assertUnprocessable();
        $this->deleteJson('/api/payments/'.$id, ['reason' => 'Duplicate collection entry'])->assertNoContent();
        $this->assertSoftDeleted('payments', ['id' => $id]);
        $this->assertSame('voided', PaymentAudit::latest('id')->first()->action);
        $this->assertEquals(200, PaymentAudit::latest('id')->first()->before['amount']);
        $this->assertDatabaseCount('payment_audits', 3);
        $this->postJson('/api/payments', $payload)->assertConflict();
        $this->getJson('/api/subscriptions/'.$sub->id.'/soa-json?month=2026-09-01')->assertJsonPath('total_due', 500);
        $this->getJson('/api/operations/payment-audits?payment_id='.$id)->assertOk()->assertJsonPath('total', 3);
    }

    public function test_reconciliation_excludes_offsets_voids_and_preserves_remittance_history(): void
    {
        $sub = $this->setupAccount();
        $this->postJson('/api/payments', $this->payment($sub, ['amount' => 300]))->assertCreated();
        $this->postJson('/api/payments', $this->payment($sub, ['amount' => 50, 'payment_type' => 'offset']))->assertCreated();
        $void = $this->postJson('/api/payments', $this->payment($sub, ['amount' => 75]))->json('id');
        $this->deleteJson('/api/payments/'.$void, ['reason' => 'Duplicate payment'])->assertNoContent();
        $remittance = ['collection_date' => '2026-09-08', 'collector_name' => 'Alex', 'payment_method' => 'cash',
            'amount' => 200, 'reference' => 'CASH-001', 'request_key' => (string) Str::uuid()];
        $id = $this->postJson('/api/operations/remittances', $remittance)->assertCreated()->json('id');
        $this->postJson('/api/operations/remittances', $remittance)->assertCreated()->assertJsonPath('id', $id);
        $this->postJson('/api/operations/remittances', array_replace($remittance, ['amount' => 250]))->assertConflict();
        $this->getJson('/api/operations/reconciliation?date=2026-09-08')->assertOk()
            ->assertJsonPath('rows.0.collected', 300)->assertJsonPath('rows.0.remitted', 200)->assertJsonPath('rows.0.unremitted', 100);
        $this->postJson('/api/operations/remittances/'.$id.'/void', ['reason' => 'Wrong cash handover'])->assertNoContent();
        $this->getJson('/api/operations/reconciliation?date=2026-09-08')->assertOk()->assertJsonPath('rows.0.remitted', 0);
        $this->assertDatabaseHas('collector_remittances', ['id' => $id, 'voided_by_name' => 'Cashier', 'void_reason' => 'Wrong cash handover']);
        $this->assertDatabaseCount('collector_remittances', 1);
    }

    public function test_dashboard_uses_real_collections_overdues_and_future_due_dates(): void
    {
        $sub = $this->setupAccount();
        $this->postJson('/api/payments', $this->payment($sub, ['amount' => 100]))->assertCreated();
        $this->postJson('/api/payments', $this->payment($sub, ['amount' => 25, 'payment_type' => 'offset']))->assertCreated();
        $this->postJson('/api/payments', $this->payment($sub, ['amount' => 50, 'payment_date' => '2026-09-09']))->assertCreated();
        Subscription::create(['subscriber_id' => $sub->subscriber_id, 'plan_id' => $sub->plan_id, 'start_date' => '2026-09-10']);
        $this->getJson('/api/operations/dashboard')->assertOk()->assertJsonPath('collections_today', 100)
            ->assertJsonPath('overdue_total', 375)->assertJsonPath('overdue_count', 1)
            ->assertJsonPath('upcoming_count', 1)->assertJsonPath('outstanding_balance', 375);
    }

    public function test_operations_endpoints_require_authentication(): void
    {
        foreach (['dashboard', 'reconciliation', 'payment-audits'] as $path) {
            $this->getJson('/api/operations/'.$path)->assertUnauthorized();
        }
        $this->postJson('/api/operations/remittances', [])->assertUnauthorized();
        $this->postJson('/api/operations/remittances/1/void', [])->assertUnauthorized();
    }

    public function test_reconciliation_groups_collector_capitalization_and_separates_payment_methods(): void
    {
        $sub = $this->setupAccount();
        $this->postJson('/api/payments', $this->payment($sub, ['collector_name' => 'Alex', 'amount' => 100]))->assertCreated();
        $this->postJson('/api/payments', $this->payment($sub, ['collector_name' => 'alex', 'amount' => 50]))->assertCreated();
        $this->postJson('/api/payments', $this->payment($sub, ['payment_method' => 'gcash', 'amount' => 70]))->assertCreated();
        $this->postJson('/api/operations/remittances', [
            'collection_date' => '2026-09-08', 'collector_name' => 'ALEX', 'payment_method' => 'cash',
            'amount' => 150, 'reference' => 'CASH-002', 'request_key' => (string) Str::uuid(),
        ])->assertCreated();
        $rows = collect($this->getJson('/api/operations/reconciliation?date=2026-09-08')->assertOk()->json('rows'));
        $this->assertCount(2, $rows);
        $this->assertEquals(0, $rows->firstWhere('payment_method', 'cash')['unremitted']);
        $this->assertEquals(70, $rows->firstWhere('payment_method', 'gcash')['unremitted']);
    }

    public function test_payment_is_rolled_back_if_its_audit_cannot_be_written(): void
    {
        $sub = $this->setupAccount();
        PaymentAudit::creating(function () { throw new \RuntimeException('Audit storage unavailable'); });
        try {
            $this->postJson('/api/payments', $this->payment($sub))->assertStatus(500);
            $this->assertDatabaseCount('payments', 0);
        } finally {
            PaymentAudit::flushEventListeners();
        }
    }
}
