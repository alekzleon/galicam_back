<?php

namespace App\Http\Resources\ContactFaq;

use App\Support\Localization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactFaqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = Localization::currentLocale($request);
        $includeTranslations = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id,
            'question' => Localization::translate($this->translations, 'question', $this->question, $locale),
            'answer' => Localization::translate($this->translations, 'answer', $this->answer, $locale),
            'translations' => $this->when($includeTranslations, $this->translations ?? []),
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
