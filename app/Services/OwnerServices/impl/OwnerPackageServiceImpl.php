<?php

namespace App\Services\OwnerServices\impl;

use App\Models\Owner\OwnerPackage;
use App\Models\Owner\OwnerProduct;
use App\Services\OwnerServices\OwnerPackageService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class OwnerPackageServiceImpl implements OwnerPackageService
{
	public function read(int $ownerId, array $filters = []): LengthAwarePaginator
	{
		$perPage = (int) ($filters['per_page'] ?? 15);

		return OwnerPackage::query()
			->with('ownerProduct')
			->whereHas('ownerProduct', function ($q) use ($ownerId): void {
				$q->visible()->where('owner_id', $ownerId);
			})
			->latest()
			->paginate($perPage)
			->through(function (OwnerPackage $package): array {
				return [
					'id' => $package->id,
					'owner_product_id' => $package->owner_product_id,
					'package_name' => $package->package_name,
					'contains' => $package->contains,
					'price' => $package->price,
					'stock_quantity' => $package->stock_quantity,
					'created_at' => $package->created_at,
					'updated_at' => $package->updated_at,
				];
			});
	}

	public function addPackage(int $ownerId, array $data): OwnerPackage
	{
		$product = OwnerProduct::visible()
			->where('owner_id', $ownerId)
			->where('id', $data['owner_product_id'])
			->first();

		if (!$product) {
			throw ValidationException::withMessages([
				'owner_product_id' => ['Product not found for this owner.'],
			]);
		}

		return DB::transaction(function () use ($data) {

			// ✅ check if this product already has packages
			$exists = OwnerPackage::where('owner_product_id', $data['owner_product_id'])->exists();

			// ✅ first package → auto default
			if (!$exists) {
				$data['is_default'] = 1;
			}

			// ✅ if user explicitly sets default → reset others
			if (!empty($data['is_default'])) {
				OwnerPackage::where('owner_product_id', $data['owner_product_id'])
					->update(['is_default' => 0]);
			}

			return OwnerPackage::query()->create($data);
		});
	}
	public function updatePackage(int $ownerId, int $packageId, array $data): OwnerPackage
	{
		$package = OwnerPackage::query()
			->with('ownerProduct')
			->where('id', $packageId)
			->whereHas('ownerProduct', function ($q) use ($ownerId): void {
				$q->where('owner_id', $ownerId);
			})
			->firstOrFail();

		if (array_key_exists('owner_product_id', $data)) {
			$targetProduct = OwnerProduct::query()
				->where('owner_id', $ownerId)
				->where('id', $data['owner_product_id'])
				->first();

			if (!$targetProduct) {
				throw ValidationException::withMessages([
					'owner_product_id' => ['Product not found for this owner.'],
				]);
			}
		}

		$package->update($data);

		return $package->fresh();
	}

public function setDefaultPackage(int $ownerId, int $productId, int $packageId): void
{
    DB::transaction(function () use ($ownerId, $productId, $packageId) {

        $package = OwnerPackage::where('id', $packageId)
            ->where('owner_product_id', $productId)
            ->whereHas('ownerProduct', function ($q) use ($ownerId) {
                $q->where('owner_id', $ownerId);
            })
            ->firstOrFail();

  DB::table('owner_package')
    ->where('owner_product_id', $productId)
    ->update(['is_default' => 0]);

        $package->update(['is_default' => 1]);
    });
}
}
