<?php

namespace App\Services\OwnerServices;

use App\Models\Owner\OwnerBusinessHour;
use Illuminate\Support\Collection;

interface OwnerBusinessHourService
{
	public function getBusinessHour(int $ownerId): Collection;

	public function setBusinessHour(int $ownerId, array $data): OwnerBusinessHour;

	public function updateBusinessHour(int $ownerId, int $businessHourId, array $data): OwnerBusinessHour;
	 public function bulkUpsert(int $ownerId, array $groups): Collection;

}
