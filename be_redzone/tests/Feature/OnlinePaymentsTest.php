<?php

namespace Tests\Feature;

use App\Models\{OnlinePaymentRequest, Payment, Plan, Subscriber, Subscription, User};
use App\Services\PayMongo\OnlinePayments;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, Http, Mail};
use Tests\TestCase;

class OnlinePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'Asia/Manila'));
        config(['paymongo.enabled' => true, 'paymongo.mode' => 'test', 'paymongo.secret_key' => 'sk_test_example',
            'paymongo.webhook_secret' => 'signing-secret', 'paymongo.public_url' => 'https://redzone.example']);
        Http::preventStrayRequests();
    }

    private function subscription(): Subscription
    {
        $this->actingAs(User::factory()->create());
        $subscriber = Subscriber::create(['name' => 'Private Subscriber', 'email' => 'bill@example.com']);
        $plan = Plan::create(['name' => 'Fiber', 'price' => 1000]);
        return Subscription::create(['subscriber_id' => $subscriber->id, 'plan_id' => $plan->id, 'start_date' => '2026-10-01']);
    }

    private function link(?Subscription $sub = null): OnlinePaymentRequest
    {
        $sub ??= $this->subscription();
        $this->postJson('/api/subscriptions/'.$sub->id.'/payment-link')->assertCreated()->assertJsonStructure(['url', 'qr']);
        return OnlinePaymentRequest::latest('id')->firstOrFail();
    }

    private function gatewaySession(OnlinePaymentRequest $link, bool $paid = false): array
    {
        return ['id' => 'cs_example', 'type' => 'checkout_session', 'attributes' => [
            'livemode' => false, 'reference_number' => $link->reference, 'status' => 'active',
            'checkout_url' => 'https://checkout.paymongo.com/cs_example',
            'payments' => $paid ? [['id' => 'pay_example', 'attributes' => ['amount' => 100000, 'currency' => 'PHP',
                'status' => 'paid', 'paid_at' => now()->timestamp, 'source' => ['type' => 'gcash'], 'livemode' => false,
                'fee' => 2000, 'net_amount' => 98000]]] : [],
        ]];
    }

    private function checkout(OnlinePaymentRequest $link): void
    {
        Http::fake(['api.paymongo.com/v2/checkout_sessions' => Http::response(['data' => $this->gatewaySession($link)])]);
        $token = Crypt::decryptString($link->token_encrypted);
        $this->postJson('/api/public/payments/'.$token.'/checkout', ['expected_amount_centavos' => 100000])->assertOk();
    }

    private function webhook(array $session, string $id = 'evt_example', int $age = 0, bool $valid = true)
    {
        $payload = json_encode(['data' => ['id' => $id, 'attributes' => ['type' => 'checkout_session.payment.paid',
            'livemode' => false, 'data' => $session]]]);
        $timestamp = (string) (now()->timestamp - $age);
        $signature = $valid ? hash_hmac('sha256', $timestamp.'.'.$payload, 'signing-secret') : str_repeat('0', 64);
        return $this->call('POST', '/api/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't='.$timestamp.',te='.$signature.',li='], $payload);
    }

    public function test_link_is_private_and_reuses_existing_request(): void
    {
        $sub = $this->subscription(); $link = $this->link($sub);
        $this->postJson('/api/subscriptions/'.$sub->id.'/payment-link')->assertCreated()->assertJsonPath('id', $link->id);
        $this->assertDatabaseCount('online_payment_requests', 1);
        $this->getJson('/api/public/payments/'.Crypt::decryptString($link->token_encrypted))->assertOk()
            ->assertJsonPath('subscriber', 'P***')->assertDontSee('Private Subscriber')->assertDontSee('bill@example.com');
        $json = $this->getJson('/api/online-payments')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('token_encrypted', $json);
        $this->assertArrayNotHasKey('token_hash', $json);
    }

    public function test_double_click_resumes_checkout_and_redirect_does_not_post_payment(): void
    {
        $link = $this->link(); $this->checkout($link);
        $token = Crypt::decryptString($link->token_encrypted);
        $this->postJson('/api/public/payments/'.$token.'/checkout', ['expected_amount_centavos' => 100000])->assertOk();
        $this->getJson('/api/public/payments/'.$token)->assertJsonPath('status', 'pending')->assertJsonPath('payment_reference', null);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_signed_payment_posts_once_updates_bill_and_protects_gateway_record(): void
    {
        $link = $this->link(); $this->checkout($link);
        $this->webhook($this->gatewaySession($link, true))->assertOk()->assertJsonPath('paid', true);
        $this->webhook($this->gatewaySession($link, true))->assertOk();
        $this->webhook($this->gatewaySession($link, true), 'evt_second')->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $payment = Payment::first();
        $this->assertSame('2026-10-20', $payment->payment_date->toDateString());
        $this->assertDatabaseHas('payment_audits', ['payment_id' => $payment->id, 'action' => 'created']);
        $this->getJson('/api/public/payments/'.Crypt::decryptString($link->token_encrypted))
            ->assertJsonPath('status', 'paid')->assertJsonPath('quote.amount_centavos', 0);
        $this->putJson('/api/payments/'.$payment->id, ['amount' => 1, 'reason' => 'Correction'])->assertStatus(409);
        $this->deleteJson('/api/payments/'.$payment->id, ['reason' => 'Correction'])->assertStatus(409);
        $this->getJson('/api/operations/reconciliation?date=2026-10-20')->assertOk()->assertJsonCount(0, 'rows');
        $this->assertEquals(2000, $link->fresh()->fee_centavos);
        $this->assertEquals(98000, $link->fresh()->net_centavos);
    }

    public function test_invalid_stale_wrong_mode_and_wrong_amount_events_do_not_post(): void
    {
        $link = $this->link(); $this->checkout($link); $session = $this->gatewaySession($link, true);
        $this->webhook($session, valid: false)->assertUnauthorized();
        $this->webhook($session, age: 301)->assertUnauthorized();
        $session['attributes']['livemode'] = true;
        $this->webhook($session)->assertUnprocessable();
        $session['attributes']['livemode'] = false;
        $session['attributes']['payments'][0]['attributes']['amount'] = 99999;
        $this->webhook($session)->assertOk()->assertJsonPath('review', true);
        $this->assertSame('review', $link->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_webhook_can_arrive_before_checkout_creation_response(): void
    {
        $link = $this->link(); $link->update(['status' => 'creating']);
        $this->webhook($this->gatewaySession($link, true))->assertOk()->assertJsonPath('paid', true);
        $this->assertSame('cs_example', $link->fresh()->checkout_id);
    }

    public function test_changed_balance_requires_review_before_new_checkout(): void
    {
        $link = $this->link();
        Payment::create(['subscription_id' => $link->subscription_id, 'amount' => 100, 'payment_type' => 'payment', 'payment_date' => '2026-10-20']);
        $this->postJson('/api/public/payments/'.Crypt::decryptString($link->token_encrypted).'/checkout',
            ['expected_amount_centavos' => 100000])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_cash_payment_during_checkout_retains_full_gateway_credit_and_flags_review(): void
    {
        $link = $this->link(); $this->checkout($link);
        $this->postJson('/api/payments', ['subscription_id' => $link->subscription_id, 'amount' => 1000,
            'payment_date' => '2026-10-20', 'payment_type' => 'payment', 'payment_method' => 'cash'])->assertCreated();
        $this->webhook($this->gatewaySession($link, true))->assertOk()->assertJsonPath('paid', true)->assertJsonPath('review', true);
        $this->assertEquals(2000, Payment::sum('amount'));
        $this->assertSame('paid_review', $link->fresh()->status);
    }

    public function test_timeout_blocks_replacement_until_staff_recovers_confirmed_checkout(): void
    {
        $link = $this->link();
        Http::fake(['api.paymongo.com/v2/checkout_sessions' => Http::response([], 500)]);
        $token = Crypt::decryptString($link->token_encrypted);
        $this->postJson('/api/public/payments/'.$token.'/checkout', ['expected_amount_centavos' => 100000])->assertStatus(502);
        $this->assertSame('review', $link->fresh()->status);
        $this->postJson('/api/public/payments/'.$token.'/checkout', ['expected_amount_centavos' => 100000])->assertStatus(409);
        $this->postJson('/api/online-payments/'.$link->id.'/expire')->assertStatus(409);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_example' => Http::response(['data' => $this->gatewaySession($link, true)])]);
        $this->postJson('/api/online-payments/'.$link->id.'/recover', ['checkout_id' => 'cs_example'])->assertOk()->assertJsonPath('paid', true);
    }

    public function test_scheduled_reconciliation_recovers_missing_webhook(): void
    {
        $link = $this->link(); $this->checkout($link);
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_example' => Http::response(['data' => $this->gatewaySession($link, true)])]);
        $this->artisan('paymongo:reconcile')->assertSuccessful();
        $this->artisan('paymongo:reconcile')->assertSuccessful();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_link_expiry_and_provider_expiry_are_distinct(): void
    {
        $link = $this->link(); $this->checkout($link);
        $link->update(['expires_at' => now()->subDay()]);
        $this->postJson('/api/public/payments/'.Crypt::decryptString($link->token_encrypted).'/checkout',
            ['expected_amount_centavos' => 100000])->assertOk();
        $expired = $this->gatewaySession($link); $expired['attributes']['status'] = 'expired';
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_example/expire' => Http::response(['data' => $expired]),
            'api.paymongo.com/v1/checkout_sessions/cs_example' => Http::response(['data' => $expired])]);
        $this->postJson('/api/online-payments/'.$link->id.'/expire')->assertOk();
        $this->assertSame('expired', $link->fresh()->status);
        $this->postJson('/api/subscriptions/'.$link->subscription_id.'/payment-link')->assertCreated();
        $this->assertDatabaseCount('online_payment_requests', 2);
    }

    public function test_settlement_is_separate_and_audited(): void
    {
        $link = $this->link(); $this->checkout($link);
        $this->webhook($this->gatewaySession($link, true))->assertOk();
        $data = ['settlement_reference' => 'PAYOUT-1', 'settled_at' => '2026-10-20'];
        $this->postJson('/api/online-payments/'.$link->id.'/settlement', $data)->assertOk();
        $this->postJson('/api/online-payments/'.$link->id.'/settlement', $data)->assertStatus(409);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('online_payment_events', ['event_key' => 'settlement-'.$link->id]);
    }

    public function test_disconnected_subscription_is_not_reactivated_and_uses_manila_payment_date(): void
    {
        $sub = $this->subscription();
        $sub->update(['active' => false, 'deactivated_at' => '2026-10-10']);
        $link = $this->link($sub); $this->checkout($link);
        $session = $this->gatewaySession($link, true);
        // UTC October 19 is already October 20 in the Philippine business timezone.
        $session['attributes']['payments'][0]['attributes']['paid_at'] = Carbon::parse('2026-10-19 17:30:00', 'UTC')->timestamp;
        $this->webhook($session)->assertOk()->assertJsonPath('review', true);
        $this->assertFalse($sub->fresh()->active);
        $this->assertSame('2026-10-20', Payment::first()->payment_date->toDateString());
    }

    public function test_disabled_checkout_and_staff_endpoints_require_correct_access(): void
    {
        $link = $this->link(); config(['paymongo.enabled' => false]);
        $this->postJson('/api/public/payments/'.Crypt::decryptString($link->token_encrypted).'/checkout',
            ['expected_amount_centavos' => 100000])->assertStatus(503);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/online-payments')->assertUnauthorized();
        $this->postJson('/api/online-payments/'.$link->id.'/sync')->assertUnauthorized();
        $this->getJson('/api/public/payments/not-a-token')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_statements_include_private_link_and_local_qr_code_when_enabled(): void
    {
        Mail::fake(); $sub = $this->subscription();
        $this->get('/api/subscriptions/'.$sub->id.'/billing-statement?month=2026-10-01')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseCount('online_payment_requests', 1);
        $link = OnlinePaymentRequest::first();
        $this->assertStringStartsWith('data:image/svg+xml;base64,', app(OnlinePayments::class)->qr($link));
        $this->assertStringContainsString('/pay#', app(OnlinePayments::class)->url($link));
    }
}
