<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArtisanRequest;
use App\Http\Requests\Admin\SyncArtisanProductsRequest;
use App\Http\Requests\Admin\UpdateArtisanRequest;
use App\Http\Resources\Artisan\ArtisanResource;
use App\Models\Artisan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArtisanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = Artisan::query()
            ->with(['region:id,name,slug,is_active'])
            ->withCount('products')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('history', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('region_id'), fn ($query) => $query->where('region_id', (int) $request->integer('region_id')))
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
                'data' => ArtisanResource::collection($query->get()),
            ]);
        }

        $artisans = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Artesanos obtenidos correctamente.',
            'data' => ArtisanResource::collection($artisans->getCollection()),
            'meta' => [
                'current_page' => $artisans->currentPage(),
                'last_page' => $artisans->lastPage(),
                'per_page' => $artisans->perPage(),
                'total' => $artisans->total(),
                'from' => $artisans->firstItem(),
                'to' => $artisans->lastItem(),
            ],
        ]);
    }

    public function store(StoreArtisanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $products = $this->productsFromValidatedData($data);

        if ($request->hasFile('photo')) {
            $data['photo_disk'] = 'public';
            $data['photo_path'] = $request->file('photo')->store('artisans', 'public');
        }

        unset($data['photo'], $data['product_ids'], $data['products']);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = ((int) Artisan::query()->max('sort_order')) + 1;
        }

        $artisan = Artisan::create($data);
        $this->syncProducts($artisan, $products);
        $this->loadShowRelations($artisan);

        return response()->json([
            'ok' => true,
            'message' => 'Artesano creado correctamente.',
            'data' => new ArtisanResource($artisan),
        ], 201);
    }

    public function show(Artisan $artisan): JsonResponse
    {
        $this->loadShowRelations($artisan);

        return response()->json([
            'ok' => true,
            'message' => 'Artesano obtenido correctamente.',
            'data' => new ArtisanResource($artisan),
        ]);
    }

    public function update(UpdateArtisanRequest $request, Artisan $artisan): JsonResponse
    {
        $data = $request->validated();

        if ($request->boolean('remove_photo')) {
            $this->deletePhoto($artisan);
            $data['photo_disk'] = null;
            $data['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            $this->deletePhoto($artisan);
            $data['photo_disk'] = 'public';
            $data['photo_path'] = $request->file('photo')->store('artisans', 'public');
        }

        $products = $this->productsFromValidatedData($data);
        $shouldSyncProducts = array_key_exists('product_ids', $data) || array_key_exists('products', $data);
        unset($data['photo'], $data['remove_photo'], $data['product_ids'], $data['products']);

        $artisan->update($data);

        if ($shouldSyncProducts) {
            $this->syncProducts($artisan, $products);
        }

        $this->loadShowRelations($artisan);

        return response()->json([
            'ok' => true,
            'message' => 'Artesano actualizado correctamente.',
            'data' => new ArtisanResource($artisan),
        ]);
    }

    public function destroy(Artisan $artisan): JsonResponse
    {
        $artisan->update(['is_active' => false]);
        $artisan->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => 'Artesano desactivado correctamente.',
            'data' => new ArtisanResource($artisan->load('region:id,name,slug,is_active')),
        ]);
    }

    public function updateStatus(Request $request, Artisan $artisan): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $artisan->update(['is_active' => (bool) $validated['is_active']]);
        $artisan->load('region:id,name,slug,is_active')->loadCount('products');

        return response()->json([
            'ok' => true,
            'message' => $artisan->is_active
                ? 'Artesano activado correctamente.'
                : 'Artesano desactivado correctamente.',
            'data' => new ArtisanResource($artisan),
        ]);
    }

    public function syncArtisanProducts(SyncArtisanProductsRequest $request, Artisan $artisan): JsonResponse
    {
        $data = $request->validated();
        $this->syncProducts($artisan, $this->productsFromValidatedData($data));
        $this->loadShowRelations($artisan);

        return response()->json([
            'ok' => true,
            'message' => 'Productos del artesano actualizados correctamente.',
            'data' => new ArtisanResource($artisan),
        ]);
    }

    protected function syncProducts(Artisan $artisan, array $products): void
    {
        $syncPayload = collect($products)
            ->filter()
            ->unique('product_id')
            ->values()
            ->mapWithKeys(fn ($product, $index) => [
                (int) $product['product_id'] => [
                    'sort_order' => (int) ($product['sort_order'] ?? ($index + 1)),
                ],
            ])
            ->all();

        $artisan->products()->sync($syncPayload);
    }

    protected function productsFromValidatedData(array $data): array
    {
        if (array_key_exists('products', $data)) {
            return collect($data['products'] ?? [])
                ->map(fn (array $product) => [
                    'product_id' => (int) $product['product_id'],
                    'sort_order' => $product['sort_order'] ?? null,
                ])
                ->values()
                ->all();
        }

        return collect($data['product_ids'] ?? [])
            ->map(fn ($productId, $index) => [
                'product_id' => (int) $productId,
                'sort_order' => $index + 1,
            ])
            ->values()
            ->all();
    }

    protected function loadShowRelations(Artisan $artisan): void
    {
        $artisan->load([
            'region:id,name,slug,is_active',
            'products' => fn ($query) => $query->orderBy('artisan_product.sort_order')->orderBy('products.name'),
        ])->loadCount('products');
    }

    protected function deletePhoto(Artisan $artisan): void
    {
        if ($artisan->photo_path && Storage::disk($artisan->photo_disk ?: 'public')->exists($artisan->photo_path)) {
            Storage::disk($artisan->photo_disk ?: 'public')->delete($artisan->photo_path);
        }
    }
}
