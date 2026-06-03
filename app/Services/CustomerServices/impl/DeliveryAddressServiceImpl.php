<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\DeliveryAddress;
use App\Services\CustomerServices\DeliveryAddressService;
use Illuminate\Support\Collection;

class DeliveryAddressServiceImpl implements DeliveryAddressService
{
	public function addDeliveryAddress(int $customerId, array $data): DeliveryAddress
	{
		return DeliveryAddress::query()->create([
			'customer_id' => $customerId,
			...$data,
		]);
	}

	public function getDeliveryAddress(int $customerId): Collection
	{
		return DeliveryAddress::query()
			->where('customer_id', $customerId)
			->orderByDesc('created_at')
			->get();
	}

	public function updateDeliveryAddress(int $customerId, int $deliveryAddressId, array $data): ?DeliveryAddress
	{
		$address = DeliveryAddress::query()
			->where('customer_id', $customerId)
			->where('id', $deliveryAddressId)
			->first();

		if (!$address) {
			return null;
		}

		$address->update($data);

		return $address->fresh();
	}

	public function deleteDeliveryAddress(int $customerId, int $deliveryAddressId): bool
	{
		$address = DeliveryAddress::query()
			->where('customer_id', $customerId)
			->where('id', $deliveryAddressId)
			->first();

		if (!$address) {
			return false;
		}

		$address->delete();

		return true;
	}

	public function setDefaultAddress(
    int $customerId,
    int $deliveryAddressId
): bool {

    $address = DeliveryAddress::query()
        ->where('customer_id', $customerId)
        ->where('id', $deliveryAddressId)
        ->first();

    if (!$address) {
        return false;
    }

    // remove old default
    DeliveryAddress::query()
        ->where('customer_id', $customerId)
        ->update([
            'is_default' => false
        ]);

    // set new default
    $address->update([
        'is_default' => true
    ]);

    return true;
}

}
