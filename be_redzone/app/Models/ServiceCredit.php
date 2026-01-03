<?php
// app/Models/ServiceCredit.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCredit extends Model
{
  protected $fillable = ['subscription_id', 'credit_month', 'outage_days', 'reason'];
  protected $casts = ['credit_month' => 'date'];

  public function subscription()
  {
    return $this->belongsTo(Subscription::class);
  }
}
