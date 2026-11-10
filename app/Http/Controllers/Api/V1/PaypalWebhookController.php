<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaypalPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaypalWebhookController extends Controller
{
    public function __construct(
        protected PaypalPaymentService $paypalPaymentService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $headers = [
            'PAYPAL-AUTH-ALGO' => $request->header('PAYPAL-AUTH-ALGO'),
            'PAYPAL-CERT-URL' => $request->header('PAYPAL-CERT-URL'),
            'PAYPAL-TRANSMISSION-ID' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'PAYPAL-TRANSMISSION-SIG' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'PAYPAL-TRANSMISSION-TIME' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        $result = $this->paypalPaymentService->handleWebhook(
            payload: $request->getContent(),
            headers: $headers
        );

        return response()->json($result);
    }
}
