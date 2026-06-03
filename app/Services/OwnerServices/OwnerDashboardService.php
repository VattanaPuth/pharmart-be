<?php

namespace App\Services\OwnerServices;

interface OwnerDashboardService
{
    public function getSummary(int $ownerId): array;
    public function getRevenue(int $ownerId, string $period): array;
    public function getInventoryAlerts(int $ownerId, int $lowStockThreshold, int $expiryDays): array;
    public function getPendingOrders(int $ownerId, int $perPage): \Illuminate\Pagination\LengthAwarePaginator;
    public function getRecentOrders(int $ownerId, array $filters): \Illuminate\Pagination\LengthAwarePaginator;
    public function getLowStockProducts(int $ownerId, int $threshold, int $perPage): \Illuminate\Pagination\LengthAwarePaginator;
    public function getReviewsSummary(int $ownerId): array;
    public function getNearExpiryProducts(int $ownerId, int $days, int $perPage): \Illuminate\Pagination\LengthAwarePaginator;
}

