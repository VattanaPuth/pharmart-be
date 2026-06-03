<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\Cart;
use App\Models\Customer\CartItems;
use App\Models\Customer\CustomerCheckoutSession;
use App\Models\Customer\Payment;
use App\Models\Owner\Owner;
use App\Services\CustomerServices\CheckoutSessionServiceInterface;
use Illuminate\Support\Facades\DB;
use App\Models\Owner\OwnerNotification;
use RuntimeException;
use App\Services\NotificationServices\impl\NotificationServiceImpl;

class CheckoutSessionServiceImpl implements CheckoutSessionServiceInterface
{
    public function createSession(int $customerId, array $storeIds): CustomerCheckoutSession
    {
        $cart = Cart::where('customer_id', $customerId)
            ->where('status', 'active')
            ->with(['items.product', 'items.package'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new RuntimeException('Cart is empty');
        }

        // filter ONLY selected stores
        $items = $cart->items
            ->filter(function ($item) use ($storeIds) {
                return in_array($item->product->owner_id, $storeIds);
            })
            ->map(function ($item) {
                $owner = Owner::with('setting')
                    ->find($item->product->owner_id);
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name,
                    'product_image' => $item->product->main_image,
                    'owner_id' => $item->owner_id,
                    'owner_name' => $owner?->setting?->pharmacy_name ?? 'Unknown Pharmacy',
                    'package_id' => $item->package_id,
                    'package_name' => $item->package?->package_name,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                ];
            })
            ->values();

        if ($items->isEmpty()) {
            throw new RuntimeException('No items found for selected stores');
        }

        $subtotal = $items->sum('line_total');



        return CustomerCheckoutSession::create([
            'customer_id' => $customerId,
            'items' => $items,
            'subtotal' => $subtotal,
            'status' => 'active',
            'expires_at' => now()->addMinutes(30),
        ]);
    }


    public function getSession(int $customerId, int $sessionId): CustomerCheckoutSession
    {
        $session = CustomerCheckoutSession::where('id', $sessionId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if ($session->expires_at && now()->gt($session->expires_at)) {
            $session->update(['status' => 'expired']);
            throw new RuntimeException('Session expired');
        }

        return $session;
    }

    public function updateSession(int $customerId, int $sessionId, array $data,): CustomerCheckoutSession
    {
        $session = CustomerCheckoutSession::where('id', $sessionId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $session->update([
            'fulfillment_method' => $data['fulfillment_method'] ?? $session->fulfillment_method,
            'payment_method' => $data['payment_method'] ?? $session->payment_method,
            'delivery_address' => $data['delivery_address'] ?? $session->delivery_address,
        ]);

        return $session;
    }



    public function confirmSession(int $customerId, int $sessionId): array
    {
        $session = CustomerCheckoutSession::where('id', $sessionId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        if ($session->status === 'completed') {
            return [
                'message' => 'Order already completed',
                'status' => 'already_completed',

            ];
        }

        if ($session->expires_at && now()->gt($session->expires_at)) {
            $session->update(['status' => 'expired']);
            throw new RuntimeException('Session expired');
        }

        if ($session->status !== 'active') {
            throw new RuntimeException('Invalid session');
        }

        if (
            !$session->fulfillment_method ||
            !$session->payment_method
        ) {
            throw new RuntimeException('Incomplete checkout');
        }

        // ==================================================
        // ONLINE STRIPE
        // ==================================================
        if ($session->payment_method === 'online') {

            $subtotal = collect($session->items)->sum('line_total');

            $deliveryFee = $session->fulfillment_method === 'delivery' ? 2 : 0;

            $total = $subtotal + $deliveryFee;

            // =========================
            // 🔥 PREVENT DUPLICATE PAYMENT INTENT
            // =========================
            $existingPayment = Payment::where('checkout_session_id', $session->id)
                ->where('payment_provider', 'stripe')
                ->whereNotNull('transaction_id')
                ->first();

            if ($existingPayment) {

                $stripe = new \Stripe\StripeClient(config('stripe.secret'));

                $intent = $stripe->paymentIntents->retrieve(
                    $existingPayment->transaction_id
                );

                return [
                    'payment_method' => 'online',
                    'payment_id' => $existingPayment->id,
                    'orders' => [],
                    'client_secret' => $intent->client_secret,

                ];
            }

            // =========================
            // CREATE NEW INTENT ONLY ONCE
            // =========================
            $stripe = new \Stripe\StripeClient(config('stripe.secret'));

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => (int) round($total * 100),
                'currency' => 'usd',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'customer_id' => $customerId,
                    'checkout_session_id' => $session->id,
                ],
            ]);

            $payment = Payment::create([
                'customer_id' => $customerId,
                'payment_provider' => 'stripe',
                'transaction_id' => $paymentIntent->id,
                'amount' => $total,
                'currency' => 'usd',
                'status' => 'pending',
                'checkout_session_id' => $session->id,
            ]);

            return [
                'payment_method' => 'online',
                'payment_id' => $payment->id,
                'client_secret' => $paymentIntent->client_secret,
            ];
        }

        // ==================================================
        // PAY AT SHOP
        // ==================================================
        DB::beginTransaction();

        try {

            $grouped = collect($session->items)
                ->groupBy('owner_id');

            $orders = [];

            $subtotal = collect($session->items)
                ->sum('line_total');

            $deliveryFee =
                $session->fulfillment_method === 'delivery'
                ? 2
                : 0;

            $total = $subtotal + $deliveryFee;

            $payment = Payment::create([
                'customer_id' => $customerId,
                'payment_provider' => 'cash',
                'transaction_id' => null,
                'amount' => $total,
                'currency' => 'usd',
                'status' => 'pending',
                'checkout_session_id' => $session->id,
            ]);

            foreach ($grouped as $ownerId => $items) {

                $ownerSubtotal = $items->sum('line_total');

                $ownerDeliveryFee =
                    $session->fulfillment_method === 'delivery'
                    ? 2
                    : 0;

                $ownerTotal =
                    $ownerSubtotal + $ownerDeliveryFee;

                $order = \App\Models\Customer\Order::create([
                    'order_number' =>
                    'ORD-' .
                        now()->format('YmdHis') .
                        '-' .
                        rand(1000, 9999),

                    'customer_id' => $customerId,
                    'owner_id' => $ownerId,
                    'checkout_session_id' => $session->id,
                    'payment_id' => $payment->id,
                    'status' => 'pending',
                    'payment_method' => 'pay_at_shop',
                    'payment_status' => 'pending',
                    'fulfillment_method' => $session->fulfillment_method,
                    'subtotal' => $ownerSubtotal,
                    'delivery_fee' => $ownerDeliveryFee,
                    'total' => $ownerTotal,
                    'delivery_address' => $session->delivery_address,
                    'status_history' => json_encode([
                        [
                            'status' => 'pending',
                            'time' => now()->toDateTimeString(),
                        ]
                    ]),
                ]);

                OwnerNotification::create([
                    'owner_id' => $ownerId,
                    'customer_id' => $customerId,
                    'order_id' => $order->id,
                    'type' => 'new_order',
                    'title' => 'New Order Received',
                    'message' =>
                    "A new order {$order->order_number} has been placed.",
                    'channels' => ['web'],
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_id' => $customerId,
                        'status' => 'pending',
                        'total' => $ownerTotal,
                        'fulfillment_method' => $session->fulfillment_method,
                    ],
                ]);

                $owner = \App\Models\Owner\Owner::find($ownerId);

                NotificationServiceImpl::owner(
                    $owner,
                    'new_order',
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_id' => $customerId,
                        'title' => 'Pharmart: New Order Received',
                        'message' => "A new order number {$order->order_number} | order ID#{$order->id} has been placed.",
                        'total' => $order->total,
                        'mail' => true,
                    ]
                );

                foreach ($items as $item) {

                    $product =
                        \App\Models\Owner\OwnerProduct::find(
                            $item['product_id']
                        );

                    $package =
                        \App\Models\Owner\OwnerPackage::find(
                            $item['package_id']
                        );

                    \App\Models\Customer\OrderItems::create([
                        'order_id' => $order->id,

                        'product_id' => $product?->id,
                        'product_sku' => $product?->sku,

                        'owner_id' => $item['owner_id'],

                        'product_name' => $product?->product_name,

                        'product_image' => $product?->main_image,

                        'unit_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'line_total' => $item['line_total'],

                        'package_id' => $package?->id,
                        'package_name' => $package?->package_name,
                        'product_snapshot' => json_encode([
                            'sku' => $product?->sku,
                            'name' => $product?->product_name,
                            'description' =>
                            $product?->description,
                            'strength' =>
                            $product?->strength,
                            'form' =>
                            $product?->form,
                            'image' =>
                            $product?->main_image,
                        ]),
                    ]);
                }

                $orders[] = $order;
            }

            $session->update([
                'status' => 'completed'
            ]);

            // =========================
            // REMOVE PURCHASED ITEMS FROM CART
            // =========================
            $cart = Cart::query()
                ->where('customer_id', $customerId)
                ->where('status', 'active')
                ->first();

            if ($cart) {

                $productIds = collect($session->items)
                    ->pluck('product_id')
                    ->unique()
                    ->values();

                CartItems::query()
                    ->where('cart_id', $cart->id)
                    ->whereIn('product_id', $productIds)
                    ->delete();
            }

            DB::commit();

            return $orders;
        } catch (\Exception $e) {

            DB::rollBack();

            throw new RuntimeException(
                'Checkout failed: ' . $e->getMessage()
            );
        }
    }

    public function attachPaymentToOrders(int $customerId, int $paymentId): void
    {
        \App\Models\Customer\Order::where('customer_id', $customerId)
            ->whereNull('payment_id')
            ->update([
                'payment_id' => $paymentId,
                'payment_status' => 'paid',
            ]);
    }
}
