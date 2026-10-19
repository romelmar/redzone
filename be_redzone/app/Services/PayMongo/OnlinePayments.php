<?php

namespace App\Services\PayMongo;

use App\Models\{OnlinePaymentRequest, Payment, Subscription};
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;

class OnlinePayments
{
    public function __construct(public Gateway $gateway, private BillingService $billing) {}

    public function quote(Subscription $sub): array
    {
        $month = now('Asia/Manila')->startOfMonth();
        $sub->load(['subscriber', 'plan', 'rates', 'events', 'addons', 'payments', 'serviceCredits']);
        $calc = $this->billing->computeFor($sub, $month);
        return ['billing_month' => $month->toDateString(), 'plan' => $sub->plan?->name,
            'previous_balance' => $calc['previous_balance'], 'monthly_fee' => $calc['msf'],
            'discount' => $calc['discount'], 'addons' => $calc['addons_total'],
            'credits' => $calc['outage_credit'], 'payments' => $calc['payments_total'],
            'due_date' => $calc['due_date']->toDateString(),
            'amount_centavos' => (int) round($calc['total_due'] * 100)];
    }

    public function url(OnlinePaymentRequest $link): string
    {
        return rtrim((string) config('paymongo.public_url'), '/').'/pay#'.Crypt::decryptString($link->token_encrypted);
    }

    public function qr(OnlinePaymentRequest $link): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        return 'data:image/svg+xml;base64,'.base64_encode((new \BaconQrCode\Writer($renderer))->writeString($this->url($link)));
    }

    public function create(Subscription $subscription, ?int $userId): OnlinePaymentRequest
    {
        abort_unless($this->gateway->configured(), 503, 'Online payments are not configured.');
        return DB::transaction(function () use ($subscription, $userId) {
            $sub = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $existing = OnlinePaymentRequest::where('subscription_id', $sub->id)
                ->whereIn('status', ['ready', 'creating', 'pending', 'review'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->mode === $this->gateway->mode(), 409, 'Resolve the existing checkout before changing payment mode.');
                if ($existing->status !== 'ready' || $existing->expires_at->isFuture()) return $existing;
                $existing->update(['status' => 'expired']);
            }
            $snapshot = $this->quote($sub);
            abort_if($snapshot['amount_centavos'] <= 0, 422, 'This subscription has no outstanding balance.');
            $token = bin2hex(random_bytes(32));
            return OnlinePaymentRequest::create([
                'subscription_id' => $sub->id, 'created_by' => $userId,
                'reference' => 'RZ-'.Str::uuid(), 'token_hash' => hash('sha256', $token),
                'token_encrypted' => Crypt::encryptString($token), 'mode' => $this->gateway->mode(),
                'billing_month' => $snapshot['billing_month'], 'amount_centavos' => $snapshot['amount_centavos'],
                'snapshot' => $snapshot, 'expires_at' => now()->addDays(7),
            ]);
        });
    }

    public function fromToken(string $token): OnlinePaymentRequest
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        return OnlinePaymentRequest::where('token_hash', hash('sha256', $token))->firstOrFail();
    }

    public function checkout(OnlinePaymentRequest $link, int $expected): string
    {
        abort_unless($this->gateway->configured() && $link->mode === $this->gateway->mode(), 503, 'Online payments are unavailable.');
        $link = DB::transaction(function () use ($link, $expected) {
            $sub = Subscription::whereKey($link->subscription_id)->lockForUpdate()->firstOrFail();
            $locked = OnlinePaymentRequest::whereKey($link->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'pending' && $locked->checkout_url) return $locked;
            abort_unless($locked->status === 'ready', 409, 'This payment is already processing or requires staff review.');
            abort_if($locked->expires_at->isPast(), 410, 'This payment link has expired. Request a new link.');
            $snapshot = $this->quote($sub);
            abort_if($snapshot['amount_centavos'] <= 0, 409, 'There is no outstanding balance.');
            abort_unless($snapshot['amount_centavos'] === $expected, 409, 'Your bill changed. Review the updated balance before paying.');
            $locked->update(['status' => 'creating', 'amount_centavos' => $expected,
                'billing_month' => $snapshot['billing_month'], 'snapshot' => $snapshot]);
            return $locked;
        });
        if ($link->status === 'pending') return $link->checkout_url;
        try {
            $return = $this->url($link);
            $session = $this->gateway->request('POST', '/v2/checkout_sessions', ['data' => ['attributes' => [
                'line_items' => [['name' => 'Redzone subscription bill', 'amount' => $link->amount_centavos, 'currency' => 'PHP', 'quantity' => 1]],
                'payment_method_types' => ['gcash'], 'reference_number' => $link->reference,
                'success_url' => $return, 'cancel_url' => $return, 'send_email_receipt' => false, 'pass_on_fees' => false,
            ]]], $link->reference);
            $url = data_get($session, 'attributes.checkout_url');
            abort_unless(is_string(data_get($session, 'id')) && preg_match('/^cs_[a-zA-Z0-9]+$/D', $session['id'])
                && is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https'
                && parse_url($url, PHP_URL_HOST) === 'checkout.paymongo.com'
                && data_get($session, 'attributes.livemode') === ($link->mode === 'live'), 502);
            OnlinePaymentRequest::whereKey($link->id)->where('status', 'creating')->update([
                'checkout_id' => $session['id'], 'checkout_url' => $url, 'status' => 'pending', 'updated_at' => now(),
            ]);
            return $url;
        } catch (\Throwable $e) {
            OnlinePaymentRequest::whereKey($link->id)->where('status', 'creating')->update([
                'status' => 'review', 'review_reason' => 'Checkout creation was not confirmed. Find the reference in PayMongo before replacing this request.', 'updated_at' => now(),
            ]);
            abort(502, 'Checkout creation could not be confirmed. Contact Redzone before trying another payment.');
        }
    }

    public function sync(OnlinePaymentRequest $link): array
    {
        abort_unless($link->mode === $this->gateway->mode(), 409, 'Payment mode mismatch.');
        if ($link->payment_id) return ['paid' => true];
        abort_unless($link->checkout_id, 409, 'No confirmed checkout. Staff must find it in PayMongo first.');
        $session = $this->gateway->retrieve($link->checkout_id);
        abort_unless(data_get($session, 'id') === $link->checkout_id, 502, 'Unexpected checkout returned.');
        return $this->post($session, 'sync-'.hash('sha256', json_encode($session)));
    }

    public function post(array $session, string $eventKey): array
    {
        $reference = data_get($session, 'attributes.reference_number');
        abort_unless(is_string($reference) && is_string(data_get($session, 'id')), 422);
        $candidate = OnlinePaymentRequest::where('reference', $reference)->firstOrFail();
        return DB::transaction(function () use ($candidate, $session, $eventKey) {
            $sub = Subscription::whereKey($candidate->subscription_id)->lockForUpdate()->firstOrFail();
            $link = OnlinePaymentRequest::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless(data_get($session, 'attributes.livemode') === ($link->mode === 'live')
                && $link->mode === $this->gateway->mode(), 422, 'Unexpected payment mode.');
            abort_if($link->checkout_id && $link->checkout_id !== $session['id'], 422, 'Unexpected checkout.');
            // A signed webhook can arrive before the create-session HTTP response.
            abort_unless($link->checkout_id || in_array($link->status, ['creating', 'review'], true), 422, 'No checkout was requested.');
            if ($link->payment_id) return ['paid' => true, 'duplicate' => true];
            $paid = collect(data_get($session, 'attributes.payments', []))
                ->filter(fn ($p) => data_get($p, 'attributes.status') === 'paid')->values();
            if ($paid->isEmpty()) {
                if (data_get($session, 'attributes.status') === 'expired') $link->update(['status' => 'expired']);
                return ['pending' => true];
            }
            if (DB::table('online_payment_events')->where('event_key', $eventKey)->exists()) return ['duplicate' => true];
            $p = $paid->first(); $a = data_get($p, 'attributes', []);
            $paidAt = $a['paid_at'] ?? null;
            $valid = $paid->count() === 1 && is_string(data_get($p, 'id')) && strlen($p['id']) <= 100
                && ($a['currency'] ?? null) === 'PHP' && ($a['amount'] ?? null) === $link->amount_centavos
                && data_get($a, 'source.type') === 'gcash'
                && (!array_key_exists('livemode', $a) || $a['livemode'] === ($link->mode === 'live'))
                && is_int($paidAt) && $paidAt > 0 && $paidAt <= now()->timestamp + 300
                && !Payment::withTrashed()->where('gateway_request_id', $link->id)->exists();
            if (!$valid) {
                $link->update(['checkout_id' => $session['id'], 'status' => 'review',
                    'review_reason' => 'Paid checkout failed amount, currency, method, timestamp, mode, or duplicate checks. No payment was posted.']);
                $outcome = 'review';
            } else {
                $balance = $this->quote($sub)['amount_centavos'];
                $review = $balance < $link->amount_centavos || !$sub->active;
                $payment = new Payment([
                    'subscription_id' => $sub->id, 'amount' => number_format($link->amount_centavos / 100, 2, '.', ''),
                    'payment_date' => Carbon::createFromTimestamp($paidAt, 'Asia/Manila')->toDateString(),
                    'payment_type' => 'payment', 'payment_method' => 'gcash', 'collector_name' => 'PayMongo',
                    'remarks' => ($link->mode === 'test' ? '[TEST] ' : '').'Verified PayMongo payment / '.$link->reference,
                ]);
                $payment->gateway_request_id = $link->id;
                $payment->auditReason = 'Verified PayMongo payment '.$p['id'];
                $payment->save();
                $link->update(['checkout_id' => $session['id'], 'provider_payment_id' => $p['id'], 'payment_id' => $payment->id,
                    'fee_centavos' => is_int($a['fee'] ?? null) ? $a['fee'] : null,
                    'net_centavos' => is_int($a['net_amount'] ?? null) ? $a['net_amount'] : null,
                    'status' => $review ? 'paid_review' : 'paid',
                    'review_reason' => $review ? 'Payment recorded. Review excess account credit and/or reconnection eligibility; service was not activated.' : null]);
                $outcome = 'posted';
            }
            DB::table('online_payment_events')->insert(['event_key' => $eventKey,
                'online_payment_request_id' => $link->id, 'outcome' => $outcome, 'created_at' => now(), 'updated_at' => now()]);
            return ['paid' => (bool) $link->payment_id, 'review' => $link->review_reason !== null];
        });
    }

    public function recover(OnlinePaymentRequest $link, string $checkoutId): array
    {
        abort_unless($link->mode === $this->gateway->mode(), 409);
        $session = $this->gateway->retrieve($checkoutId);
        abort_unless(data_get($session, 'id') === $checkoutId
            && data_get($session, 'attributes.reference_number') === $link->reference
            && data_get($session, 'attributes.livemode') === ($link->mode === 'live'), 422, 'Checkout does not match this request.');
        DB::transaction(function () use ($link, $checkoutId, $session) {
            Subscription::whereKey($link->subscription_id)->lockForUpdate()->firstOrFail();
            $locked = OnlinePaymentRequest::whereKey($link->id)->lockForUpdate()->firstOrFail();
            abort_unless(!$locked->payment_id && (!$locked->checkout_id || $locked->checkout_id === $checkoutId)
                && in_array($locked->status, ['review', 'creating', 'pending'], true), 409);
            $url = data_get($session, 'attributes.checkout_url');
            abort_unless(is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https'
                && parse_url($url, PHP_URL_HOST) === 'checkout.paymongo.com', 422);
            $locked->update(['checkout_id' => $checkoutId, 'checkout_url' => $url, 'status' => 'pending', 'review_reason' => null]);
        });
        return $this->sync($link->fresh());
    }

    public function expire(OnlinePaymentRequest $link): void
    {
        abort_unless($link->mode === $this->gateway->mode(), 409);
        if ($link->checkout_id) {
            $this->sync($link);
            abort_if($link->fresh()->payment_id, 409, 'Payment already received.');
            if ($link->fresh()->status === 'expired') return;
            $this->gateway->request('POST', '/v1/checkout_sessions/'.rawurlencode($link->checkout_id).'/expire', [], 'expire-'.$link->reference);
            $session = $this->gateway->retrieve($link->checkout_id);
            abort_unless(data_get($session, 'id') === $link->checkout_id
                && data_get($session, 'attributes.livemode') === ($link->mode === 'live')
                && data_get($session, 'attributes.status') === 'expired', 409, 'Provider expiration was not confirmed.');
        }
        DB::transaction(function () use ($link) {
            Subscription::whereKey($link->subscription_id)->lockForUpdate()->firstOrFail();
            $locked = OnlinePaymentRequest::whereKey($link->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->payment_id, 409, 'Payment already received.');
            abort_unless($locked->checkout_id || $locked->status === 'ready', 409, 'Find the unconfirmed checkout in PayMongo before replacing it.');
            $locked->update(['status' => 'expired', 'review_reason' => null]);
        });
    }
}
