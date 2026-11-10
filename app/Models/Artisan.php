<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Artisan extends Model
{
    protected $fillable = [
        'region_id',
        'name',
        'slug',
        'history',
        'contact',
        'photo_disk',
        'photo_path',
        'photo_alt',
        'translations',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'translations' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (Artisan $artisan) {
            if (blank($artisan->slug) && filled($artisan->name)) {
                $artisan->slug = static::generateUniqueSlug($artisan->name);
            }
        });

        static::updating(function (Artisan $artisan) {
            if ($artisan->isDirty('name')) {
                $originalName = $artisan->getOriginal('name');
                $originalSlug = $artisan->getOriginal('slug');

                if (
                    blank($artisan->slug) ||
                    $artisan->slug === Str::slug($originalName) ||
                    $artisan->slug === $originalSlug
                ) {
                    $artisan->slug = static::generateUniqueSlug($artisan->name, $artisan->id);
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

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'artisan_product')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo_path
                ? Storage::disk($this->photo_disk ?: 'public')->url($this->photo_path)
                : null
        );
    }

    protected static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'artisan';
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
