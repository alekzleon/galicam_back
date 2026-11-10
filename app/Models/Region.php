<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Region extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_disk',
        'banner_path',
        'banner_alt',
        'translations',
        'sort_order',
        'is_active',
        'metadata',
        'stripe_account_id',
        'stripe_connect_status',
        'stripe_details_submitted',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
        'stripe_capabilities',
        'stripe_requirements',
        'stripe_synced_at',
    ];

    protected $casts = [
        'translations' => 'array',
        'metadata' => 'array',
        'stripe_details_submitted' => 'boolean',
        'stripe_charges_enabled' => 'boolean',
        'stripe_payouts_enabled' => 'boolean',
        'stripe_capabilities' => 'array',
        'stripe_requirements' => 'array',
        'stripe_synced_at' => 'datetime',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'banner_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (Region $region) {
            if (blank($region->slug) && filled($region->name)) {
                $region->slug = static::generateUniqueSlug($region->name);
            }
        });

        static::updating(function (Region $region) {
            if ($region->isDirty('name')) {
                $originalName = $region->getOriginal('name');
                $originalSlug = $region->getOriginal('slug');

                if (
                    blank($region->slug) ||
                    $region->slug === Str::slug($originalName) ||
                    $region->slug === $originalSlug
                ) {
                    $region->slug = static::generateUniqueSlug($region->name, $region->id);
                }
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_region')
            ->using(ProductRegion::class)
            ->withPivot('is_active', 'regional_price', 'regional_stock', 'commission_rate', 'metadata', 'sort_order')
            ->withTimestamps();
    }

    public function profileChangeRequests(): HasMany
    {
        return $this->hasMany(RegionProfileChangeRequest::class);
    }

    protected function bannerUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->banner_path
                ? Storage::disk($this->banner_disk ?: 'public')->url($this->banner_path)
                : null
        );
    }

    protected static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'region';
        $slug = $baseSlug;
        $counter = 1;

        while (
            static::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
