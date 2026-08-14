<?php

namespace App\Http\Controllers\Api\V1\Admin;

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
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = Region::query()
            ->withCount('products')
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
        $data = $request->validated();
        $productIds = $data['product_ids'] ?? [];

        if ($request->hasFile('banner')) {
            $data['banner_disk'] = 'public';
            $data['banner_path'] = $request->file('banner')->store('regions', 'public');
        }

        unset($data['banner'], $data['product_ids']);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = ((int) Region::query()->max('sort_order')) + 1;
        }

        $region = Region::create($data);
        $this->syncProducts($region, $productIds);
        $this->loadOrderedProducts($region);

        return response()->json([
            'ok' => true,
            'message' => 'Región creada correctamente.',
            'data' => new RegionResource($region),
        ], 201);
    }

    public function show(Region $region): JsonResponse
    {
        $this->loadOrderedProducts($region);

        return response()->json([
            'ok' => true,
            'message' => 'Región obtenida correctamente.',
            'data' => new RegionResource($region),
        ]);
    }

    public function update(UpdateRegionRequest $request, Region $region): JsonResponse
    {
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

        $productIds = $data['product_ids'] ?? null;
        unset($data['banner'], $data['remove_banner'], $data['product_ids']);

        $region->update($data);

        if ($productIds !== null) {
            $this->syncProducts($region, $productIds);
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
        $this->syncProducts($region, $request->validated('product_ids'));
        $this->loadOrderedProducts($region);

        return response()->json([
            'ok' => true,
            'message' => 'Productos de la región actualizados correctamente.',
            'data' => new RegionResource($region),
        ]);
    }

    protected function syncProducts(Region $region, array $productIds): void
    {
        $syncPayload = collect($productIds)
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(fn ($productId, $index) => [
                (int) $productId => ['sort_order' => $index + 1],
            ])
            ->all();

        $region->products()->sync($syncPayload);
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
