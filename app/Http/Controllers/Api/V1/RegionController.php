<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Region\RegionResource;
use App\Models\Product;
use App\Models\Region;
use App\Support\Currency;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 0);

        $query = Region::query()
            ->active()
            ->withCount(['products' => fn ($query) => $query
                ->where('products.is_active', true)
                ->where('product_region.is_active', true)])
            ->ordered();

        $regions = $limit > 0
            ? $query->limit(min($limit, 100))->get()
            : $query->get();

        return response()->json([
            'ok' => true,
            'data' => RegionResource::collection($regions),
            'meta' => [
                'locale' => Localization::currentLocale($request),
            ],
        ]);
    }

    public function menu(Request $request): JsonResponse
    {
        $locale = Localization::currentLocale($request);

        $regions = Region::query()
            ->active()
            ->withCount(['products' => fn ($query) => $query
                ->where('products.is_active', true)
                ->where('product_region.is_active', true)])
            ->ordered()
            ->get()
            ->map(fn (Region $region) => [
                'id' => $region->id,
                'name' => Localization::translate($region->translations, 'name', $region->name, $locale),
                'slug' => $region->slug,
                'products_count' => (int) ($region->products_count ?? 0),
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'data' => $regions,
            'meta' => [
                'locale' => $locale,
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $region = Region::query()
            ->active()
            ->withCount(['products' => fn ($query) => $query
                ->where('products.is_active', true)
                ->where('product_region.is_active', true)])
            ->where('slug', $slug)
            ->first();

        if (! $region) {
            return response()->json([
                'ok' => false,
                'message' => 'Región no encontrada.',
            ], 404);
        }

        $perPage = max(1, min((int) $request->integer('per_page', 24), 100));
        $sort = trim((string) $request->string('sort')->toString());
        $user = $request->user('sanctum') ?? $request->user();
        $userId = $user ? (int) $user->id : null;

        $productsQuery = $region->products()
            ->with([
                'category:id,grupo_linea_id,name,slug,translations',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug,translations',
                'promotions' => function ($query) use ($user) {
                    $query->usable($user)
                        ->orderBy('priority')
                        ->orderByDesc('id');
                },
            ])
            ->wherePivot('is_active', true)
            ->where('products.is_active', true)
            ->when($userId, function ($query) use ($userId) {
                $query->withExists([
                    'favoritedByUsers as is_favorite' => fn ($favoriteQuery) => $favoriteQuery->where('users.id', $userId),
                ]);
            });

        match ($sort) {
            'price_asc' => $productsQuery->orderByRaw('COALESCE(product_region.regional_price, products.default_price) asc'),
            'price_desc' => $productsQuery->orderByRaw('COALESCE(product_region.regional_price, products.default_price) desc'),
            'name_asc' => $productsQuery->orderBy('products.name', 'asc'),
            'name_desc' => $productsQuery->orderBy('products.name', 'desc'),
            default => $productsQuery->orderBy('product_region.sort_order')->orderBy('products.name')->orderBy('products.id'),
        };

        $products = $productsQuery->paginate($perPage)->appends($request->query());
        $formatter = app(ProductController::class);

        $products->getCollection()->transform(fn (Product $product) => $formatter->formatProduct($product));

        return response()->json([
            'ok' => true,
            'data' => [
                'region' => new RegionResource($region),
                'products' => $products->items(),
            ],
            'meta' => [
                'locale' => Localization::currentLocale($request),
                'currency' => Currency::currentCurrency($request),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }
}
