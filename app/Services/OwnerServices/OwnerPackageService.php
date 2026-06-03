<?php

namespace App\Services\OwnerServices;


use App\Models\Owner\OwnerPackage;
use Illuminate\Pagination\LengthAwarePaginator;


interface OwnerPackageService
{
	public function read(int $ownerId, array $filters = []): LengthAwarePaginator;

	public function addPackage(int $ownerId, array $data): OwnerPackage;

	public function updatePackage(int $ownerId, int $packageId, array $data): OwnerPackage;

	public function setDefaultPackage(int $ownerId, int $productId, int $packageId): void;
	

}
