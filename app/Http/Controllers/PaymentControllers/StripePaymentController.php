<?php

namespace App\Http\Controllers\PaymentControllers;

use App\Http\Controllers\Controller;
use App\Models\Customer\Payment;
use App\Models\Customer\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePaymentController extends Controller
{
    private function stripe(): StripeClient
    {
        return new StripeClient((string) config('stripe.secret'));
    }

    private function currentCustomerId(Request $request): ?int
    {
        return $request->user()?->customer?->id;
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'   => 'required|numeric|min:0.5',
            'currency' => 'nullable|string|size:3',
            'checkout_session_id' => 'required|integer',
        ]);

        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $currency = strtolower($validated['currency'] ?? (string) config('stripe.currency', 'usd'));
        $amount   = round((float) $validated['amount'], 2);

        try {
            $intent = $this->stripe()->paymentIntents->create([
                'amount'                     => (int) round($amount * 100),
                'currency'                   => $currency,
                'automatic_payment_methods'  => [
                    'enabled'         => true,

                ],

                'metadata' => [
                    'checkout_session_id' => $validated['checkout_session_id'],
                    'customer_id' => $customerId,
                ],

            ]);
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Stripe error: ' . $e->getMessage()], 422);
        }

        Payment::query()->create([
            'customer_id'      => $customerId,
            'payment_provider' => 'stripe',
            'transaction_id'   => $intent->id,
            'amount'           => $amount,
            'currency'         => $currency,
            'status'           => 'pending',
            'checkout_session_id' => $validated['checkout_session_id'],
        ]);

        return response()->json([
            'payment_intent_id' => $intent->id,
            'client_secret'     => $intent->client_secret,
        ]);
    }

public function confirmPayment(Request $request): JsonResponse
{
    $validated = $request->validate([
        'payment_intent_id' => 'required|string',
    ]);

    $customerId = $this->currentCustomerId($request);

    if (!$customerId) {
        return response()->json(['message' => 'Customer profile not found'], 404);
    }

    $payment = Payment::where('transaction_id', $validated['payment_intent_id'])
        ->where('customer_id', $customerId)
        ->first();

    if (!$payment) {
        return response()->json(['message' => 'Payment not found'], 404);
    }

    try {
        $intent = $this->stripe()->paymentIntents->retrieve(
            $validated['payment_intent_id']
        );
    } catch (ApiErrorException $e) {
        return response()->json([
            'message' => 'Stripe error: ' . $e->getMessage()
        ], 422);
    }

    // ✅ NEVER block flow — just map status
    $dbStatus = match ($intent->status) {
        'succeeded' => 'success',
        'processing' => 'pending',
        'requires_payment_method' => 'failed',
        'requires_action' => 'pending',
        'canceled' => 'failed',
        default => 'failed',
    };

    DB::transaction(function () use ($payment, $dbStatus) {

        $payment->update([
            'status' => $dbStatus,
            'paid_at' => $dbStatus === 'success' ? now() : null,
        ]);

        if ($dbStatus === 'success') {
            Order::where('customer_id', $payment->customer_id)
                ->where('checkout_session_id', $payment->checkout_session_id)
                ->whereNull('payment_id')
                ->update([
                    'payment_id' => $payment->id,
                    'payment_status' => 'paid',
                ]);
        }
    });

    return response()->json([
        'status' => $dbStatus,
        'stripe_status' => $intent->status,
    ]);
}

    public function getPaymentStatus(Request $request, string $paymentIntentId): JsonResponse
    {
        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $payment = Payment::query()
            ->where('transaction_id', $paymentIntentId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        try {
            $intent = $this->stripe()->paymentIntents->retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Stripe error: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'stripe_status'  => $intent->status,
            'payment'        => $payment->fresh('orders'),
        ]);
    }

    public function createCustomer(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $customer = $this->stripe()->customers->create([
                'email' => $user->email ?? null,
                'metadata' => ['customer_id' => $user->id],
            ]);
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Stripe error: ' . $e->getMessage()], 422);
        }

        return response()->json(['stripe_customer_id' => $customer->id]);
    }

    public function createRefund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_intent_id' => 'required|string',
            'amount'            => 'nullable|numeric|min:0.01',
        ]);

        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $payment = Payment::query()
            ->where('transaction_id', $validated['payment_intent_id'])
            ->where('customer_id', $customerId)
            ->whereIn('status', ['success', 'pending'])
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Paid payment not found'], 404);
        }

        try {
            $intent = $this->stripe()->paymentIntents->retrieve($validated['payment_intent_id']);
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Stripe error: ' . $e->getMessage()], 422);
        }

        if ($intent->status !== 'succeeded') {
            return response()->json(['message' => 'Payment has not been completed on Stripe'], 422);
        }

        if ($payment->status !== 'success') {
            $payment->update(['status' => 'success', 'paid_at' => now()]);
        }

        $params = ['payment_intent' => $validated['payment_intent_id']];

        if (!empty($validated['amount'])) {
            $params['amount'] = (int) round((float) $validated['amount'] * 100);
        }

        try {
            $refund = $this->stripe()->refunds->create($params);
        } catch (ApiErrorException $e) {
            return response()->json(['message' => 'Stripe error: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'refund_id' => $refund->id,
            'status'    => $refund->status,
        ]);
    }
}
