<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\Order;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderService
{
	public function checkout(int $customerId, array $payload): array;
	public function getOrders(int $customerId, array $filters): LengthAwarePaginator;
	public function getOrderById(int $customerId, int $orderId): ?Order;
	public function updateStatus(int $ownerId, int $orderId, string $status): Order;
	public function cancelByCustomer(int $customerId, int $orderId): Order;
	public function confirmReceived(int $customerId, int $orderId): Order;
	public function declineOrder(int $ownerId, int $orderId, string $reason): Order;
	public function progressOrder(int $ownerId, int $orderId): Order;
	public function confirmOrder(int $ownerId,int $orderId): Order;
	public function completeOrder(int $ownerId,int $orderId): Order;
}
