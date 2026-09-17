<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Subscription, ServiceCredit};
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferRewardController extends Controller
{
 private function quote(Subscription $subscription, string $month): array {
  $date = Carbon::parse($month)->startOfMonth();
  $existing = ServiceCredit::where('transfer_reward_key', 'transfer:'.$subscription->id)->first();
  $reason = null;
  if ($existing) $reason = 'This subscription has already received its transfer reward.';
  elseif ($date->lt($subscription->start_date->copy()->startOfMonth())) $reason = 'Select the subscription start month or a later month.';
  elseif ($date->gt(now()->startOfMonth())) $reason = 'Rewards can be applied once the selected billing month has started.';
  $amount = 0;
  if (!$reason) {
   $bill = app(BillingService::class)->computeFor($subscription, $date);
   $amount = round(max(0, $bill['msf'] - $bill['discount'] - $bill['outage_credit']), 2);
   if ($amount <= 0) $reason = 'There is no remaining recurring fee to waive for this month.';
  }
  return ['month' => $date->toDateString(), 'amount' => $amount, 'eligible' => !$reason, 'reason' => $reason, 'reward' => $existing];
 }
 public function preview(Request $request, Subscription $subscription) {
  $data = $request->validate(['month'=>'required|date_format:Y-m']);
  return response()->json($this->quote($subscription, $data['month'].'-01'));
 }
 public function store(Request $request, Subscription $subscription) {
  $data = $request->validate(['month'=>'required|date_format:Y-m', 'previous_provider'=>'required|string|max:150', 'expected_amount'=>'required|numeric|min:0.01']);
  if (trim($data['previous_provider']) === '') throw ValidationException::withMessages(['previous_provider'=>'Enter the previous provider.']);
  $reward = DB::transaction(function () use ($subscription, $data, $request) {
   $sub = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
   $quote = $this->quote($sub, $data['month'].'-01');
   if (!$quote['eligible']) throw ValidationException::withMessages(['month'=>$quote['reason']]);
   if ((int) round($quote['amount'] * 100) !== (int) round($data['expected_amount'] * 100)) throw ValidationException::withMessages(['expected_amount'=>'The credit changed. Review the reward again before applying it.']);
   return ServiceCredit::create([
    'subscription_id'=>$sub->id, 'credit_month'=>$quote['month'], 'outage_days'=>0,
    'amount'=>$quote['amount'], 'transfer_reward_key'=>'transfer:'.$sub->id,
    'previous_provider'=>trim($data['previous_provider']), 'granted_by_name'=>$request->user()->name,
    'reason'=>'Competitor transfer reward - one free month; previous provider: '.trim($data['previous_provider']),
   ]);
  });
  return response()->json($reward, 201);
 }
}
