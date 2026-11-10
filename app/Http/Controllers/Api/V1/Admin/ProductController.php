<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesRegionalProductAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRegionalCatalogRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use AuthorizesRegionalProductAccess;

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);

        $query = Product::query()
            ->with(['category', 'family', 'regions']);

        $this->applyRegionalProductScope($query, $request->user());

        $products = $query
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', (int) $request->category_id);
            })
            ->when($request->filled('family_id'), function ($query) use ($request) {
                $query->where('family_id', (int) $request->family_id);
            })
            ->when($request->filled('region_id') && ! $request->user()?->isRegionalAdmin(), function ($query) use ($request) {
                $query->whereHas('regions', fn ($regionQuery) => $regionQuery->where('regions.id', (int) $request->region_id));
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->when($request->filled('processed'), function ($query) use ($request) {
                $processed = filter_var($request->input('processed'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($processed !== null) {
                    $query->where('processed', $processed);
                }
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'message' => 'Productos obtenidos correctamente.',
            'data' => AdminProductResource::collection($products->getCollection()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        unset($data['image']);

        $product = Product::create($data);

        if ($regionId = $this->regionalAdminRegionId($request->user())) {
            $product->regions()->syncWithoutDetaching([
                $regionId => ['sort_order' => 0],
            ]);
        }

        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto creado correctamente.',
            'data' => new AdminProductResource($product),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->ensureProductIsVisibleForRegionalAdmin(request()->user(), $product);
        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto obtenido correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->ensureProductIsVisibleForRegionalAdmin($request->user(), $product);
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }

            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        unset($data['image']);

        $product->update($data);
        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto actualizado correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    public function updateStatus(Request $request, Product $product): JsonResponse
    {
        $this->ensureProductIsVisibleForRegionalAdmin($request->user(), $product);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ], [
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ]);

        $product->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => $product->is_active
                ? 'Producto activado correctamente.'
                : 'Producto desactivado correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    public function updateRegionalCatalog(UpdateProductRegionalCatalogRequest $request, Product $product): JsonResponse
    {
        $user = $request->user();
        $regionId = $this->regionalAdminRegionId($user) ?? (int) $request->integer('region_id');

        abort_if($regionId <= 0, 422, 'El centro regional es obligatorio.');

        if ($user?->isRegionalAdmin()) {
            $this->ensureProductIsVisibleForRegionalAdmin($user, $product);
        }

        $data = collect($request->validated())
            ->except('region_id')
            ->all();

        $payload = [];

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        foreach (['regional_price', 'regional_stock', 'commission_rate'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field] !== null ? round((float) $data[$field], 2) : null;
            }
        }

        if (array_key_exists('metadata', $data)) {
            $payload['metadata'] = $data['metadata'] !== null ? json_encode($data['metadata']) : null;
        }

        if ($payload === []) {
            $payload['is_active'] = true;
        }

        $product->regions()->syncWithoutDetaching([
            $regionId => $payload,
        ]);

        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Configuración regional del producto actualizada correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->ensureProductIsVisibleForRegionalAdmin(request()->user(), $product);

        $product->update([
            'is_active' => false,
        ]);

        $product->load($this->productDetailRelations());

        return response()->json([
            'ok' => true,
            'message' => 'Producto desactivado correctamente.',
            'data' => new AdminProductResource($product),
        ]);
    }

    protected function productDetailRelations(): array
    {
        return [
            'category',
            'family',
            'regions',
            'galleryItems',
            'variantAttributes.values',
            'variants.attributeValues.attribute',
        ];
    }
}
