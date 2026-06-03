<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;

use App\Models\Customer\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerReviewController extends Controller
{
    // =========================
    // REVIEW LIST
    // =========================
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json([
                'message' => 'Owner profile not found'
            ], 404);
        }

        $orders = Order::with([
            'customer.information',
            'items',
            'reviews'
        ])
            ->whereHas('owner', function ($q) use ($ownerId) {
                $q->where('id', $ownerId);
            })

            // ONLY ORDERS WITH REVIEWS
            ->whereHas('reviews')

            ->latest()
            ->get();

        $data = $orders->map(function ($order) {

            // =========================
            // ITEMS + REVIEW
            // =========================
            $items = $order->items->map(function ($item) use ($order) {

                $review = $order->reviews
                    ->firstWhere('product_id', $item->product_id);

                return [
                    'product_id' => $item->product_id,

                    'name' => $item->product_name,

                    'image' => $item->product_image
                        ?  $item->product_image
                        : null,

                    'package_name' => $item->package_name,

                    'quantity' => $item->quantity,

                    'rating' => $review?->rating,

                    'review' => $review?->review,

                    'reviewed_at' => $review?->created_at?->format('Y-m-d H:i'),
                ];
            });

            // =========================
            // AVERAGE RATING
            // =========================
            $averageRating = round(
                collect($items)
                    ->pluck('rating')
                    ->filter()
                    ->avg(),
                1
            );

            return [
                'id' => $order->id,

                'order_id' =>   ''.$order->id,
                'order_number'=> $order->order_number,

                'status' => ucfirst($order->status),

                'date' => $order->created_at->format('Y-m-d'),

                'customer' => [
                    'id' => $order->customer?->id,

                    'name' =>
                    $order->customer?->information?->customer_name
                        ?? 'Unknown',
                ],

                'average_rating' => $averageRating,

                'total_reviews' => collect($items)
                    ->pluck('rating')
                    ->filter()
                    ->count(),

                'items' => $items,
            ];
        });

        return response()->json([
            'data' => $data
        ]);
    }

    // =========================
    // SINGLE ORDER REVIEWS
    // =========================
    public function show(
        Request $request,
        int $orderId
    ): JsonResponse {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json([
                'message' => 'Owner profile not found'
            ], 404);
        }

        $order = Order::with([
            'customer.information',
            'items',
            'reviews'
        ])
            ->where('id', $orderId)

            ->whereHas('owner', function ($q) use ($ownerId) {
                $q->where('id', $ownerId);
            })

            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        // =========================
        // ITEMS + REVIEWS
        // =========================
        $items = $order->items->map(function ($item) use ($order) {

            $review = $order->reviews
                ->firstWhere('product_id', $item->product_id);

            return [
                'product_id' => $item->product_id,

                'name' => $item->product_name,

                'image' => $item->product_image,

                'package_name' => $item->package_name,

                'quantity' => $item->quantity,

                'rating' => $review?->rating,

                'review' => $review?->review,

                'reviewed_at' => $review?->created_at?->format('Y-m-d H:i'),
            ];
        });

        // =========================
        // AVERAGE
        // =========================
        $averageRating = round(
            collect($items)
                ->pluck('rating')
                ->filter()
                ->avg(),
            1
        );

        return response()->json([
            'data' => [

                'id' => $order->id,

                'order_number' => $order->order_number,

                'status' => ucfirst($order->status),

                'date' => $order->created_at->format('Y-m-d'),

                'customer' => [
                    'id' => $order->customer?->id,

                    'name' =>
                    $order->customer?->information?->customer_name
                        ?? 'Unknown',

                    'email' =>
                    $order->customer?->information?->email,

                    'phone' =>
                    $order->customer?->information?->phone_number,
                ],

                'average_rating' => $averageRating,

                'total_reviews' => collect($items)
                    ->pluck('rating')
                    ->filter()
                    ->count(),

                'items' => $items,
            ]
        ]);
    }

    // =========================
    // OWNER ID
    // =========================
    private function currentOwnerId(Request $request): ?int
    {
        return $request->user()?->owner?->id;
    }
}
