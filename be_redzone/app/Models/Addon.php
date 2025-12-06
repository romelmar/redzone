<?php

// app/Models/Addon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
  protected $fillable = [
    'subscription_id',
    'name',
    'description',
    'amount',
    'bill_month',
];

  protected $casts = ['bill_month' => 'date'];

  public function plans()
  {
    return $this->belongsToMany(Plan::class);
  }

  public function subscription()
  {
    return $this->belongsTo(Subscription::class);
  }
}
