<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesRegionalProductAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRegionProfileChangeRequest;
use App\Http\Requests\Admin\StoreRegionProfileChangeRequest;
use App\Models\Region;
use App\Models\RegionProfileChangeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegionProfileChangeRequestController extends Controller
{
    use AuthorizesRegionalProductAccess;

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = RegionProfileChangeRequest::query()
            ->with(['region', 'requester:id,name,email,username', 'reviewer:id,name,email,username'])
            ->when($regionId = $this->regionalAdminRegionId($request->user()), fn ($query) => $query->where('region_id', $regionId))
            ->when($request->filled('region_id') && ! $request->user()?->isRegionalAdmin(), fn ($query) => $query->where('region_id', (int) $request->integer('region_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id');

        $requests = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Solicitudes de cambio obtenidas correctamente.',
            'data' => $requests->getCollection()->map(fn ($changeRequest) => $this->payload($changeRequest))->values(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'from' => $requests->firstItem(),
                'to' => $requests->lastItem(),
            ],
        ]);
    }

    public function store(StoreRegionProfileChangeRequest $request, Region $region): JsonResponse
    {
        $this->ensureRegionIsVisibleForRegionalAdmin($request->user(), $region);

        abort_if(
            $region->profileChangeRequests()->pending()->exists(),
            422,
            'Esta región ya tiene una solicitud pendiente de revisión.'
        );

        $data = $request->validated();
        $changes = collect($data)
            ->only(['description', 'banner_alt', 'remove_banner', 'translations'])
            ->all();

        if ($request->hasFile('banner')) {
            $changes['banner_disk'] = 'public';
            $changes['banner_path'] = $request->file('banner')->store('region-profile-change-requests', 'public');
            $changes['remove_banner'] = false;
        }

        $changeRequest = $region->profileChangeRequests()->create([
            'requested_by' => $request->user()->id,
            'status' => RegionProfileChangeRequest::STATUS_PENDING,
            'current_snapshot' => $this->regionSnapshot($region),
            'proposed_changes' => $changes,
            'request_notes' => $data['request_notes'] ?? null,
        ]);

        $changeRequest->load(['region', 'requester:id,name,email,username', 'reviewer:id,name,email,username']);

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud de cambio enviada para aprobación.',
            'data' => $this->payload($changeRequest),
        ], 201);
    }

    public function show(Request $request, RegionProfileChangeRequest $changeRequest): JsonResponse
    {
        $this->ensureChangeRequestIsVisibleForRegionalAdmin($request->user(), $changeRequest);
        $changeRequest->load(['region', 'requester:id,name,email,username', 'reviewer:id,name,email,username']);

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud de cambio obtenida correctamente.',
            'data' => $this->payload($changeRequest),
        ]);
    }

    public function approve(
        ReviewRegionProfileChangeRequest $request,
        RegionProfileChangeRequest $changeRequest
    ): JsonResponse {
        $this->ensureReviewerCanReview($request->user());

        abort_unless($changeRequest->status === RegionProfileChangeRequest::STATUS_PENDING, 422, 'La solicitud ya fue revisada.');

        $changeRequest = DB::transaction(function () use ($request, $changeRequest) {
            $changeRequest = RegionProfileChangeRequest::query()
                ->with('region')
                ->whereKey($changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($changeRequest->status === RegionProfileChangeRequest::STATUS_PENDING, 422, 'La solicitud ya fue revisada.');

            $this->applyChanges($changeRequest->region, $changeRequest->proposed_changes ?? []);

            $changeRequest->forceFill([
                'status' => RegionProfileChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'review_notes' => $request->validated('review_notes'),
                'reviewed_at' => now(),
            ])->save();

            return $changeRequest;
        });

        $changeRequest->load(['region', 'requester:id,name,email,username', 'reviewer:id,name,email,username']);

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud aprobada y cambios aplicados correctamente.',
            'data' => $this->payload($changeRequest),
        ]);
    }

    public function reject(
        ReviewRegionProfileChangeRequest $request,
        RegionProfileChangeRequest $changeRequest
    ): JsonResponse {
        $this->ensureReviewerCanReview($request->user());
        abort_unless($changeRequest->status === RegionProfileChangeRequest::STATUS_PENDING, 422, 'La solicitud ya fue revisada.');
        $this->deletePendingBanner($changeRequest);

        $changeRequest->forceFill([
            'status' => RegionProfileChangeRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'review_notes' => $request->validated('review_notes'),
            'reviewed_at' => now(),
        ])->save();

        $changeRequest->load(['region', 'requester:id,name,email,username', 'reviewer:id,name,email,username']);

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud rechazada correctamente.',
            'data' => $this->payload($changeRequest),
        ]);
    }

    public function cancel(Request $request, RegionProfileChangeRequest $changeRequest): JsonResponse
    {
        $this->ensureChangeRequestIsVisibleForRegionalAdmin($request->user(), $changeRequest);

        abort_unless($changeRequest->status === RegionProfileChangeRequest::STATUS_PENDING, 422, 'Solo se pueden cancelar solicitudes pendientes.');
        abort_unless(
            (int) $changeRequest->requested_by === (int) $request->user()->id || ! $request->user()?->isRegionalAdmin(),
            403,
            'No puedes cancelar una solicitud creada por otro usuario.'
        );

        $this->deletePendingBanner($changeRequest);

        $changeRequest->forceFill([
            'status' => RegionProfileChangeRequest::STATUS_CANCELLED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => 'Solicitud cancelada correctamente.',
            'data' => $this->payload($changeRequest->fresh(['region', 'requester:id,name,email,username', 'reviewer:id,name,email,username'])),
        ]);
    }

    protected function applyChanges(Region $region, array $changes): void
    {
        $data = [];

        foreach (['description', 'banner_alt', 'translations'] as $field) {
            if (array_key_exists($field, $changes)) {
                $data[$field] = $changes[$field];
            }
        }

        if (data_get($changes, 'remove_banner')) {
            $this->deleteRegionBanner($region);
            $data['banner_disk'] = null;
            $data['banner_path'] = null;
        }

        if (data_get($changes, 'banner_path')) {
            $this->deleteRegionBanner($region);
            $data['banner_disk'] = data_get($changes, 'banner_disk', 'public') ?: 'public';
            $data['banner_path'] = data_get($changes, 'banner_path');
        }

        $region->update($data);
    }

    protected function payload(RegionProfileChangeRequest $changeRequest): array
    {
        return [
            'id' => $changeRequest->id,
            'region_id' => $changeRequest->region_id,
            'region' => $changeRequest->region ? [
                'id' => $changeRequest->region->id,
                'name' => $changeRequest->region->name,
                'slug' => $changeRequest->region->slug,
            ] : null,
            'status' => $changeRequest->status,
            'current_snapshot' => $changeRequest->current_snapshot,
            'proposed_changes' => $this->changesPayload($changeRequest->proposed_changes ?? []),
            'request_notes' => $changeRequest->request_notes,
            'review_notes' => $changeRequest->review_notes,
            'requested_by' => $this->userPayload($changeRequest->requester),
            'reviewed_by' => $this->userPayload($changeRequest->reviewer),
            'reviewed_at' => $changeRequest->reviewed_at?->toDateTimeString(),
            'created_at' => $changeRequest->created_at?->toDateTimeString(),
            'updated_at' => $changeRequest->updated_at?->toDateTimeString(),
        ];
    }

    protected function changesPayload(array $changes): array
    {
        if (isset($changes['banner_path'])) {
            $disk = $changes['banner_disk'] ?? 'public';
            $changes['banner_url'] = Storage::disk($disk ?: 'public')->url($changes['banner_path']);
        }

        return $changes;
    }

    protected function regionSnapshot(Region $region): array
    {
        return [
            'description' => $region->description,
            'banner_disk' => $region->banner_disk,
            'banner_path' => $region->banner_path,
            'banner_url' => $region->banner_url,
            'banner_alt' => $region->banner_alt,
            'translations' => $region->translations ?? [],
        ];
    }

    protected function userPayload(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
        ] : null;
    }

    protected function ensureRegionIsVisibleForRegionalAdmin(?User $user, Region $region): void
    {
        $regionId = $this->regionalAdminRegionId($user);

        if ($regionId === null) {
            return;
        }

        abort_unless((int) $region->id === $regionId, 404, 'Región no encontrada para tu usuario.');
    }

    protected function ensureChangeRequestIsVisibleForRegionalAdmin(?User $user, RegionProfileChangeRequest $changeRequest): void
    {
        $regionId = $this->regionalAdminRegionId($user);

        if ($regionId === null) {
            return;
        }

        abort_unless((int) $changeRequest->region_id === $regionId, 404, 'Solicitud no encontrada para tu usuario.');
    }

    protected function ensureReviewerCanReview(?User $user): void
    {
        $user?->loadMissing('role');

        abort_unless(
            $user && in_array($user->role?->name, ['admin', 'super_admin'], true),
            403,
            'Solo admin y super_admin pueden aprobar o rechazar solicitudes.'
        );
    }

    protected function deleteRegionBanner(Region $region): void
    {
        if ($region->banner_path && Storage::disk($region->banner_disk ?: 'public')->exists($region->banner_path)) {
            Storage::disk($region->banner_disk ?: 'public')->delete($region->banner_path);
        }
    }

    protected function deletePendingBanner(RegionProfileChangeRequest $changeRequest): void
    {
        $path = data_get($changeRequest->proposed_changes, 'banner_path');
        $disk = data_get($changeRequest->proposed_changes, 'banner_disk', 'public') ?: 'public';

        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
