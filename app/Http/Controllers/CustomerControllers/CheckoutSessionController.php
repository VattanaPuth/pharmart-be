<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\CheckoutSessionServiceInterface;
use Illuminate\Http\Request;
use App\Models\Owner\OwnerNotification;
use RuntimeException;

class CheckoutSessionController extends Controller
{
    public function __construct(
        private CheckoutSessionServiceInterface $checkoutService
    ) {}

    // ---------------- CREATE SESSION ----------------
    public function create(Request $request)
    {
        $validated = $request->validate([
            'store_ids' => 'required|array|min:1',
            'store_ids.*' => 'integer'
        ]);

        $customer = $request->user()?->customer;

        if (!$customer) {
            return response()->json([
                'message' => 'Customer profile not found'
            ], 404);
        }

        $session = $this->checkoutService->createSession(
            $customer->id,
            $validated['store_ids']
        );

        return response()->json([
            'message' => 'Checkout session created',
            'data' => $session
        ]);
    }

    // ---------------- GET SESSION ----------------
    public function show(Request $request, int $sessionId)
    {
        try {
            $customerId = $request->user()->customer->id;

            $session = $this->checkoutService->getSession($customerId, $sessionId);

            return response()->json([
                'data' => $session
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    // ---------------- UPDATE SESSION ----------------
    public function update(Request $request, int $sessionId)
    {
        $validated = $request->validate([
            'fulfillment_method' => 'nullable|in:pickup,delivery',
            'payment_method' => 'nullable|in:online,pay_at_shop',
            'delivery_address' => 'nullable|string|max:255',
        ]);

        try {
            $customerId = $request->user()->customer->id;

            $session = $this->checkoutService->updateSession(
                $customerId,
                $sessionId,
                $validated
            );

            return response()->json([
                'message' => 'Session updated',
                'data' => $session
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    // ---------------- CONFIRM CHECKOUT ----------------
    public function confirm(Request $request, int $sessionId)
    {
        try {

            $customerId = $request->user()->customer->id;

            $result = $this->checkoutService->confirmSession(
                $customerId,
                $sessionId,
            );

            // =========================
            // ONLINE PAYMENT
            // =========================
            if (
                isset($result['payment_method']) &&
                $result['payment_method'] === 'online'
            ) {

                return response()->json($result);
            }

            
            // =========================
            // PAY AT SHOP
            // =========================
            return response()->json([
                'message' => 'Checkout successful',
                // 'data' => collect($result)->map(function ($order) {
                //     return $order->load('items');
                // }),
            ]);

        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
