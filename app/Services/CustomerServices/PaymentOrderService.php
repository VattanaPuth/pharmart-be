<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PaymentOrderService
{
	public function createPaymentOrderRows(Payment $payment, array $orderAmountRows): void;
	public function getCustomerPayments(int $customerId, int $perPage): LengthAwarePaginator;
	public function getCustomerPaymentById(int $customerId, int $paymentId): ?Payment;
}
