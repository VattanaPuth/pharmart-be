<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\Information;

interface InformationService
{
	public function addCustomerInformation(int $customerId, array $data): array;
	public function getCustomerInformation(int $customerId): ?Information;
	public function updateCustomerInformation(int $customerId, array $data): ?Information;
}
