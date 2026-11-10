<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Artisan\ArtisanResource;
use App\Models\Artisan;
use App\Models\Product;
use App\Support\Currency;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtisanController extends Controller
{
    public function randomSpotlight(Request $request): JsonResponse
    {
        $locale = Localization::currentLocale($request);

        $artisan = Artisan::query()
            ->active()
            ->whereNotNull('photo_path')
            ->inRandomOrder()
            ->first();

        return response()->json([
            'ok' => true,
            'data' => $artisan ? [
                'name' => Localization::translate($artisan->translations, 'name', $artisan->name, $locale),
                'photo_path' => $artisan->photo_path,
                'photo_url' => $artisan->photo_url,
                'photo_alt' => Localization::translate($artisan->translations, 'photo_alt', $artisan->photo_alt, $locale)
                    ?: Localization::translate($artisan->translations, 'name', $artisan->name, $locale),
            ] : null,
            'meta' => [
                'locale' => $locale,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 0);

        $query = Artisan::query()
            ->active()
            ->with(['region:id,name,slug,is_active'])
            ->withCount(['products' => fn ($query) => $query->where('products.is_active', true)])
            ->when($request->filled('region_id'), fn ($query) => $query->where('region_id', (int) $request->integer('region_id')))
            ->when($request->filled('region_slug'), function ($query) use ($request) {
                $query->whereHas('region', fn ($regionQuery) => $regionQuery->where('slug', (string) $request->string('region_slug')));
            })
            ->ordered();

        $artisans = $limit > 0
            ? $query->limit(min($limit, 100))->get()
            : $query->get();

        return response()->json([
            'ok' => true,
            'data' => ArtisanResource::collection($artisans),
            'meta' => [
                'locale' => Localization::currentLocale($request),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $artisan = Artisan::query()
            ->active()
            ->with(['region:id,name,slug,is_active'])
            ->withCount(['products' => fn ($query) => $query->where('products.is_active', true)])
            ->where('slug', $slug)
            ->first();

        if (! $artisan) {
            return response()->json([
                'ok' => false,
                'message' => 'Artesano no encontrado.',
            ], 404);
        }

        $perPage = max(1, min((int) $request->integer('per_page', 24), 100));
        $sort = trim((string) $request->string('sort')->toString());
        $user = $request->user('sanctum') ?? $request->user();
        $userId = $user ? (int) $user->id : null;

        $productsQuery = $artisan->products()
            ->with([
                'category:id,grupo_linea_id,name,slug,translations',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug,translations',
                'promotions' => function ($query) use ($user) {
                    $query->usable($user)
                        ->orderBy('priority')
                        ->orderByDesc('id');
                },
            ])
            ->where('products.is_active', true)
            ->when($userId, function ($query) use ($userId) {
                $query->withExists([
                    'favoritedByUsers as is_favorite' => fn ($favoriteQuery) => $favoriteQuery->where('users.id', $userId),
                ]);
            });

        match ($sort) {
            'price_asc' => $productsQuery->orderBy('products.default_price', 'asc'),
            'price_desc' => $productsQuery->orderBy('products.default_price', 'desc'),
            'name_desc' => $productsQuery->orderBy('products.name', 'desc'),
            'name_asc' => $productsQuery->orderBy('products.name', 'asc'),
            default => $productsQuery->orderBy('artisan_product.sort_order')->orderBy('products.name')->orderBy('products.id'),
        };

        $products = $productsQuery->paginate($perPage)->appends($request->query());
        $formatter = app(ProductController::class);
        $products->getCollection()->transform(fn (Product $product) => $formatter->formatProduct($product));

        return response()->json([
            'ok' => true,
            'data' => [
                'artisan' => new ArtisanResource($artisan),
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
