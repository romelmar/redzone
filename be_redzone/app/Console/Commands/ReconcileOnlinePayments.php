<?php

namespace App\Console\Commands;

use App\Models\OnlinePaymentRequest;
use App\Services\PayMongo\OnlinePayments;
use Illuminate\Console\Command;

class ReconcileOnlinePayments extends Command
{
    protected $signature = 'paymongo:reconcile';
    protected $description = 'Verify unresolved PayMongo checkouts and post confirmed payments once';

    public function handle(OnlinePayments $payments): int
    {
        if (!$payments->gateway->configured()) { $this->info('PayMongo is disabled or unconfigured.'); return self::SUCCESS; }
        // Unconfirmed creation cannot safely be retried; expose abandoned attempts to staff.
        OnlinePaymentRequest::where('status', 'creating')->where('updated_at', '<', now()->subMinutes(2))
            ->update(['status' => 'review', 'review_reason' => 'Checkout creation was interrupted. Find its reference in PayMongo before replacing it.']);
        $failures = 0;
        OnlinePaymentRequest::where('mode', $payments->gateway->mode())->whereNull('payment_id')
            ->whereNotNull('checkout_id')->whereIn('status', ['pending', 'review'])
            ->chunkById(100, function ($links) use ($payments, &$failures) {
                foreach ($links as $link) {
                    try { $payments->sync($link); }
                    catch (\Throwable $e) { $failures++; $this->warn('Could not verify '.$link->reference.'. Retry or review with staff.'); }
                }
            });
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
