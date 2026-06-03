<?php

namespace App\Services\OwnerServices;

use App\Models\Owner\OwnerSetting;

interface OwnerSettingService
{
	public function getSetting(int $ownerId): ?OwnerSetting;

	public function setSetting(int $ownerId, array $data): OwnerSetting;

	public function updateSetting(int $ownerId, array $data): OwnerSetting;

}
