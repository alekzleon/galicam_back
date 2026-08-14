<?php

namespace App\Http\Resources\GiftItem;

use App\Support\Localization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = Localization::currentLocale($request);
        $includeTranslations = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id,
            'name' => Localization::translate($this->translations, 'name', $this->name, $locale),
            'code' => $this->code,
            'description' => Localization::translate($this->translations, 'description', $this->description, $locale),
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'estimated_value' => $this->estimated_value !== null ? (float) $this->estimated_value : null,
            'unit_label' => Localization::translate($this->translations, 'unit_label', $this->unit_label, $locale),
            'translations' => $this->when($includeTranslations, $this->translations ?? []),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
