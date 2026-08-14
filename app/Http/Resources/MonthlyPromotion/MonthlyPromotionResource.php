<?php

namespace App\Http\Resources\MonthlyPromotion;

use App\Support\Currency;
use App\Support\Localization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlyPromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = Localization::currentLocale($request);
        $includeTranslations = $request->is('api/v1/admin/*');
        $price = $this->metadataPrice();

        return [
            'id' => $this->id,
            'title' => Localization::translate($this->translations, 'title', $this->title, $locale),
            'description' => Localization::translate($this->translations, 'description', $this->description, $locale),
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'link_url' => $this->link_url,
            'button_text' => Localization::translate($this->translations, 'button_text', $this->button_text, $locale),
            'translations' => $this->when($includeTranslations, $this->translations ?? []),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'starts_at' => $this->starts_at?->toDateTimeString(),
            'ends_at' => $this->ends_at?->toDateTimeString(),
            'metadata' => $this->metadata,
            'price_money' => $price !== null ? Currency::money($price) : null,
            'metadata_money' => [
                'price' => $price !== null ? Currency::money($price) : null,
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    protected function metadataPrice(): ?float
    {
        foreach (['promotional_price', 'price', 'default_price', 'amount'] as $key) {
            $value = data_get($this->metadata, $key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
