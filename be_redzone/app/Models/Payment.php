<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
  use \Illuminate\Database\Eloquent\SoftDeletes;

  public ?string $auditReason = null;
  private ?array $auditBeforeDelete = null;
  protected $fillable = ['subscription_id','amount','payment_date','payment_type','remarks', 'request_key', 'request_hash', 'collector_name', 'payment_method'];
  protected $hidden = ['request_key', 'request_hash'];
  protected $casts = ['payment_date'=>'date'];

    protected static function booted(): void
    {
        static::created(fn (self $payment) => $payment->recordAudit('created'));
        static::updated(fn (self $payment) => $payment->recordAudit('updated'));
        static::deleting(function (self $payment) {
            $payment->auditBeforeDelete = $payment->getRawOriginal();
        });
        static::deleted(fn (self $payment) => $payment->recordAudit('voided'));
    }

    private function recordAudit(string $action): void
    {
        $before = $action === 'voided' ? $this->auditBeforeDelete : $this->getRawOriginal();
        $after = $this->getAttributes();
        foreach (['request_key', 'request_hash'] as $key) {
            unset($before[$key], $after[$key]);
        }
        if ($action === 'voided') {
            $before['deleted_at'] = null;
        }
        PaymentAudit::create([
            'payment_id' => $this->id, 'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name ?? 'System', 'action' => $action,
            'reason' => $this->auditReason, 'before' => $action === 'created' ? null : $before,
            'after' => $after, 'created_at' => now(),
        ]);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
