<?php

namespace App\Services\OwnerServices\impl;

use App\Models\Customer\Order;
use App\Models\Owner\OwnerPackage;
use App\Models\Owner\OwnerProduct;
use App\Models\Customer\ProductReview;
use App\Services\OwnerServices\OwnerDashboardService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\Customer\Refund;

class OwnerDashboardServiceImpl implements OwnerDashboardService
{
    /** Order statuses that count as valid revenue */
    private const REVENUE_STATUSES = ['pending', 'confirmed', 'ready', 'completed'];

    // -------------------------------------------------------------------------
    // Summary Cards
    // -------------------------------------------------------------------------
    public function getSummary(int $ownerId): array
    {
        $totalProducts = OwnerProduct::query()
            ->where('owner_id', $ownerId)
            ->where('status', true)
            ->count();

        $pendingOrders = Order::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'pending')
            ->count();

        $totalOrders = Order::query()
            ->where('owner_id', $ownerId)
            ->count();

        // Gross revenue from successful orders
        $grossRevenue = Order::query()
            ->where('owner_id', $ownerId)
            ->where('payment_status', 'paid')
            ->whereIn('status', self::REVENUE_STATUSES)
            ->sum('subtotal');

        // Refunded amount
        $refundedAmount = Refund::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'refunded')
            ->sum('refund_amount');

        // Net revenue after refunds
        $netRevenue = $grossRevenue - $refundedAmount;

        // Total refunded orders
        $totalRefunds = Refund::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'refunded')
            ->count();

        return [
            'total_products'   => $totalProducts,
            'pending_orders'   => $pendingOrders,
            'total_orders'     => $totalOrders,

            // NEW
            'gross_revenue'    => round((float) $grossRevenue, 2),
            'refunded_amount'  => round((float) $refundedAmount, 2),
            'net_revenue'      => round((float) $netRevenue, 2),
            'total_refunds'    => $totalRefunds,

            // backward compatibility
            'all_time_revenue' => round((float) $netRevenue, 2),
        ];
    }

    // -------------------------------------------------------------------------
    // Revenue by Period
    // -------------------------------------------------------------------------
    public function getRevenue(int $ownerId, string $period): array
    {
        $query = Order::query()
            ->where('owner_id', $ownerId)
            ->where('payment_status', 'paid')
            ->whereIn('status', self::REVENUE_STATUSES);

        $refundQuery = Refund::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'refunded');

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());

                $refundQuery->whereDate(
                    'processed_at',
                    Carbon::today()
                );
                break;

            case 'last_7_days':
                $query->where(
                    'created_at',
                    '>=',
                    Carbon::now()->subDays(6)->startOfDay()
                );

                $refundQuery->where(
                    'processed_at',
                    '>=',
                    Carbon::now()->subDays(6)->startOfDay()
                );
                break;

            case 'this_month':
                $query->whereYear(
                    'created_at',
                    Carbon::now()->year
                )->whereMonth(
                    'created_at',
                    Carbon::now()->month
                );

                $refundQuery->whereYear(
                    'processed_at',
                    Carbon::now()->year
                )->whereMonth(
                    'processed_at',
                    Carbon::now()->month
                );
                break;
        }

        // Gross revenue
        $result = $query
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(subtotal), 0) as gross_revenue')
            ->first();

        // Refunded amount
        $refundedAmount = $refundQuery->sum('refund_amount');

        // Net revenue
        $netRevenue = (float) ($result->gross_revenue ?? 0)
            - (float) $refundedAmount;

        return [
            'period' => $period,

            'gross_revenue' => round(
                (float) ($result->gross_revenue ?? 0),
                2
            ),

            'refunded_amount' => round(
                (float) $refundedAmount,
                2
            ),

            'revenue' => round(
                $netRevenue,
                2
            ),

            'order_count' => (int) ($result->order_count ?? 0),
        ];
    }
    // -------------------------------------------------------------------------
    // Inventory Alerts (counts + list)
    // -------------------------------------------------------------------------
    public function getInventoryAlerts(int $ownerId, int $lowStockThreshold, int $expiryDays): array
    {
        // LOW STOCK (based on SUM of all packages per product)
        $lowStockCount = OwnerProduct::query()
            ->where('owner_id', $ownerId)
            ->where('status', true)
            ->whereHas('packages', function ($q) use ($lowStockThreshold) {
                $q->select('owner_product_id')
                    ->groupBy('owner_product_id')
                    ->havingRaw('SUM(stock_quantity) <= ?', [$lowStockThreshold]);
            })
            ->count();

        // NEAR EXPIRY (same logic but cleaner)
        $expiryDeadline = Carbon::today()->addDays($expiryDays);

        $nearExpiryCount = OwnerProduct::query()
            ->where('owner_id', $ownerId)
            ->where('status', true)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [Carbon::today(), $expiryDeadline])
            ->count();

        return [
            'low_stock_count'     => $lowStockCount,
            'near_expiry_count'   => $nearExpiryCount,
            'low_stock_threshold' => $lowStockThreshold,
            'near_expiry_days'    => $expiryDays,
        ];
    }

    // -------------------------------------------------------------------------
    // Pending Orders Widget
    // -------------------------------------------------------------------------
    public function getPendingOrders(int $ownerId, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->where('owner_id', $ownerId)
            ->where('status', 'pending')
            ->with(['customer.register', 'customer.information'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Recent Orders Table
    // -------------------------------------------------------------------------
    public function getRecentOrders(int $ownerId, array $filters): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('owner_id', $ownerId)
            ->with(['customer.register', 'customer.information', 'refund'])
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 10), 50);

        return $query->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Low Stock Products Widget
    // -------------------------------------------------------------------------
    public function getLowStockProducts(int $ownerId, int $threshold, int $perPage): LengthAwarePaginator
    {
        return OwnerProduct::query()
            ->where('owner_product.owner_id', $ownerId)
            ->where('owner_product.status', true)

            // only products where ANY package is low stock
            ->whereHas('packages', function ($q) use ($threshold) {
                $q->where('stock_quantity', '<=', $threshold);
            })

            ->with([
                'packages' => function ($q) {
                    $q->orderBy('is_default', 'desc');
                }
            ])

            ->select([
                'id',
                'product_name',
                'form',
                'strength',
                'main_image',
            ])

            ->orderBy('product_name')
            ->paginate($perPage);
    }


    public function getReviewsSummary(int $ownerId): array
    {
        // Step 1:
        // Get average rating grouped by order
        $orderAverages = ProductReview::query()
            ->select(
                'order_id',
                DB::raw('AVG(rating) as order_avg_rating')
            )
            ->whereHas('product', function ($query) use ($ownerId) {
                $query->where('owner_id', $ownerId);
            })
            ->groupBy('order_id')
            ->get();

        // Step 2:
        // Average among all order averages
        $overallAverage = round(
            (float) $orderAverages->avg('order_avg_rating'),
            1
        );

        // Step 3:
        // Distinct reviewed order count
        $totalReviewedOrders = $orderAverages->count();

        return [
            'average_rating'      => $overallAverage,
            'total_review_orders' => $totalReviewedOrders,
        ];
    }

    public function getNearExpiryProducts(int $ownerId, int $days, int $perPage): LengthAwarePaginator
{
    $expiryDate = Carbon::today()->addDays($days);

$products = OwnerProduct::query()
    ->where('owner_id', $ownerId)
    ->where('status', true)
    ->whereNotNull('expiry_date')
    ->where('expiry_date', '<=', $expiryDate)
    ->orderBy('expiry_date')
    ->paginate($perPage);

$products->getCollection()->transform(function ($product) {
    $product->expiry_status = Carbon::parse($product->expiry_date)->isPast()
        ? 'expired'
        : 'near_expiry';

    return $product;
});

return $products; 
}
}
