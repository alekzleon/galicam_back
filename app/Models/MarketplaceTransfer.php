<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTransfer extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'order_id',
        'region_id',
        'stripe_account_id',
        'stripe_transfer_id',
        'stripe_charge_id',
        'transfer_group',
        'status',
        'gross_amount',
        'commission_amount',
        'transfer_amount',
        'currency',
        'provider_payload',
        'failure_message',
        'transferred_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'transfer_amount' => 'decimal:2',
        'provider_payload' => 'array',
        'transferred_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
