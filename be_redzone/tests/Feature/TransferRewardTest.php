<?php
namespace Tests\Feature;
use App\Models\{Subscription, Subscriber, Plan, User, ServiceCredit, Addon, Payment};
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TransferRewardTest extends TestCase {
 use RefreshDatabase;
 private function account(): Subscription {
  $this->travelTo(Carbon::parse('2026-09-17'));
  $this->actingAs(User::factory()->create());
  $customer=Subscriber::create(['name'=>'Transfer customer','email'=>'transfer@example.com']);
  $plan=Plan::create(['name'=>'Plan','price'=>1000]);
  return Subscription::create(['subscriber_id'=>$customer->id,'plan_id'=>$plan->id,'start_date'=>'2026-08-01','monthly_discount'=>100]);
 }
 public function test_reward_is_once_only_and_preserves_addons_previous_balance_and_next_month(): void {
  $sub=$this->account();
  Addon::create(['subscription_id'=>$sub->id,'name'=>'Extra','amount'=>200,'credit_month'=>'2026-09-01']);
  ServiceCredit::create(['subscription_id'=>$sub->id,'credit_month'=>'2026-09-01','outage_days'=>1,'amount'=>30]);
  $url='/api/subscriptions/'.$sub->id.'/transfer-reward';
  $this->getJson($url.'?month=2026-09')->assertOk()->assertJsonPath('amount',870);
  $id=$this->postJson($url,['month'=>'2026-09','previous_provider'=>'Other ISP','expected_amount'=>870])->assertCreated()->json('id');
  $billing=app(BillingService::class);
  $this->assertEquals(1100,$billing->computeFor($sub->fresh(),Carbon::parse('2026-09-01'))['total_due']);
  $this->assertEquals(2000,$billing->computeFor($sub->fresh(),Carbon::parse('2026-10-01'))['total_due']);
  $this->postJson($url,['month'=>'2026-08','previous_provider'=>'Other ISP','expected_amount'=>900])->assertUnprocessable();
  $this->putJson('/api/serviceCredits/'.$id,['outage_days'=>3])->assertUnprocessable();
  $this->deleteJson('/api/serviceCredits/'.$id)->assertUnprocessable();
  $this->assertDatabaseCount('service_credits',2);
  $this->getJson('/api/subscriptions/'.$sub->id.'/account-statement?from=2026-09-01&to=2026-09-17')->assertOk()->assertJsonPath('total_credits',900)->assertJsonPath('closing_balance',1100);
 }
 public function test_zero_fee_disconnected_future_and_stale_quotes_are_rejected(): void {
  $sub=$this->account(); $url='/api/subscriptions/'.$sub->id.'/transfer-reward';
  $this->postJson($url,['month'=>'2026-09','previous_provider'=>'Other','expected_amount'=>1000])->assertUnprocessable();
  $this->getJson($url.'?month=2026-07')->assertOk()->assertJsonPath('eligible',false);
  $this->getJson($url.'?month=2026-10')->assertOk()->assertJsonPath('eligible',false);
  $sub->update(['active'=>false,'deactivated_at'=>'2026-08-10']);
  $this->postJson($url,['month'=>'2026-09','previous_provider'=>'Other','expected_amount'=>900])->assertUnprocessable();
  $this->assertDatabaseCount('service_credits',0);
 }
 public function test_payment_history_includes_voids_but_totals_only_received_payments(): void {
  $sub=$this->account();
  Payment::create(['subscription_id'=>$sub->id,'payment_date'=>'2026-09-01','amount'=>500,'payment_type'=>'payment']);
  Payment::create(['subscription_id'=>$sub->id,'payment_date'=>'2026-09-02','amount'=>100,'payment_type'=>'offset']);
  $void=Payment::create(['subscription_id'=>$sub->id,'payment_date'=>'2026-09-03','amount'=>700,'payment_type'=>'payment']); $void->delete();
  $url='/api/subscriptions/'.$sub->id.'/payment-history';
  $this->getJson($url.'?per_page=1')->assertOk()->assertJsonPath('total',3)->assertJsonPath('total_received',500)->assertJsonPath('data.0.status','voided');
  $this->getJson($url.'?sort_dir=asc')->assertOk()->assertJsonPath('data.0.payment_date','2026-09-01');
 }
 public function test_authentication_is_required(): void {
  $this->getJson('/api/subscriptions/1/transfer-reward?month=2026-09')->assertUnauthorized();
  $this->postJson('/api/subscriptions/1/transfer-reward',[])->assertUnauthorized();
  $this->getJson('/api/subscriptions/1/payment-history')->assertUnauthorized();
 }
}
