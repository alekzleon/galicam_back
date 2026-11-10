<?php

namespace App\Http\Resources\Artisan;

use App\Support\Localization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtisanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = Localization::currentLocale($request);
        $includeTranslations = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id,
            'region_id' => $this->region_id,
            'region' => $this->whenLoaded('region', fn () => [
                'id' => $this->region->id,
                'name' => Localization::translate($this->region->translations, 'name', $this->region->name, $locale),
                'slug' => $this->region->slug,
                'is_active' => (bool) $this->region->is_active,
            ]),
            'name' => Localization::translate($this->translations, 'name', $this->name, $locale),
            'slug' => $this->slug,
            'history' => Localization::translate($this->translations, 'history', $this->history, $locale),
            'contact' => Localization::translate($this->translations, 'contact', $this->contact, $locale),
            'photo_disk' => $this->when($request->is('api/v1/admin/*'), $this->photo_disk),
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_url,
            'photo_alt' => Localization::translate($this->translations, 'photo_alt', $this->photo_alt, $locale),
            'translations' => $this->when($includeTranslations, $this->translations ?? []),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'products_count' => (int) ($this->products_count ?? 0),
            'product_ids' => $this->whenLoaded('products', fn () => $this->products->pluck('id')->values()),
            'products' => $this->whenLoaded('products', fn () => $this->products
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => Localization::translate($product->translations, 'name', $product->name, $locale),
                    'slug' => $product->slug,
                    'sku' => $product->sku,
                    'brand' => $product->brand,
                    'short_description' => Localization::translate($product->translations, 'short_description', $product->short_description, $locale),
                    'image_path' => $product->image_path,
                    'image_url' => $product->image_url,
                    'default_price' => $product->default_price !== null ? (float) $product->default_price : null,
                    'stock' => $product->stock !== null ? (float) $product->stock : null,
                    'sort_order' => (int) ($product->pivot?->sort_order ?? 0),
                ])
                ->values()),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
