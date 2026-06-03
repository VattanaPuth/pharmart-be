<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\Payment;
use App\Models\Customer\PaymentOrder;
use App\Services\CustomerServices\PaymentOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaymentOrderServiceImpl implements PaymentOrderService
{
	public function createPaymentOrderRows(Payment $payment, array $orderAmountRows): void
	{
		foreach ($orderAmountRows as $row) {
			PaymentOrder::query()->create([
				'payment_id' => $payment->id,
				'order_id' => (int) $row['order_id'],
				'amount' => round((float) $row['amount'], 2),
				'created_at' => now(),
			]);
		}
	}

	public function getCustomerPayments(int $customerId, int $perPage): LengthAwarePaginator
	{
		return Payment::query()
			->where('customer_id', $customerId)
			->with(['orders'])
			->orderByDesc('created_at')
			->paginate($perPage);
	}

	public function getCustomerPaymentById(int $customerId, int $paymentId): ?Payment
	{
		return Payment::query()
			->where('customer_id', $customerId)
			->where('id', $paymentId)
			->with(['orders'])
			->first();
	}

}
