<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use App\Models\Customer\Order;
use Carbon\Carbon;

class OwnerOrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    private function currentOwnerId(Request $request): ?int
    {
        return $request->user()?->owner?->id;
    }


    private function respond(Request $request, int $orderId, string $action): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        try {
            $order = $this->orderService->{$action}($ownerId, $orderId);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong.'
            ], 500);
        }

        $messages = [
            'confirmOrder' => 'Order confirmed',
            'progressOrder' => match ($order->fulfillment_method) {
                'pickup' => 'Order ready for pickup',
                'delivery' => 'Order out for delivery',
            },
            'completeOrder' => 'Completion request submitted',
        ];

        return response()->json([
            'message' => $messages[$action] ?? 'Order updated',
            'data'    => $order,
        ]);
    }

    public function confirm(Request $request, int $orderId): JsonResponse
    {
        return $this->respond($request, $orderId, 'confirmOrder');
    }


    public function ready(Request $request, int $orderId): JsonResponse
    {
        return $this->respond($request, $orderId, 'progressOrder');
    }

    public function complete(Request $request, int $orderId): JsonResponse
    {
        return $this->respond($request, $orderId, 'completeOrder');
    }


    public function decline(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json([
                'message' => 'Owner profile not found'
            ], 404);
        }

        try {
            $order = $this->orderService->declineOrder(
                $ownerId,
                $orderId,
                $request->reason
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong.'
            ], 500);
        }

        return response()->json([
            'message' => 'Order declined',
            'data' => $order,
        ]);
    }



    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $perPage = $request->get('per_page', 10);

        $orders = Order::with(['customer', 'items', 'owner.setting'])
            ->whereHas('owner', function ($q) use ($ownerId) {
                $q->where('id', $ownerId);
            })

            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })

            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {

                    $search = str_replace('ORD-', '', $request->search);

                    $query->where('id', 'like', "%{$search}%");
                });
            })

            ->when($request->from_date, function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->from_date);
            })

            ->when($request->to_date, function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->to_date);
            })

            ->latest()
            ->paginate($perPage);

        // =========================
        // TRANSFORM (FRONTEND READY)
        // =========================
        $orders->getCollection()->transform(function ($order) {

            return [
                'id' => $order->id,
                'order_id' => 'ID' . $order->id,
                'order_number'=> $order->order_number,
                'status' => ucfirst($order->status),
                'created_at'=>$order->created_at,
           


                // ✅ CUSTOMER (flattened + safe)
                'customer' => [
                    'id' => $order->customer?->id,
                    'name' =>
                    $order->customer?->information?->customer_name
                        ?? 'Unknown',

                    'phone' =>
                    $order->customer?->information?->phone_number,

                    'email' =>
                    $order->customer?->information?->email,
                ],

                // ✅ PHARMACY
                'pharmacy' => $order->owner->setting->pharmacy_name ?? 'Unknown Pharmacy',

                // ✅ ITEMS (structured + display ready)
                'items' => $order->items->map(function ($item) {
                    return [
                        'name' => $item->product_name,
                        'image' => $item->product_image,
                        'package_name' => $item->package_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'display_product_name' => $item->product_name,
                        'line_total' => $item->line_total
                    ];
                }),

                // ✅ SIMPLE STRING FOR UI (NO FRONTEND MAPPING)
                'items_text' => $order->items
                    ->map(fn($i) => $i->product_name . " x" . $i->quantity)
                    ->implode(', '),

                // ✅ STANDARDIZED FIELD NAMES
                'delivery_fee' => (float)  $order->delivery_fee,
                'amount' => (float) $order->subtotal,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'refund_status'=> $order->refund?->status,
                'delivery' => ucfirst($order->fulfillment_method),
                'fulfillment_method' => $order->fulfillment_method,
                'delivery_address' => $order->delivery_address,
                
                // 'refund_status'=> $order->refund->status,

                'date' => $order->created_at->format('Y-m-d'),
                'decline_reason' => $order->decline_reason,

                'history' => [
                    'confirmed_at' => $order->confirmed_at,
                    'ready_at' => $order->ready_at,
                    'completed_at' => $order->completed_at,
                    'cancelled_at' => $order->cancelled_at,
                    'pharmacy_completed_at' => $order->pharmacy_completed_at,
                    'customer_completed_at' => $order->customer_completed_at,

                ],
                'can_complete' =>
                $order->status === 'ready'
                    || $order->status === 'delivering',
                'awaiting_customer_confirmation' =>
                $order->pharmacy_completed_at &&
                    !$order->customer_completed_at,

            ];
        });

        return response()->json($orders);
    }


    public function show(Request $request, int $orderId): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $order = Order::with(['customer.information', 'items', 'owner.setting'])
            ->where('id', $orderId)
            ->whereHas('owner', function ($q) use ($ownerId) {
                $q->where('id', $ownerId);
            })
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_id' => 'ORD-' . $order->id,
                'order_number'=>$order->order_number,
                'status' => ucfirst($order->status),
                'created_at'=>$order->created_at,
                'invoice_created_at'=>Carbon::now(),

                'customer' => [
                    'id' => $order->customer?->id,
                    'name' => $order->customer?->information?->customer_name ?? 'Unknown',
                    'phone' => $order->customer?->information?->phone_number,
                    'email' => $order->customer?->information?->email,
                ],

                'pharmacy' => $order->owner->setting->pharmacy_name ?? 'Unknown Pharmacy',
                'delivery_fee' => (float) $order->delivery_fee,
                'amount' => (float) $order->subtotal,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'delivery' => ucfirst($order->fulfillment_method),
                'fulfillment_method' => $order->fulfillment_method,
                'delivery_address' => $order->delivery_address,
                'decline_reason' => $order->decline_reason,

                'items' => $order->items->map(function ($item) {
                    return [
                        'name' => $item->product_name,
                        'image' => $item->product_image,
                        'package_name' => $item->package_name,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'line_total' => (float) $item->line_total,
                    ];
                }),

                'history' => [
                    'confirmed_at' => $order->confirmed_at,
                    'ready_at' => $order->ready_at,
                    'completed_at' => $order->completed_at,
                    'cancelled_at' => $order->cancelled_at,
                    'pharmacy_completed_at' => $order->pharmacy_completed_at,
                    'customer_completed_at' => $order->customer_completed_at,
                ],

                'can_complete' =>
                $order->status === 'ready' ||
                    $order->status === 'delivering',

                'awaiting_customer_confirmation' =>
                $order->pharmacy_completed_at &&
                    !$order->customer_completed_at,
            ]
        ]);
    }
}
