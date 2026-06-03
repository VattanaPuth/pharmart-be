<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use App\Services\OwnerServices\OwnerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer\ProductReview;

class OwnerDashboardController extends Controller
{
    // Configurable defaults — can be overridden via query params
    private const DEFAULT_LOW_STOCK_THRESHOLD = 10;
    private const DEFAULT_EXPIRY_DAYS         = 30;
    private const DEFAULT_PER_PAGE            = 10;

    public function __construct(private OwnerDashboardService $dashboardService) {}

    private function currentOwnerId(Request $request): ?int
    {
        return $request->user()?->owner?->id;
    }

    // -------------------------------------------------------------------------
    // GET /api/owner/dashboard
    // Full dashboard snapshot: summary + revenue (default: this_month) + alerts
    // -------------------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $period           = $request->query('period', 'this_month');
        $lowStockThreshold = (int) $request->query('low_stock_threshold', self::DEFAULT_LOW_STOCK_THRESHOLD);
        $expiryDays       = (int) $request->query('expiry_days', self::DEFAULT_EXPIRY_DAYS);

        if (!in_array($period, ['today', 'last_7_days', 'this_month', 'all_time'], true)) {
            $period = 'this_month';
        }

        return response()->json([
            'summary'          => $this->dashboardService->getSummary($ownerId),
            'revenue'          => $this->dashboardService->getRevenue($ownerId, $period),
            'inventory_alerts' => $this->dashboardService->getInventoryAlerts($ownerId, $lowStockThreshold, $expiryDays),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/owner/dashboard/revenue
    // Revenue by period with order count
    // -------------------------------------------------------------------------
    public function revenue(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $period = $request->query('period', 'this_month');

        if (!in_array($period, ['today', 'last_7_days', 'this_month', 'all_time'], true)) {
            return response()->json(['message' => 'Invalid period. Allowed: today, last_7_days, this_month, all_time'], 422);
        }

        return response()->json([
            'data' => $this->dashboardService->getRevenue($ownerId, $period),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/owner/dashboard/pending-orders
    // Pending orders widget
    // -------------------------------------------------------------------------
    public function pendingOrders(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $perPage = min((int) $request->query('per_page', self::DEFAULT_PER_PAGE), 50);

        $orders = $this->dashboardService->getPendingOrders($ownerId, $perPage);

        $mapped = $orders->through(function ($order) {
            $info = $order->customer?->information;
            return [
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'customer_name' => $info ? trim($info->first_name . ' ' . $info->last_name) : 'N/A',
                'amount'        => (float) $order->total,
                'status'        => $order->status,
                'date'          => $order->created_at?->toDateTimeString(),
            ];
        });

        return response()->json($mapped);
    }

    // -------------------------------------------------------------------------
    // GET /api/owner/dashboard/recent-orders
    // Recent orders table with optional filters
    // -------------------------------------------------------------------------
    public function recentOrders(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $filters = $request->only(['status', 'from', 'to', 'per_page']);

        $orders = $this->dashboardService->getRecentOrders($ownerId, $filters);

        $mapped = $orders->through(function ($order) {

            $info = $order->customer?->information;
            $refund = $order->refund;
            return [
                'order_id'       => $order->id,
                'order_number'   => $order->order_number,
                'customer_name'  => $info ? trim($info->first_name . ' ' . $info->last_name) : 'N/A',
                'amount'         => (float) $order->total,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'date'           => $order->created_at?->toDateTimeString(),
                'refund_status'  => $refund?->status,
                'refund_amount'  => $refund?->refund_amount,
                'is_refunded'    => $refund?->status === 'refunded',
            ];
        });

        return response()->json([
            'data' => $mapped->items(),
            'meta' => [
                'current_page' => $mapped->currentPage(),
                'last_page' => $mapped->lastPage(),
                'total' => $mapped->total(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/owner/dashboard/low-stock
    // Low stock products widget
    // -------------------------------------------------------------------------
    public function lowStock(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $threshold = (int) $request->query('threshold', self::DEFAULT_LOW_STOCK_THRESHOLD);
        $perPage   = min((int) $request->query('per_page', self::DEFAULT_PER_PAGE), 50);

        return response()->json(
            $this->dashboardService->getLowStockProducts($ownerId, $threshold, $perPage)
        );
    }

    public function reviewsSummary(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json([
                'message' => 'Owner profile not found'
            ], 404);
        }

        return response()->json([
            'data' => $this->dashboardService->getReviewsSummary($ownerId)
        ]);
    }

    public function nearExpiry(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $days   = (int) $request->query('days', self::DEFAULT_EXPIRY_DAYS);
        $perPage = min((int) $request->query('per_page', self::DEFAULT_PER_PAGE), 50);

        return response()->json(
            $this->dashboardService->getNearExpiryProducts($ownerId, $days, $perPage)
        );
    }


    public function fullDashboard(Request $request): JsonResponse
    {
        $ownerId = $this->currentOwnerId($request);

        if (!$ownerId) {
            return response()->json([
                'message' => 'Owner profile not found'
            ], 404);
        }

        $period = $request->query('period', 'this_month');

        if (!in_array($period, [
            'today',
            'last_7_days',
            'this_month',
            'all_time'
        ], true)) {
            $period = 'this_month';
        }

        $lowStockThreshold = (int) $request->query(
            'low_stock_threshold',
            self::DEFAULT_LOW_STOCK_THRESHOLD
        );

        $expiryDays = (int) $request->query(
            'expiry_days',
            self::DEFAULT_EXPIRY_DAYS
        );

        $perPage = min(
            (int) $request->query(
                'per_page',
                self::DEFAULT_PER_PAGE
            ),
            50
        );

        // ----------------------------------------------------
        // Pending Orders
        // ----------------------------------------------------
        $pendingOrders = $this->dashboardService
            ->getPendingOrders($ownerId, $perPage)
            ->through(function ($order) {

                $info = $order->customer?->information;

                return [
                    'order_id'      => $order->id,
                    'order_number'  =>$order->order_number,
                    'customer_name' => $info
                        ? trim($info->customer_name)
                        : 'N/A',

                    'amount' => (float) $order->total,
                    'status' => $order->status,
                    'date'   => $order->created_at?->toDateTimeString(),
                ];
            });

        // ----------------------------------------------------
        // Recent Orders
        // ----------------------------------------------------
        $recentOrders = $this->dashboardService
            ->getRecentOrders($ownerId, [
                'per_page' => $perPage
            ])
            ->through(function ($order) {

                $info = $order->customer?->information;
                $refund = $order->refund;

                return [
                    'order_id'       => $order->id,
                    'order_number'   => $order->order_number,

                    'customer_name'  => $info
                        ? trim($info->customer_name)
                        : 'N/A',

                    'amount'         => (float) $order->total,
                    'status'         => $order->status,
                    'payment_status' => $order->payment_status,
                    'date'           => $order->created_at?->toDateTimeString(),

                    'refund_status'  => $refund?->status,
                    'refund_amount'  => $refund?->refund_amount,
                    'is_refunded'    => $refund?->status === 'refunded',
                ];
            });

        return response()->json([

            // summary widgets
            'summary' => $this->dashboardService
                ->getSummary($ownerId),

            'revenue' => $this->dashboardService
                ->getRevenue($ownerId, $period),

            'reviews_summary' => $this->dashboardService
                ->getReviewsSummary($ownerId),

            // inventory alerts
            'inventory_alerts' => $this->dashboardService
                ->getInventoryAlerts(
                    $ownerId,
                    $lowStockThreshold,
                    $expiryDays
                ),

            // tables/lists
            'pending_orders' => $pendingOrders->values(),

            'recent_orders' => [
                'data' => $recentOrders->items(),
                'meta' => [
                    'current_page' => $recentOrders->currentPage(),
                    'last_page'    => $recentOrders->lastPage(),
                    'total'        => $recentOrders->total(),
                ],
            ],

            'low_stock' => $this->dashboardService
                ->getLowStockProducts(
                    $ownerId,
                    $lowStockThreshold,
                    $perPage
                ),

            'near_expiry' => $this->dashboardService
                ->getNearExpiryProducts(
                    $ownerId,
                    $expiryDays,
                    $perPage
                ),
        ]);
    }
}
