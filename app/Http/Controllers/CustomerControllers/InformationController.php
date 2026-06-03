<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\InformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformationController extends Controller
{
	public function __construct(private InformationService $informationService) {}

	private function currentCustomerId(Request $request): ?int
	{
		return $request->user()?->customer?->id;
	}

	public function addCustomerInformation(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'customer_name' => 'required|string|max:255',
			'phone_number' => 'required|string|max:50',
			'email' => 'nullable|email|max:255',
		]);

		$result = $this->informationService->addCustomerInformation($customerId, $validated);

		if (!$result['created']) {
			return response()->json([
				'message' => 'Customer information already exists',
				'data' => $result['information'],
			], 409);
		}

		return response()->json([
			'message' => 'Customer information created successfully',
			'data' => $result['information'],
		], 201);
	}

	public function getCustomerInformation(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$information = $this->informationService->getCustomerInformation($customerId);

		return response()->json([
			'message' => $information ? 'Customer information retrieved successfully' : 'Customer information not found',
			'data' => $information,
		]);
	}

	public function updateCustomerInformation(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'customer_name' => 'sometimes|string|max:255',
			'phone_number' => 'sometimes|string|max:50',
			'email' => 'nullable|email|max:255',
		]);

		$information = $this->informationService->updateCustomerInformation($customerId, $validated);
		if (!$information) {
			return response()->json(['message' => 'Customer information not found'], 404);
		}

		return response()->json([
			'message' => 'Customer information updated successfully',
			'data' => $information,
		]);
	}

}
