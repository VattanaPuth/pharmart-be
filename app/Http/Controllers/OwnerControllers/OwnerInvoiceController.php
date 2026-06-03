<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use App\Services\OwnerServices\OwnerInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OwnerInvoiceController extends Controller
{
    public function __construct(private OwnerInvoiceService $ownerInvoiceService) {}

    private function currentOwnerId(Request $request): ?int
    {
        return $request->user()?->owner?->id;
    }

    public function generate(Request $request, int $orderId): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        try {
            $invoice = $this->ownerInvoiceService->generateInvoice($ownerId, $orderId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice generated successfully',
            'data'    => $invoice,
        ], 201);
    }
}
