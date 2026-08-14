<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesRegionalProductAccess;
use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Services\Payments\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionStripeConnectController extends Controller
{
    use AuthorizesRegionalProductAccess;

    public function __construct(protected StripeConnectService $stripeConnectService)
    {
    }

    public function show(Request $request, Region $region): JsonResponse
    {
        $this->ensureRegionIsVisibleForRegionalAdmin($request->user(), $region);

        return response()->json([
            'ok' => true,
            'message' => 'Estado de Stripe Connect obtenido correctamente.',
            'data' => $this->stripeConnectService->statusPayload($region),
        ]);
    }

    public function onboardingLink(Request $request, Region $region): JsonResponse
    {
        $this->ensureRegionIsVisibleForRegionalAdmin($request->user(), $region);

        $result = $this->stripeConnectService->createOnboardingLink($region, $request->user()?->email);

        return response()->json([
            'ok' => true,
            'message' => 'Link de onboarding Stripe Connect generado correctamente.',
            'data' => $result,
        ]);
    }

    public function sync(Request $request, Region $region): JsonResponse
    {
        $this->ensureRegionIsVisibleForRegionalAdmin($request->user(), $region);
        $region = $this->stripeConnectService->syncAccount($region);

        return response()->json([
            'ok' => true,
            'message' => 'Estado de Stripe Connect sincronizado correctamente.',
            'data' => $this->stripeConnectService->statusPayload($region),
        ]);
    }

    protected function ensureRegionIsVisibleForRegionalAdmin($user, Region $region): void
    {
        $regionId = $this->regionalAdminRegionId($user);

        if ($regionId === null) {
            return;
        }

        abort_unless((int) $region->id === $regionId, 404, 'Región no encontrada para tu usuario.');
    }
}
