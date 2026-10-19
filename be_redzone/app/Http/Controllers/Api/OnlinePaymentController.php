<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{OnlinePaymentRequest, Subscription};
use App\Services\PayMongo\OnlinePayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnlinePaymentController extends Controller
{
    public function __construct(private OnlinePayments $payments) {}

    public function create(Request $request, Subscription $subscription)
    {
        $link = $this->payments->create($subscription, $request->user()->id);
        return response()->json(['id' => $link->id, 'url' => $this->payments->url($link),
            'expires_at' => $link->expires_at, 'mode' => $link->mode, 'qr' => $this->payments->qr($link)], 201)->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:ready,creating,pending,review,paid,paid_review,expired']);
        return OnlinePaymentRequest::with(['subscription.subscriber:id,name', 'subscription.plan:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('id')->paginate(25);
    }

    public function link(OnlinePaymentRequest $onlinePayment)
    {
        return response()->json(['url' => $this->payments->url($onlinePayment), 'mode' => $onlinePayment->mode, 'qr' => $this->payments->qr($onlinePayment)])
            ->header('Cache-Control', 'no-store');
    }

    public function status(string $token)
    {
        $link = $this->payments->fromToken($token);
        $sub = $link->subscription;
        $quote = $this->payments->quote($sub);
        $status = $link->status === 'ready' && $link->expires_at->isPast() ? 'expired' : $link->status;
        return response()->json(['reference' => $link->reference,
            'subscriber' => mb_substr($sub->subscriber?->name ?? '', 0, 1).'***',
            'status' => $status, 'mode' => $link->mode, 'quote' => $quote,
            'checkout_amount_centavos' => $link->amount_centavos,
            'checkout_month' => $link->billing_month->format('Y-m'),
            'can_pay' => $this->payments->gateway->configured() && $link->mode === $this->payments->gateway->mode()
                && (($status === 'ready' && $quote['amount_centavos'] > 0) || $status === 'pending'),
            'payment_reference' => $link->payment_id ? 'PAY-'.$link->payment_id : null,
            'paid_amount_centavos' => $link->payment_id ? $link->amount_centavos : null,
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function checkout(Request $request, string $token)
    {
        $data = $request->validate(['expected_amount_centavos' => 'required|integer|min:1']);
        return ['checkout_url' => $this->payments->checkout($this->payments->fromToken($token), $data['expected_amount_centavos'])];
    }

    public function sync(OnlinePaymentRequest $onlinePayment) { return $this->payments->sync($onlinePayment); }
    public function syncPublic(string $token) { return $this->payments->sync($this->payments->fromToken($token)); }
    public function expire(OnlinePaymentRequest $onlinePayment)
    {
        $this->payments->expire($onlinePayment);
        return ['expired' => true];
    }

    public function recover(Request $request, OnlinePaymentRequest $onlinePayment)
    {
        $data = $request->validate(['checkout_id' => 'required|string|regex:/^cs_[a-zA-Z0-9]+$/D|max:100']);
        return $this->payments->recover($onlinePayment, $data['checkout_id']);
    }

    public function settlement(Request $request, OnlinePaymentRequest $onlinePayment)
    {
        $data = $request->validate(['settlement_reference' => 'required|string|max:255',
            'settled_at' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Manila')->toDateString()]);
        DB::transaction(function () use ($onlinePayment, $data, $request) {
            $link = OnlinePaymentRequest::whereKey($onlinePayment->id)->lockForUpdate()->firstOrFail();
            abort_unless($link->payment_id, 409, 'Only paid transactions can be settled.');
            abort_if($link->settled_at, 409, 'Settlement is already recorded.');
            $link->update($data);
            DB::table('online_payment_events')->insert(['event_key' => 'settlement-'.$link->id,
                'online_payment_request_id' => $link->id, 'outcome' => 'settled_by_user_'.$request->user()->id,
                'created_at' => now(), 'updated_at' => now()]);
        });
        return ['settled' => true];
    }

    public function webhook(Request $request)
    {
        $gateway = $this->payments->gateway;
        abort_unless($gateway->configured(), 503);
        $parts = [];
        foreach (explode(',', (string) $request->header('Paymongo-Signature')) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) $parts[$pair[0]] = $pair[1];
        }
        $timestamp = $parts['t'] ?? '';
        $signature = $parts[$gateway->mode() === 'live' ? 'li' : 'te'] ?? '';
        abort_unless(ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300
            && hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), config('paymongo.webhook_secret')), $signature), 401, 'Invalid webhook signature.');
        $body = $request->json()->all();
        $event = data_get($body, 'data.attributes.type') ? data_get($body, 'data.attributes') : data_get($body, 'data', []);
        if (data_get($event, 'type') !== 'checkout_session.payment.paid') return ['received' => true];
        abort_unless(data_get($event, 'livemode') === ($gateway->mode() === 'live'), 422, 'Unexpected event mode.');
        $key = data_get($body, 'data.id') ?: hash('sha256', $request->getContent());
        abort_unless(is_string($key) && strlen($key) <= 128, 422);
        return $this->payments->post((array) data_get($event, 'data', []), $key);
    }
}
