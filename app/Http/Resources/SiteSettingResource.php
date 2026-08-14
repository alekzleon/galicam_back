<?php

namespace App\Http\Resources;

use App\Models\EcommerceSetting;
use App\Support\Localization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = Localization::currentLocale($request);
        $includeTranslations = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id,
            'site_title' => Localization::translate($this->translations, 'site_title', $this->site_title, $locale),
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_url,
            'favicon_path' => $this->favicon_path,
            'favicon_url' => $this->favicon_url,
            'contact_numbers' => $this->contact_numbers ?? [],
            'email' => $this->email,
            'address' => Localization::translate($this->translations, 'address', $this->address, $locale),
            'social_links' => [
                'instagram' => data_get($this->social_links, 'instagram'),
                'facebook' => data_get($this->social_links, 'facebook'),
                'tiktok' => data_get($this->social_links, 'tiktok'),
            ],
            'forms_recipient_email' => $this->forms_recipient_email,
            'meta' => [
                'title' => Localization::translate($this->translations, 'meta_title', data_get($this->meta, 'title'), $locale),
                'description' => Localization::translate($this->translations, 'meta_description', data_get($this->meta, 'description'), $locale),
                'keywords' => data_get($this->meta, 'keywords', []),
            ],
            'translations' => $this->when($includeTranslations, $this->translations ?? []),
            'google_analytics_pixel' => $this->google_analytics_pixel,
            'meta_pixel' => $this->meta_pixel,
            'meta_pixel_id' => data_get(EcommerceSetting::getValue(EcommerceSetting::KEY_META_PIXEL, [
                'pixel_id' => null,
            ]), 'pixel_id'),
            'localization' => EcommerceSetting::localizationSettings(),
            'currency' => EcommerceSetting::currencySettings(),
            'loyalty' => [
                'first_purchase_discount_enabled' => (bool) data_get($this->loyalty, 'first_purchase_discount_enabled', false),
                'first_purchase_discount_percentage' => (float) data_get($this->loyalty, 'first_purchase_discount_percentage', 0),
                'cashback_enabled' => (bool) data_get($this->loyalty, 'cashback_enabled', false),
                'cashback_earn_percentage' => (float) data_get($this->loyalty, 'cashback_earn_percentage', 0),
                'cashback_redeem_enabled' => (bool) data_get($this->loyalty, 'cashback_redeem_enabled', false),
                'cashback_max_redeem_percentage' => (float) data_get($this->loyalty, 'cashback_max_redeem_percentage', 100),
            ],
            'og_image_path' => $this->og_image_path,
            'og_image_url' => $this->og_image_url,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
