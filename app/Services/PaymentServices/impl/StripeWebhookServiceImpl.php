<?php

namespace App\Services\PaymentServices\impl;

use App\Enums\Notification\NotificationType;
use App\Models\Customer\Order;
use App\Models\Customer\Payment;
use App\Models\Customer\Refund;
use App\Services\CustomerServices\InvoiceService;
use App\Services\NotificationServices\NotificationService;
use App\Services\PaymentServices\StripeWebhookService;
use Illuminate\Support\Facades\Log;
use Stripe\Charge;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Illuminate\Support\Facades\DB;
use App\Models\Customer\CustomerCheckoutSession;
use App\Models\Owner\OwnerProduct;
use App\Models\Owner\OwnerPackage;
use App\Models\Customer\OrderItems;
use App\Models\Owner\OwnerNotification;
use App\Services\NotificationServices\impl\NotificationServiceImpl;
use App\Models\Customer\Cart;
use App\Models\Customer\CartItems;

class StripeWebhookServiceImpl implements StripeWebhookService
{
    public function __construct(
        private InvoiceService $invoiceService,
        private NotificationService $notificationService
    ) {}

    public function handle(string $rawPayload, string $sigHeader): void
    {
        $event = Webhook::constructEvent(
            $rawPayload,
            $sigHeader,
            (string) config('stripe.webhook_secret')
        );

        /** @var object $object */
        $object = $event->data->object;

        match ($event->type) {
            'payment_intent.succeeded'       => $this->onPaymentIntentSucceeded($object),
            'payment_intent.payment_failed'  => $this->onPaymentIntentFailed($object),
            'payment_intent.canceled'        => $this->onPaymentIntentCanceled($object),
            'payment_intent.requires_action' => null, // already returned to frontend sync
            'charge.refunded'                => $this->onChargeRefunded($object),
            'charge.refund.updated'          => $this->onChargeRefundUpdated($object),
            default                          => null,
        };
    }

    // -------------------------------------------------------------------------
    // payment_intent.succeeded
    // -------------------------------------------------------------------------
    private function onPaymentIntentSucceeded(PaymentIntent $intent): void
    {
        $payment = Payment::where('transaction_id', $intent->id)->first();

        if (!$payment) {
            Log::error('Payment not found for intent', ['intent' => $intent->id]);
            return;
        }

        // =========================
        // IDEMPOTENCY GUARD (VERY IMPORTANT)
        // =========================
        if ($payment->status === 'success') {
            return;
        }

        DB::beginTransaction();

        try {

            // =========================
            // MARK PAYMENT SUCCESS
            // =========================
            $payment->update([
                'status' => 'success',
                'paid_at' => now(),
                'stripe_charge_id' => $intent->latest_charge,
            ]);

            // =========================
            // LOAD SESSION
            // =========================
            $session = CustomerCheckoutSession::find($payment->checkout_session_id);

            if (!$session) {
                throw new \Exception('Checkout session not found');
            }

            // =========================
            // PREVENT DUPLICATE ORDERS (IMPORTANT FIX)
            // =========================
            $existingOrders = Order::where('checkout_session_id', $session->id)->exists();

            if ($existingOrders) {
                DB::commit();
                return;
            }

            $grouped = collect($session->items)->groupBy('owner_id');

            foreach ($grouped as $ownerId => $items) {

                $subtotal = collect($items)->sum('line_total');

                $deliveryFee =
                    $session->fulfillment_method === 'delivery' ? 2 : 0;

                $total = $subtotal + $deliveryFee;

                $order = Order::create([
                    'order_number' =>
                    'ORD-' . now()->format('YmdHis') . '-' . rand(1000, 9999),

                    'customer_id' => $payment->customer_id,
                    'owner_id' => $ownerId,
                    'checkout_session_id' => $session->id,
                    'payment_id' => $payment->id,

                    'status' => 'pending',
                    'payment_status' => 'paid',

                    'fulfillment_method' => $session->fulfillment_method,
                    'payment_method' => $session->payment_method,

                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,

                    'delivery_address' => $session->delivery_address,

                    'status_history' => json_encode([
                        [
                            // 'status' => 'confirmed',
                            'status' => 'pending',
                            'time' => now()->toDateTimeString(),
                        ]
                    ]),
                ]);

                foreach ($items as $item) {

                    $product = OwnerProduct::find($item['product_id']);
                    $package = OwnerPackage::find($item['package_id']);

                    OrderItems::create([
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
                            'description' => $product?->description,
                            'strength' => $product?->strength,
                            'form' => $product?->form,
                            'image' => $product?->main_image,
                        ]),
                    ]);
                }
            }

            // =========================
            // COMPLETE SESSION LAST
            // =========================
            $session->update([
                'status' => 'completed',
            ]);

            OwnerNotification::create([
                'owner_id' => $order->owner_id,

                'customer_id' => $order->customer_id,

                'order_id' => $order->id,

                'type' => 'new_order',

                'title' => 'New Order Received',

                'message' =>
                "You received a new order ({$order->order_number}) totaling \${$order->total}.",

                'channels' => [
                    'web',
                    'email',
                ],

                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_id' => $payment->id,
                    'total' => $order->total,
                    'payment_method' => $order->payment_method,
                    'fulfillment_method' => $order->fulfillment_method,
                ],
            ]);


            NotificationServiceImpl::owner(
                $order->owner,
                'new_order',
                [
                    'customer_id' => $order->customer_id,
                    'order_id'    => $order->id,

                    'title' => 'New Order Received',

                    'message' =>
                    "You received a new order ({$order->order_number}) totaling \${$order->total}.",

                    'channels' => ['web', 'email'],

                    'data' => [
                        'order_id'          => $order->id,
                        'order_number'      => $order->order_number,
                        'payment_id'        => $payment->id,
                        'total'             => $order->total,
                        'payment_method'    => $order->payment_method,
                        'fulfillment_method' => $order->fulfillment_method,
                    ],
                ]
            );

            $cart = Cart::query()
                ->where('customer_id',$order->customer_id)
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
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Webhook order creation failed', [
                'intent' => $intent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // payment_intent.payment_failed
    // -------------------------------------------------------------------------
    private function onPaymentIntentFailed(PaymentIntent $intent): void
    {
        $payment = Payment::query()
            ->where('transaction_id', $intent->id)
            ->first();

        if (!$payment || $payment->status === 'failed') {
            return;
        }

        $payment->update(['status' => 'failed']);

        $orderIds = $payment->order->pluck('id');

        Order::query()
            ->whereIn('id', $orderIds)
            ->update(['payment_status' => 'failed']);
    }

    // -------------------------------------------------------------------------
    // payment_intent.canceled
    // -------------------------------------------------------------------------
    // private function onPaymentIntentCanceled(PaymentIntent $intent): void
    // {
    //     $payment = Payment::query()
    //         ->where('transaction_id', $intent->id)
    //         ->first();

    //     if (!$payment) {
    //         return;
    //     }

    //     // Map Stripe canceled → our 'failed' status (no dedicated canceled in enum)
    //     if (!in_array($payment->status, ['success', 'failed'], true)) {
    //         $payment->update(['status' => 'failed']);

    //         Order::query()
    //             ->whereIn('id', $payment->order->pluck('id'))
    //             ->update(['payment_status' => 'failed']);



    //     }
    // }



    private function onPaymentIntentCanceled(
        PaymentIntent $intent
    ): void {

        $payment = Payment::query()
            ->where('transaction_id', $intent->id)
            ->first();

        if (!$payment) {
            return;
        }

        // prevent duplicate processing
        if (
            in_array(
                $payment->status,
                ['success', 'failed'],
                true
            )
        ) {
            return;
        }

        // =========================
        // UPDATE PAYMENT
        // =========================
        $payment->update([
            'status' => 'failed',
        ]);

        // =========================
        // GET ORDER
        // =========================
        $order = $payment->order()->first();

        if (!$order) {
            return;
        }

        // =========================
        // UPDATE ORDER
        // =========================
        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
        ]);

        // =========================
        // OWNER NOTIFICATION
        // =========================
        OwnerNotification::create([
            'owner_id' => $order->owner_id,

            'customer_id' => $order->customer_id,

            'order_id' => $order->id,

            'type' => 'payment_cancelled',

            'title' => 'Order Payment Cancelled',

            'message' =>
            "Payment for order {$order->order_number} was cancelled or failed. The order has been cancelled.",

            'channels' => [
                'web',
                'email',
            ],

            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'transaction_id' => $intent->id,
                'payment_status' => 'failed',
                'order_status' => 'cancelled',
            ],
        ]);
    }

    // ----------------
    // charge.refunded
    // ----------------
    private function onChargeRefunded(Charge $charge): void
    {
        // Find our payment via the PaymentIntent ID attached to the charge
        $paymentIntentId = $charge->payment_intent;

        if (!$paymentIntentId) {
            return;
        }

        $payment = Payment::query()
            ->where('transaction_id', $paymentIntentId)
            ->first();

        if (!$payment) {
            return;
        }

        // Move all owner-verified refunds for this payment to 'refunded'
        Refund::query()
            ->where('payment_id', $payment->id)
            ->where('status', 'verified')
            ->update(['status' => 'refunded']);
    }

    // -----------------------
    // charge.refund.updated
    // -----------------------
    private function onChargeRefundUpdated(\Stripe\Refund $refund): void
    {
        if ($refund->status !== 'failed') {
            return;
        }

        // If we stored our refund ID in Stripe metadata, cancel it
        $ourRefundId = $refund->metadata['our_refund_id'] ?? null;

        if ($ourRefundId) {
            Refund::query()
                ->where('id', (int) $ourRefundId)
                ->whereIn('status', ['verified', 'returning'])
                ->update(['status' => 'canceled']);
        } else {
            Log::warning('Stripe refund failed but no our_refund_id in metadata', [
                'stripe_refund_id' => $refund->id,
            ]);
        }
    }
}
