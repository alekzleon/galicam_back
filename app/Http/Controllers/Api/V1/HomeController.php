<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Banner\BannerResource;
use App\Http\Resources\BrandBanner\BrandBannerResource;
use App\Http\Resources\SiteSettingResource;
use App\Models\Banner;
use App\Models\BrandBanner;
use App\Models\Category;
use App\Models\EcommerceSetting;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SiteSetting;
use App\Support\Currency;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function storefront(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $this->storefrontPayload(),
            'meta' => [
                'locale' => Localization::currentLocale(request()),
                'currency' => Currency::currentCurrency(request()),
            ],
        ]);
    }

    public function home(): JsonResponse
    {
        $storefront = $this->storefrontPayload();

        if (! (bool) data_get($storefront, 'is_published', false)) {
            return response()->json([
                'ok' => true,
                'message' => 'El ecommerce todavía no está publicado.',
                'data' => [
                    'storefront' => $storefront,
                    'sections' => [],
                ],
                'meta' => [
                    'locale' => Localization::currentLocale(request()),
                    'currency' => Currency::currentCurrency(request()),
                ],
            ]);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'storefront' => $storefront,
                'sections' => $this->homeSections(),
            ],
            'meta' => [
                'locale' => Localization::currentLocale(request()),
                'currency' => Currency::currentCurrency(request()),
            ],
        ]);
    }

    public function sections(Request $request): JsonResponse
    {
        $newProductsLimit = $this->boundedLimit($request, 'new_products_limit', 10, 1, 24);
        $termsLimit = $this->boundedLimit($request, 'terms_limit', 6, 1, 12);
        $productsLimit = $this->boundedLimit($request, 'products_limit', 4, 1, 12);

        return response()->json([
            'ok' => true,
            'data' => [
                'new_products' => $this->newProductsPayload($request, $newProductsLimit),
                'popular_searches' => $this->popularSearchesPayload($request, $termsLimit, $productsLimit),
            ],
            'meta' => [
                'locale' => Localization::currentLocale($request),
                'currency' => Currency::currentCurrency($request),
            ],
        ]);
    }

    public function newProducts(Request $request): JsonResponse
    {
        $limit = $this->boundedLimit($request, 'limit', 10, 1, 24);

        return response()->json([
            'ok' => true,
            'data' => $this->newProductsPayload($request, $limit),
            'meta' => [
                'locale' => Localization::currentLocale($request),
                'currency' => Currency::currentCurrency($request),
                'limit' => $limit,
            ],
        ]);
    }

    public function popularSearches(Request $request): JsonResponse
    {
        $termsLimit = $this->boundedLimit($request, 'limit', 6, 1, 12);
        $productsLimit = $this->boundedLimit($request, 'products_limit', 4, 1, 12);

        return response()->json([
            'ok' => true,
            'data' => $this->popularSearchesPayload($request, $termsLimit, $productsLimit),
            'meta' => [
                'locale' => Localization::currentLocale($request),
                'currency' => Currency::currentCurrency($request),
                'limit' => $termsLimit,
                'products_limit' => $productsLimit,
            ],
        ]);
    }

    protected function storefrontPayload(): array
    {
        $storefront = EcommerceSetting::storefrontSettings();
        $template = EcommerceSetting::homeTemplateSettings();

        return [
            'is_published' => (bool) data_get($storefront, 'is_published', false),
            'construction' => [
                'title' => data_get($storefront, 'construction_title'),
                'message' => data_get($storefront, 'construction_message'),
            ],
            'active_template' => data_get($template, 'active_template', EcommerceSetting::HOME_TEMPLATE_CLASSIC),
            'available_templates' => EcommerceSetting::availableTemplates(),
            'localization' => EcommerceSetting::localizationSettings(),
            'currency' => EcommerceSetting::currencySettings(),
        ];
    }

    protected function homeSections(): array
    {
        return [
            'hero_banners' => BannerResource::collection(
                Banner::query()->active()->currentWindow()->ordered()->limit(5)->get()
            )->resolve(),
            'hero_brand_banners' => BrandBannerResource::collection(
                BrandBanner::query()->active()->currentWindow()->ordered()->limit(5)->get()
            )->resolve(),
            'benefits' => $this->homeBenefitsValue(),
            'featured_categories' => $this->featuredCategories(),
            'promotions' => $this->promotions(),
            'daily_offers' => $this->promotions(2),
            'featured_products' => $this->featuredProducts(),
            'recent_purchase_products' => $this->featuredProducts(8),
            'brand_banners' => BrandBannerResource::collection(
                BrandBanner::query()->active()->currentWindow()->ordered()->limit(6)->get()
            )->resolve(),
            'footer_settings' => (new SiteSettingResource(SiteSetting::current()))->resolve(),
        ];
    }

    protected function homeBenefitsValue(): array
    {
        return collect([1, 2, 3])
            ->map(function (int $benefit) {
                $value = EcommerceSetting::homeBenefitValue($benefit);
                $path = data_get($value, 'icon_path');
                $disk = data_get($value, 'icon_disk', 'public') ?: 'public';
                $translations = data_get($value, 'translations', []);
                $locale = Localization::currentLocale(request());

                return [
                    'benefit' => $benefit,
                    'title' => Localization::translate($translations, 'title', data_get($value, 'title'), $locale),
                    'text' => Localization::translate($translations, 'text', data_get($value, 'text'), $locale),
                    'icon_disk' => $disk,
                    'icon_path' => $path,
                    'icon_url' => $path ? Storage::disk($disk)->url($path) : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function featuredCategories(): array
    {
        $locale = Localization::currentLocale(request());

        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(8)
            ->get([
                'id',
                'grupo_linea_id',
                'code',
                'name',
                'slug',
                'translations',
                'image_path',
            ])
            ->map(fn (Category $category) => [
                'id' => $category->grupo_linea_id ?? $category->id,
                'local_id' => $category->id,
                'grupo_linea_id' => $category->grupo_linea_id,
                'code' => $category->code,
                'name' => Localization::translate($category->translations, 'name', $category->name, $locale),
                'slug' => $category->slug,
                'image_path' => $category->image_path,
                'image_url' => $category->image_url,
            ])
            ->values()
            ->all();
    }

    protected function promotions(int $limit = 6): array
    {
        $locale = Localization::currentLocale(request());

        return Promotion::query()
            ->with(['products:id,name,slug,sku,translations,default_price'])
            ->withCount('products')
            ->active()
            ->currentWindow()
            ->where('is_general', true)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Promotion $promotion) => [
                'id' => $promotion->id,
                'name' => Localization::translate($promotion->translations, 'name', $promotion->name, $locale),
                'slug' => $promotion->slug,
                'type' => $promotion->type->value,
                'label' => $promotion->type->label(),
                'description' => Localization::translate($promotion->translations, 'description', $promotion->description, $locale),
                'priority' => $promotion->priority,
                'image_path' => $promotion->image_path,
                'image_url' => $promotion->image_path
                    ? asset('storage/' . ltrim($promotion->image_path, '/'))
                    : null,
                'price_money' => is_numeric(data_get($promotion->config, 'promotional_price'))
                    ? Currency::money((float) data_get($promotion->config, 'promotional_price'))
                    : null,
                'config_money' => [
                    'promotional_price' => is_numeric(data_get($promotion->config, 'promotional_price'))
                        ? Currency::money((float) data_get($promotion->config, 'promotional_price'))
                        : null,
                ],
                'products_count' => (int) ($promotion->products_count ?? 0),
                'product_ids' => $promotion->products->pluck('id')->values(),
            ])
            ->values()
            ->all();
    }

    protected function featuredProducts(int $limit = 12): array
    {
        $locale = Localization::currentLocale(request());

        return Product::query()
            ->with([
                'category:id,grupo_linea_id,name,slug,translations',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug,translations',
            ])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'family_id' => $product->family_id,
                'category' => $product->category ? [
                    'id' => $product->category->grupo_linea_id ?? $product->category->id,
                    'local_id' => $product->category->id,
                    'name' => Localization::translate($product->category->translations, 'name', $product->category->name, $locale),
                    'slug' => $product->category->slug,
                ] : null,
                'family' => $product->family ? [
                    'id' => $product->family->linea_articulo_id ?? $product->family->id,
                    'local_id' => $product->family->id,
                    'name' => Localization::translate($product->family->translations, 'name', $product->family->name, $locale),
                    'slug' => $product->family->slug,
                ] : null,
                'name' => Localization::translate($product->translations, 'name', $product->name, $locale),
                'slug' => $product->slug,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'short_description' => Localization::translate($product->translations, 'short_description', $product->short_description, $locale),
                'image_path' => $product->image_path,
                'image_url' => $product->image_url,
                'default_price' => (float) $product->default_price,
                'base_default_price' => (float) $product->default_price,
                'price_money' => Currency::money((float) $product->default_price),
                'stock' => $product->stock !== null ? (float) $product->stock : null,
            ])
            ->values()
            ->all();
    }

    protected function newProductsPayload(Request $request, int $limit): array
    {
        return $this->homeProductQuery()
            ->orderByDesc('products.created_at')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->compactProductPayload($product, $request))
            ->values()
            ->all();
    }

    protected function popularSearchesPayload(Request $request, int $termsLimit, int $productsLimit): array
    {
        $locale = Localization::currentLocale($request);

        $categories = Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->where('products.is_active', true)])
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit($termsLimit)
            ->get(['id', 'grupo_linea_id', 'name', 'slug', 'translations']);

        $terms = $categories
            ->map(fn (Category $category) => [
                'label' => Localization::translate($category->translations, 'name', $category->name, $locale),
                'value' => $category->slug,
            ])
            ->values()
            ->all();

        $products = $this->homeProductQuery()
            ->when($categories->isNotEmpty(), fn ($query) => $query->whereIn('category_id', $categories->pluck('id')))
            ->orderByDesc('products.id')
            ->limit($productsLimit)
            ->get()
            ->map(fn (Product $product) => array_merge(
                $this->compactProductPayload($product, $request),
                ['is_favorite' => false]
            ))
            ->values()
            ->all();

        return [
            'terms' => $terms,
            'products' => $products,
        ];
    }

    protected function compactProductPayload(Product $product, Request $request): array
    {
        $locale = Localization::currentLocale($request);
        $currency = Currency::currentCurrency($request);
        $price = Currency::money((float) $product->default_price, $currency);

        return [
            'id' => $product->id,
            'name' => Localization::translate($product->translations, 'name', $product->name, $locale),
            'slug' => $product->slug,
            'price' => (float) data_get($price, 'amount', (float) $product->default_price),
            'currency' => $currency,
            'image_url' => $product->image_url,
            'category' => $product->category ? [
                'id' => $product->category->grupo_linea_id ?? $product->category->id,
                'name' => Localization::translate($product->category->translations, 'name', $product->category->name, $locale),
                'slug' => $product->category->slug,
            ] : null,
        ];
    }

    protected function homeProductQuery()
    {
        return Product::query()
            ->with('category:id,grupo_linea_id,name,slug,translations')
            ->where('products.is_active', true);
    }

    protected function boundedLimit(Request $request, string $key, int $default, int $min, int $max): int
    {
        return max($min, min((int) $request->integer($key, $default), $max));
    }
}
