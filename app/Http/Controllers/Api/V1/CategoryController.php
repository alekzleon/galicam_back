<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Currency;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = Localization::currentLocale($request);
        $limit = max(1, min((int) $request->integer('limit', 8), 24));

        $categories = Category::query()
            ->where('is_active', true)
            ->withCount([
                'products as active_products_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->limit($limit)
            ->get([
                'id',
                'grupo_linea_id',
                'code',
                'name',
                'slug',
                'translations',
                'image_path',
            ])
            ->map(fn (Category $category) => $this->formatCategory($category, $locale))
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $categories,
            'meta' => [
                'locale' => $locale,
                'currency' => Currency::currentCurrency($request),
                'limit' => $limit,
            ],
        ]);
    }

    public function products(Request $request, Category $category, ProductController $productController): JsonResponse
    {
        abort_unless($category->is_active, 404);

        $perPage = max(1, min((int) $request->integer('per_page', 24), 60));
        $sort = trim((string) $request->string('sort')->toString());
        $user = $request->user('sanctum') ?? $request->user();
        $userId = $user ? (int) $user->id : null;

        $query = Product::query()
            ->with([
                'category:id,grupo_linea_id,name,slug,translations',
                'family:id,linea_articulo_id,category_id,grupo_linea_id,name,slug,translations',
                'promotions' => function ($query) {
                    $query->usable(null)
                        ->orderBy('priority')
                        ->orderByDesc('id');
                },
            ])
            ->where('is_active', true)
            ->where(function ($productQuery) use ($category) {
                $productQuery->where('category_id', $category->id)
                    ->orWhereHas('category', function ($categoryQuery) use ($category) {
                        $categoryQuery->where('grupo_linea_id', $category->grupo_linea_id ?? $category->id);
                    });
            });

        if ($userId) {
            $query->withExists([
                'favoritedByUsers as is_favorite' => fn ($favoriteQuery) => $favoriteQuery->where('users.id', $userId),
            ]);
        }

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('default_price');
                break;
            case 'price_desc':
                $query->orderByDesc('default_price');
                break;
            case 'name_desc':
                $query->orderByDesc('name')->orderByDesc('id');
                break;
            case 'name_asc':
            default:
                $query->orderBy('name')->orderBy('id');
                break;
        }

        $products = $query->paginate($perPage)->appends($request->query());
        $products->getCollection()->transform(fn (Product $product) => $productController->formatProduct($product));

        return response()->json([
            'ok' => true,
            'data' => [
                'category' => $this->formatCategory(
                    $category->loadCount([
                        'products as active_products_count' => fn ($query) => $query->where('is_active', true),
                    ]),
                    Localization::currentLocale($request)
                ),
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

    protected function formatCategory(Category $category, string $locale): array
    {
        $slug = $category->slug;

        return [
            'id' => $category->grupo_linea_id ?? $category->id,
            'local_id' => $category->id,
            'grupo_linea_id' => $category->grupo_linea_id,
            'code' => $category->code,
            'name' => Localization::translate($category->translations, 'name', $category->name, $locale),
            'slug' => $slug,
            'image_path' => $category->image_path,
            'image_url' => $category->image_url,
            'products_count' => (int) ($category->active_products_count ?? 0),
            'links' => [
                'products' => url("/api/v1/categories/{$slug}/products"),
                'catalog' => url("/api/v1/products?category_slug={$slug}"),
            ],
        ];
    }
}
