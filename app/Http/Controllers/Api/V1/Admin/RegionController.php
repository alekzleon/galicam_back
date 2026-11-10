<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesRegionalProductAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRegionRequest;
use App\Http\Requests\Admin\SyncRegionProductsRequest;
use App\Http\Requests\Admin\UpdateRegionRequest;
use App\Http\Resources\Region\RegionResource;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RegionController extends Controller
{
    use AuthorizesRegionalProductAccess;

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = Region::query()
            ->withCount('products')
            ->when($regionId = $this->regionalAdminRegionId($request->user()), function ($query) use ($regionId) {
                $query->whereKey($regionId);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            });

        match ($request->string('sort_by', 'sort_order')->toString()) {
            'name_desc' => $query->orderByDesc('name'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'latest' => $query->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            default => $query->ordered(),
        };

        if (filter_var($request->input('without_pagination', false), FILTER_VALIDATE_BOOL)) {
            return response()->json([
                'ok' => true,
                'data' => RegionResource::collection($query->get()),
            ]);
        }

        $regions = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Regiones obtenidas correctamente.',
            'data' => RegionResource::collection($regions->getCollection()),
            'meta' => [
                'current_page' => $regions->currentPage(),
                'last_page' => $regions->lastPage(),
                'per_page' => $regions->perPage(),
                'total' => $regions->total(),
                'from' => $regions->firstItem(),
                'to' => $regions->lastItem(),
            ],
        ]);
    }

    public function store(StoreRegionRequest $request): JsonResponse
    {
        $this->ensureRegionalAdminCannotMutateRegion($request->user());

        $data = $request->validated();
        $products = $this->regionalProductsFromValidatedData($data);

        if ($request->hasFile('banner')) {
            $data['banner_disk'] = 'public';
            $data['banner_path'] = $request->file('banner')->store('regions', 'public');
        }

        unset($data['banner'], $data['product_ids'], $data['products']);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = ((int) Region::query()->max('sort_order')) + 1;
        }

        $region = Region::create($data);
        $this->syncProducts($region, $products);
        $this->loadOrderedProducts($region);

        return response()->json([
            'ok' => true,
            'message' => 'Región creada correctamente.',
            'data' => new RegionResource($region),
        ], 201);
    }

    public function show(Region $region): JsonResponse
    {
        $this->ensureRegionIsVisibleForRegionalAdmin(request()->user(), $region);
        $this->loadOrderedProducts($region);

        return response()->json([
            'ok' => true,
            'message' => 'Región obtenida correctamente.',
            'data' => new RegionResource($region),
        ]);
    }

    public function update(UpdateRegionRequest $request, Region $region): JsonResponse
    {
        $this->ensureRegionalAdminCannotMutateRegion($request->user());

        $data = $request->validated();

        if ($request->boolean('remove_banner')) {
            $this->deleteBanner($region);
            $data['banner_disk'] = null;
            $data['banner_path'] = null;
        }

        if ($request->hasFile('banner')) {
            $this->deleteBanner($region);
            $data['banner_disk'] = 'public';
            $data['banner_path'] = $request->file('banner')->store('regions', 'public');
        }

        $products = $this->regionalProductsFromValidatedData($data);
        $shouldSyncProducts = array_key_exists('product_ids', $data) || array_key_exists('products', $data);
        unset($data['banner'], $data['remove_banner'], $data['product_ids'], $data['products']);

        $region->update($data);

        if ($shouldSyncProducts) {
            $this->syncProducts($region, $products);
        }

        $this->loadOrderedProducts($region);

        return response()->json([
            'ok' => true,
            'message' => 'Región actualizada correctamente.',
            'data' => new RegionResource($region),
        ]);
    }

    public function destroy(Region $region): JsonResponse
    {
        $this->ensureRegionalAdminCannotMutateRegion(request()->user());

        $region->update(['is_active' => false]);
        $region->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => 'Región desactivada correctamente.',
            'data' => new RegionResource($region),
        ]);
    }

    public function updateStatus(Request $request, Region $region): JsonResponse
    {
        $this->ensureRegionalAdminCannotMutateRegion($request->user());

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $region->update(['is_active' => (bool) $validated['is_active']]);
        $region->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => $region->is_active
                ? 'Región activada correctamente.'
                : 'Región desactivada correctamente.',
            'data' => new RegionResource($region),
        ]);
    }

    public function syncRegionProducts(SyncRegionProductsRequest $request, Region $region): JsonResponse
    {
        $this->ensureRegionalAdminCannotMutateRegion($request->user());

        $data = $request->validated();
        $this->syncProducts($region, $this->regionalProductsFromValidatedData($data));
        $this->loadOrderedProducts($region);

        return response()->json([
            'ok' => true,
            'message' => 'Productos de la región actualizados correctamente.',
            'data' => new RegionResource($region),
        ]);
    }

    protected function syncProducts(Region $region, array $products): void
    {
        $syncPayload = collect($products)
            ->filter()
            ->unique('product_id')
            ->values()
            ->mapWithKeys(fn ($product, $index) => [
                (int) $product['product_id'] => [
                    'is_active' => (bool) ($product['is_active'] ?? true),
                    'regional_price' => $product['regional_price'] ?? null,
                    'regional_stock' => $product['regional_stock'] ?? null,
                    'commission_rate' => $product['commission_rate'] ?? null,
                    'metadata' => isset($product['metadata']) ? json_encode($product['metadata']) : null,
                    'sort_order' => (int) ($product['sort_order'] ?? ($index + 1)),
                ],
            ])
            ->all();

        $region->products()->sync($syncPayload);
    }

    protected function regionalProductsFromValidatedData(array $data): array
    {
        if (array_key_exists('products', $data)) {
            return collect($data['products'] ?? [])
                ->map(fn (array $product) => [
                    'product_id' => (int) $product['product_id'],
                    'is_active' => $product['is_active'] ?? true,
                    'regional_price' => array_key_exists('regional_price', $product) && $product['regional_price'] !== null
                        ? round((float) $product['regional_price'], 2)
                        : null,
                    'regional_stock' => array_key_exists('regional_stock', $product) && $product['regional_stock'] !== null
                        ? round((float) $product['regional_stock'], 2)
                        : null,
                    'commission_rate' => array_key_exists('commission_rate', $product) && $product['commission_rate'] !== null
                        ? round((float) $product['commission_rate'], 2)
                        : null,
                    'sort_order' => $product['sort_order'] ?? null,
                    'metadata' => $product['metadata'] ?? null,
                ])
                ->values()
                ->all();
        }

        return collect($data['product_ids'] ?? [])
            ->map(fn ($productId, $index) => [
                'product_id' => (int) $productId,
                'sort_order' => $index + 1,
                'is_active' => true,
            ])
            ->values()
            ->all();
    }

    protected function ensureRegionIsVisibleForRegionalAdmin($user, Region $region): void
    {
        $regionId = $this->regionalAdminRegionId($user);

        if ($regionId === null) {
            return;
        }

        abort_unless((int) $region->id === $regionId, 404, 'Región no encontrada para tu usuario.');
    }

    protected function ensureRegionalAdminCannotMutateRegion($user): void
    {
        if ($this->regionalAdminRegionId($user) !== null) {
            abort(403, 'Los cambios del centro regional requieren autorización del administrador principal.');
        }
    }

    protected function loadOrderedProducts(Region $region): void
    {
        $region->load(['products' => fn ($query) => $query->orderBy('product_region.sort_order')->orderBy('products.name')])
            ->loadCount('products');
    }

    protected function deleteBanner(Region $region): void
    {
        if ($region->banner_path && Storage::disk($region->banner_disk ?: 'public')->exists($region->banner_path)) {
            Storage::disk($region->banner_disk ?: 'public')->delete($region->banner_path);
        }
    }
}
