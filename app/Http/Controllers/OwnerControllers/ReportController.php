<?php

namespace App\Http\Controllers\OwnerControllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Customer\Order;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportsExport;

class ReportController extends Controller
{
    private function currentOwnerId(Request $request): int
    {
        return $request->user()->owner->id;
    }


    private function getReportData(Request $request): array
    {
        $ownerId = $this->currentOwnerId($request);

        $filter = $request->filter ?? 'month';

        $query = Order::with('items')
            ->where('owner_id', $ownerId);

        // FILTERS
        if ($filter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($filter === '7days') {
            $query->whereBetween('created_at', [
                now()->subDays(6)->startOfDay(),
                now()->endOfDay(),
            ]);
        } elseif ($filter === 'month') {
            $query->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } elseif ($filter === 'custom') {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date',
            ]);

            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        }

        $orders = $query->latest()->get();

        $completedOrders = $orders->where('status', 'completed');

        $revenue = $completedOrders->sum('subtotal');

        $refundAmount = $orders->where('status', 'refunded')->sum('total');

        $totalOrders = $orders->count();

        $avgOrderValue = $completedOrders->count()
            ? $revenue / $completedOrders->count()
            : 0;

        $productMap = [];

        foreach ($completedOrders as $order) {

            foreach ($order->items as $item) {

                $key = $item->product_name;

                if (!isset($productMap[$key])) {

                    $productMap[$key] = [
                        'name' => $item->product_name,
                        'qty' => 0,
                        'revenue' => 0,
                    ];
                }

                $productMap[$key]['qty'] += $item->quantity;

                $productMap[$key]['revenue'] += $item->line_total;
            }
        }

        $topProducts = collect($productMap)
            ->sortByDesc('revenue')
            ->take(10)
            ->values()
            ->map(function ($item, $index) {

                return [
                    'rank' => $index + 1,
                    'name' => $item['name'],
                    'qty' => $item['qty'],
                    'revenue' => round($item['revenue'], 2),
                ];
            });


        $chart = [];

        if ($filter === 'today') {

            for ($hour = 0; $hour < 24; $hour++) {

                $hourRevenue = $completedOrders
                    ->filter(fn($o) => $o->created_at->hour === $hour)
                    ->sum('total');

                $chart[] = [
                    'name' => str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00',
                    'revenue' => round($hourRevenue, 2),
                ];
            }
        } elseif ($filter === '7days') {

            $start = now()->subDays(6)->startOfDay();

            for ($i = 0; $i < 7; $i++) {

                $date = $start->copy()->addDays($i);

                $dayRevenue = $completedOrders
                    ->filter(
                        fn($o) =>
                        $o->created_at->format('Y-m-d') === $date->format('Y-m-d')
                    )
                    ->sum('total');

                $chart[] = [
                    'name' => $date->format('M d'),
                    'revenue' => round($dayRevenue, 2),
                ];
            }
        } elseif ($filter === 'month') {

            $start = now()->startOfMonth();
            $end = now()->endOfMonth();

            while ($start <= $end) {

                $date = $start->copy();

                $dayRevenue = $completedOrders
                    ->filter(
                        fn($o) =>
                        $o->created_at->format('Y-m-d') === $date->format('Y-m-d')
                    )
                    ->sum('total');

                $chart[] = [
                    'name' => $date->format('d'),
                    'revenue' => round($dayRevenue, 2),
                ];

                $start->addDay();
            }
        }

        return [
            // 'orders' => $orders,

            'stats' => [
                'revenue' => round($revenue, 2),
                'total_orders' => $totalOrders,
                'avg_order_value' => round($avgOrderValue, 2),
                'refund_amount' => round($refundAmount, 2),
            ],

            'breakdown' => [
                'completed' => $orders->where('status', 'completed')->count(),
                'pending' => $orders->where('status', 'pending')->count(),
                'confirmed' => $orders->where('status', 'confirmed')->count(),
                'ready' => $orders->where('status', 'ready')->count(),
                'delivering' => $orders->where('status', 'delivering')->count(),
                'cancelled' => $orders->where('status', 'cancelled')->count(),
                'refunded' => $orders->where('status', 'refunded')->count(),
                'total' => $orders->count(),
            ],

            'chart' => $chart ?? [],

            'top_products' => $topProducts ?? collect([]),

        ];
    }

    public function dashboard(Request $request)
    {
        $data = $this->getReportData($request);

        return response()->json([
            'success' => true,
            'stats' => $data['stats'],
            // 'orders' => $data['orders'],
            'breakdown' => $data['breakdown'],
            'chart' => $data['chart'],
            'top_products' => $data['top_products'],
        ]);
    }

    private function buildFileName(Request $request): string
{
    $filter = $request->filter ?? 'month';

    $date = now()->format('Y-m-d');

    return "report_{$filter}_{$date}.xlsx";
}

    public function export(Request $request)
    {
        $data = $this->getReportData($request);

        return Excel::download(
            new ReportsExport(
                $data['stats'],
                // $data['orders'],
                $data['top_products'],
                $data['breakdown'],
                $data['chart']
            ),
             $this->buildFileName($request),
             \Maatwebsite\Excel\Excel::XLSX,
    [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]
        );
    }
}
