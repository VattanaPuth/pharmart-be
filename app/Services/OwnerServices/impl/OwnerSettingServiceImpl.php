<?php

namespace App\Services\OwnerServices\impl;

use App\Models\Owner\OwnerSetting;
use App\Services\OwnerServices\OwnerSettingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class OwnerSettingServiceImpl implements OwnerSettingService
{



	public function getSetting(int $ownerId): ?OwnerSetting
	{
		return OwnerSetting::query()
			->where('owner_id', $ownerId)
			->first();
	}

	public function setSetting(int $ownerId, array $data): OwnerSetting
	{
		$existing = OwnerSetting::query()
			->where('owner_id', $ownerId)
			->first();

		if ($existing) {
			throw ValidationException::withMessages([
				'owner_id' => ['Setting already exists. Use updateSetting (PUT).'],
			]);
		}

		$data['owner_id'] = $ownerId;

		if (isset($data['logo'])) {
			$data['logo'] = $data['logo']->store('owner/settings', 'public');
		}

		return OwnerSetting::query()->create($data);
	}

	public function updateSetting(int $ownerId, array $data): OwnerSetting
	{
		$setting = OwnerSetting::query()
			->where('owner_id', $ownerId)
			->firstOrFail();


		$setting->update($data);

		return $setting->fresh();
	}

	

}
