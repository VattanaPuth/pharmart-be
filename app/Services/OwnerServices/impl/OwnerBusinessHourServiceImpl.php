<?php

namespace App\Services\OwnerServices\impl;

use App\Models\Owner\OwnerBusinessHour;
use App\Models\Owner\OwnerSetting;
use App\Services\OwnerServices\OwnerBusinessHourService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OwnerBusinessHourServiceImpl implements OwnerBusinessHourService
{
	public function getBusinessHour(int $ownerId): Collection
	{
		$setting = OwnerSetting::query()
			->where('owner_id', $ownerId)
			->first();

		if (!$setting) {
			return collect();
		}

		return OwnerBusinessHour::query()
			->where('owner_setting_id', $setting->id)
			->orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
			->get();
	}

	public function setBusinessHour(int $ownerId, array $data): OwnerBusinessHour
	{
		$setting = OwnerSetting::query()
			->where('owner_id', $ownerId)
			->first();

		if (!$setting) {
			throw ValidationException::withMessages([
				'owner_setting_id' => ['Owner setting not found. Please create owner setting first.'],
			]);
		}

		$data['owner_setting_id'] = $setting->id;
		$data['is_open'] = $data['is_open'] ?? false;

		$duplicate = OwnerBusinessHour::query()
			->where('owner_setting_id', $setting->id)
			->where('day_of_week', $data['day_of_week'])
			->exists();

		if ($duplicate) {
			throw ValidationException::withMessages([
				'day_of_week' => ['Business hour for this day already exists. Use updateBusinessHour (PUT).'],
			]);
		}

		return OwnerBusinessHour::query()->create($data);
	}

	public function updateBusinessHour(int $ownerId, int $businessHourId, array $data): OwnerBusinessHour
	{
		$setting = OwnerSetting::query()
			->where('owner_id', $ownerId)
			->first();

		if (!$setting) {
			throw ValidationException::withMessages([
				'owner_setting_id' => ['Owner setting not found. Please create owner setting first.'],
			]);
		}

		$businessHour = OwnerBusinessHour::query()
			->where('id', $businessHourId)
			->where('owner_setting_id', $setting->id)
			->firstOrFail();

		if (isset($data['day_of_week'])) {
			$duplicate = OwnerBusinessHour::query()
				->where('owner_setting_id', $setting->id)
				->where('day_of_week', $data['day_of_week'])
				->where('id', '!=', $businessHourId)
				->exists();

			if ($duplicate) {
				throw ValidationException::withMessages([
					'day_of_week' => ['Business hour for this day already exists.'],
				]);
			}
		}

		$businessHour->update($data);

		return $businessHour->fresh();
	}

	public function bulkUpsert(int $ownerId, array $groups): Collection
	{
		$ownerSetting = OwnerSetting::query()
			->where('owner_id', $ownerId)
			->firstOrFail();

		$result = [];

		foreach ($groups as $group) {

			foreach ($group['days'] as $day) {

				$record = OwnerBusinessHour::updateOrCreate(
					[
						'owner_setting_id' => $ownerSetting->id,
						'day_of_week' => $day,
					],
					[
						'open_time' => $group['open_time'] ?? null,
						'close_time' => $group['close_time'] ?? null,
						'is_open' => $group['is_open'] ?? false,
					]
				);

				$result[] = $record;
			}
		}

		return collect($result);
	}
}
