<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\Payment;

interface PaymentService
{
	public function createPendingCombinedPayment(
		int $customerId,
		string $paymentProvider,
		float $amount,
		string $currency
	): Payment;

	public function processWithProvider(
		string $paymentMethod,
		string $paymentProvider,
		float $amount,
		?string $providerToken = null
	): array;

	public function finalizePayment(Payment $payment, string $status, ?string $transactionId = null): Payment;

}
