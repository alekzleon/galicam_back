<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductRegion extends Pivot
{
    protected $table = 'product_region';

    protected $casts = [
        'is_active' => 'boolean',
        'regional_price' => 'decimal:2',
        'regional_stock' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];
}
