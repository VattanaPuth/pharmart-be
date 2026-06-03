<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer\ProductReview;

class AdminProductReviewController extends Controller
{
    public function index()
    {
        return ProductReview::with(['product.ownerSetting', 'customerInfo'])
            ->latest()
            ->paginate(20)
            ->through(function ($r) {

                return [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'comment' => $r->review,

                    // product
                    'product' => $r->product->product_name  ?? 'Unknown Product',

                    // ⭐ PHARMACY NAME (FROM owner_setting)
                    'pharmacy' => $r->product->ownerSetting->pharmacy_name ?? 'Unknown Pharmacy',

                    // ⭐ CUSTOMER NAME (FROM customer_information)
                    'user' => $r->customerInfo->customer_name ?? 'Customer #' . $r->customer_id,

                    'order' => 'Order #' . $r->order_id,
                    'date' => $r->created_at->format('Y-m-d'),
                ];
            });
    }

    public function show(ProductReview $review)
    {
        $review->load(['product.ownerSetting', 'customerInfo']);

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->review,

            'product' => $review->product->product_name ?? 'Unknown Product',
            'pharmacy' => $review->product->ownerSetting->pharmacy_name ?? 'Unknown Pharmacy',

            'user' => $review->customerInfo->customer_name ?? 'Customer #' . $review->customer_id,

            'order' => 'Order #' . $review->order_id,
            'date' => $review->created_at->format('Y-m-d'),
        ];
    }

    public function destroy(ProductReview $review)
    {
        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }

    public function summary()
    {
        $reviews = ProductReview::all();

        $total = $reviews->count();
        $avg = $total ? round($reviews->avg('rating'), 1) : 0;

        $positive = $reviews->where('rating', '>=', 4)->count();
        $negative = $reviews->where('rating', '<=', 2)->count();

        $distribution = [
            5 => $reviews->where('rating', 5)->count(),
            4 => $reviews->where('rating', 4)->count(),
            3 => $reviews->where('rating', 3)->count(),
            2 => $reviews->where('rating', 2)->count(),
            1 => $reviews->where('rating', 1)->count(),
        ];

        return response()->json([
            'average' => $avg,
            'total' => $total,
            'positive' => $positive,
            'negative' => $negative,
            'distribution' => $distribution,
        ]);
    }
}
