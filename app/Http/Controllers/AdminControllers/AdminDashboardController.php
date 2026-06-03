<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Models\Owner\OwnerSetting;
use App\Models\Customer\Order;
use App\Models\Customer\Refund;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        // -------------------------
        // PHARMACIES
        // -------------------------
        $verifiedPharmacies = OwnerSetting::where('status', 'approved')->count();

        $suspendedPharmacies = OwnerSetting::where('status', 'suspended')->count();

        $pendingRegistrations = OwnerSetting::where('status', 'pending')->count();

        $totalPharmacies = OwnerSetting::count();

        // -------------------------
        // ORDERS
        // -------------------------
        $totalOrders = Order::count();

        $platformRevenue = Order::where('payment_status', 'paid')->sum('total');

        // -------------------------
        // REFUNDS
        // -------------------------
        $totalRefunded = Refund::where('status', 'refunded')->sum('refund_amount');

        // -------------------------
        // NET + AOV
        // -------------------------
        $netRevenue = $platformRevenue - $totalRefunded;

        $averageOrderValue = $totalOrders > 0
            ? $platformRevenue / $totalOrders
            : 0;

        return response()->json([
            'verifiedPharmacies' => $verifiedPharmacies,
            'suspendedPharmacies' => $suspendedPharmacies,
            'pendingRegistrations' => $pendingRegistrations,
            'totalPharmacies' => $totalPharmacies,

            'totalOrders' => $totalOrders,
            'platformRevenue' => round($platformRevenue, 2),
            'totalRefunded' => round($totalRefunded, 2),
            'netRevenue' => round($netRevenue, 2),
            'averageOrderValue' => round($averageOrderValue, 2),
        ]);
    }
}