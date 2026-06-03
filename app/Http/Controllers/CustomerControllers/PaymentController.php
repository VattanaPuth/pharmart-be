<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\PaymentOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
	public function __construct(private PaymentOrderService $paymentOrderService) {}

	private function currentCustomerId(Request $request): ?int
	{
		return $request->user()?->customer?->id;
	}

	public function getCustomerPayments(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$perPage = min((int) $request->query('per_page', 15), 50);

		$payments = $this->paymentOrderService->getCustomerPayments($customerId, $perPage);

		return response()->json($payments);
	}

	public function getCustomerPaymentById(Request $request, int $paymentId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$payment = $this->paymentOrderService->getCustomerPaymentById($customerId, $paymentId);

		if (!$payment) {
			return response()->json(['message' => 'Payment not found'], 404);
		}

		return response()->json([
			'message' => 'Payment retrieved successfully',
			'data' => $payment,
		]);
	}

}
