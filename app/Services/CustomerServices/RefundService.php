<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\Refund;
use App\Models\Customer\Payment;
use Illuminate\Support\Collection;
use Stripe\Refund as StripeRefund;

interface RefundService
{
	public function createRefundRequest(int $customerId, int $requestedBy, array $payload): Refund;
	public function getCustomerRefundById(int $customerId, int $refundId): ?Refund;
	public function listForOwner(int $ownerId): Collection;
	public function getOwnerRefundById(int $ownerId, int $refundId): ?Refund;
	public function reviewRefund(int $refundId, int $reviewedBy): Refund;
	public function processRefund(int $refundId, int $reviewedBy): Refund;
	public function verifyRefund(int $refundId, int $reviewedBy): Refund;
	public function completeRefund(int $refundId, int $reviewedBy): Refund;
	public function cancelRefund(int $refundId, int $canceledBy, string $canceledByRole): Refund;
	public function createStripeRefund(Payment $payment, float $amount, array $metadata): StripeRefund;
}

