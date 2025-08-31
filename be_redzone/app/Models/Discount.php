<?php

// app/Models/Discount.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = ['subscriber_id', 'amount', 'start_date', 'end_date'];

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }
}
