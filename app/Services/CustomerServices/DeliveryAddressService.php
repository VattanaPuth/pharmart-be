<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\DeliveryAddress;
use Illuminate\Support\Collection;

interface DeliveryAddressService
{
	public function addDeliveryAddress(int $customerId, array $data): DeliveryAddress;
	public function getDeliveryAddress(int $customerId): Collection;
	public function updateDeliveryAddress(int $customerId, int $deliveryAddressId, array $data): ?DeliveryAddress;
	public function deleteDeliveryAddress(int $customerId, int $deliveryAddressId): bool;
	public function setDefaultAddress(
    int $customerId,
    int $deliveryAddressId
): bool;
}
