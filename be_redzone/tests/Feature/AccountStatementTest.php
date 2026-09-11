<?php
namespace Tests\Feature;
use App\Models\{Payment, Plan, ServiceCredit, Subscriber, Subscription, User};
use App\Mail\AccountStatementMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class AccountStatementTest extends TestCase
{
 use RefreshDatabase;
 private function account(): Subscription {
  $this->actingAs(User::factory()->create());
  $customer = Subscriber::create(['name'=>'Statement customer','email'=>'statements@example.com']);
  $plan = Plan::create(['name'=>'Plan 310','price'=>310]);
  $sub = Subscription::create(['subscriber_id'=>$customer->id,'plan_id'=>$plan->id,'start_date'=>'2026-01-01','active'=>false,'deactivated_at'=>'2026-02-10']);
  Payment::create(['subscription_id'=>$sub->id,'amount'=>100,'payment_date'=>'2026-01-15']);
  Payment::create(['subscription_id'=>$sub->id,'amount'=>600,'payment_date'=>'2026-02-20']);
  $void = Payment::create(['subscription_id'=>$sub->id,'amount'=>999,'payment_date'=>'2026-02-21']); $void->delete();
  ServiceCredit::create(['subscription_id'=>$sub->id,'credit_month'=>'2026-01-01','outage_days'=>1,'amount'=>10]);
  return $sub;
 }
 public function test_balances_dates_disconnection_and_voids(): void {
  $sub=$this->account(); $url='/api/subscriptions/'.$sub->id.'/account-statement';
  $this->getJson($url.'?from=2026-02-10&to=2026-03-31')->assertOk()->assertJsonPath('opening_balance',510)->assertJsonPath('total_charges',0)->assertJsonPath('total_credits',600)->assertJsonPath('closing_balance',-90)->assertJsonCount(1,'entries')->assertJsonPath('entries.0.date','2026-02-20');
  $this->getJson($url.'?from=2026-01-01&to=2026-03-31')->assertOk()->assertJsonPath('opening_balance',0)->assertJsonPath('total_charges',620)->assertJsonPath('total_credits',710)->assertJsonPath('closing_balance',-90)->assertJsonCount(5,'entries');
 }
 public function test_pdf_and_email_share_the_statement(): void {
  Mail::fake(); $sub=$this->account(); $url='/api/subscriptions/'.$sub->id.'/account-statement';
  $this->get($url.'/pdf?from=2026-02-10&to=2026-03-31')->assertOk()->assertHeader('content-type','application/pdf');
  $this->postJson($url.'/email',['from'=>'2026-02-10','to'=>'2026-03-31'])->assertOk();
  Mail::assertSent(AccountStatementMail::class, function($mail) {
   $this->assertStringContainsString('Statement of account',$mail->build()->subject);
   return $mail->hasTo('statements@example.com') && $mail->statement['closing_balance']==-90 && str_starts_with($mail->pdf,'%PDF');
  });
 }
 public function test_validation_and_missing_email(): void {
  Mail::fake(); $sub=$this->account(); $url='/api/subscriptions/'.$sub->id.'/account-statement';
  $this->getJson($url.'?from=2026-03-31&to=2026-01-01')->assertUnprocessable();
  $this->getJson($url.'?from=bad&to=2026-01-01')->assertUnprocessable();
  $sub->subscriber->update(['email'=>'']);
  $this->postJson($url.'/email',['from'=>'2026-01-01','to'=>'2026-03-31'])->assertUnprocessable(); Mail::assertNothingSent();
 }
 public function test_workspace_and_billing_statement_email(): void {
  $sub=$this->account();
  $this->getJson('/api/billing-statements?month=2026-03-01')->assertOk()->assertJsonPath('total',1);
  $pdf=$this->get('/api/subscriptions/'.$sub->id.'/billing-statement?month=2026-02-01')->assertOk();
  $this->assertStringContainsString('Billing-statement-',$pdf->headers->get('content-disposition'));
  Mail::shouldReceive('send')->once()->withArgs(function($view,$data,$callback) {
   $this->assertSame('emails.billing-statement',$view);
   $this->assertStringContainsString('billing statement',view($view,$data)->render()); return true;
  });
  $this->postJson('/api/subscriptions/'.$sub->id.'/billing-statement/email',['month'=>'2026-02-01'])->assertOk();
 }
 public function test_routes_require_authentication(): void {
  $this->getJson('/api/billing-statements')->assertUnauthorized();
  $this->getJson('/api/subscriptions/1/account-statement')->assertUnauthorized();
  $this->getJson('/api/subscriptions/1/account-statement/pdf')->assertUnauthorized();
  $this->postJson('/api/subscriptions/1/account-statement/email')->assertUnauthorized();
 }
}
