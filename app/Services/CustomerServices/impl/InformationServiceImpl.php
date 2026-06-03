<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\Information;
use App\Services\CustomerServices\InformationService;
use App\Models\Auth\Register;

class InformationServiceImpl implements InformationService
{
	public function addCustomerInformation(int $customerId, array $data): array
	{
		$existing = Information::query()->where('customer_id', $customerId)->first();

		if ($existing) {
			return [
				'created' => false,
				'information' => $existing,
			];
		}

		$information = Information::query()->create([
			'customer_id' => $customerId,
			...$data,
		]);

		// ✅ mark onboarding complete
		$this->completeOnboarding();

		return [
			'created' => true,
			'information' => $information,
		];
	}

	public function getCustomerInformation(int $customerId): ?Information
	{
		return Information::query()->where('customer_id', $customerId)->first();
	}

	public function updateCustomerInformation(int $customerId, array $data): ?Information
	{
		$information = Information::query()->where('customer_id', $customerId)->first();

		if (!$information) {
			$information = Information::query()->create([
				'customer_id' => $customerId,
				...$data,
			]);

			$this->completeOnboarding();

			return $information;
		}

		$this->completeOnboarding();
		$information->update($data);

		return $information->fresh();
	}

	private function completeOnboarding()
	{
		if (auth('api')->check()) {
			Register::where('id', auth('api')->id())
				->update(['onboarding_completed' => true]);
		}
	}
}
