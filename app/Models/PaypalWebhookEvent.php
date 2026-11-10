<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaypalWebhookEvent extends Model
{
    protected $fillable = [
        'paypal_event_id',
        'type',
        'status',
        'processed_at',
        'payload',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'payload' => 'array',
    ];
}
