<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\DeliveryAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class DeliveryAddressController extends Controller
{
	public function __construct(private DeliveryAddressService $deliveryAddressService) {}

	private function currentCustomerId(Request $request): ?int
	{
		return $request->user()?->customer?->id;
	}


	private function parseGps(string $url): array
	{
		try {

			// =====================================
			// EXPAND SHORT GOOGLE MAP URL
			// =====================================
			if (str_contains($url, 'maps.app.goo.gl')) {

				$response = Http::withOptions([
					'allow_redirects' => true,
				])
					->timeout(10)
					->get($url);

				$url = (string) $response->effectiveUri();
			}

			// =====================================
			// PRIORITY 1:
			// EXACT PLACE COORDINATES
			// =====================================
			if (
				preg_match(
					'/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
					$url,
					$matches
				)
			) {
				return [
					(float) $matches[1],
					(float) $matches[2],
				];
			}

			// =====================================
			// PRIORITY 2:
			// MAP CENTER
			// =====================================
			if (
				preg_match(
					'/@(-?\d+\.\d+),(-?\d+\.\d+)/',
					$url,
					$matches
				)
			) {
				return [
					(float) $matches[1],
					(float) $matches[2],
				];
			}

			return [null, null];
		} catch (Throwable $e) {

			Log::error('GPS Parse Error', [
				'message' => $e->getMessage(),
			]);

			return [null, null];
		}
	}


	public function addDeliveryAddress(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'label' => 'required|string|max:100',
			'recipient_name' => 'required|string|max:255',
			'phone_number' => 'required|string|max:50',
			'full_address' => 'required|string',
			'city' => 'required|string|max:255',
			'google_map_link' => 'nullable|string|max:2048',
		]);

		// ✅ extract lat/lng like owner
		[$lat, $lng] = $this->parseGps($validated['google_map_link'] ?? null);

		$validated['latitude'] = $lat;
		$validated['longitude'] = $lng;

		$address = $this->deliveryAddressService->addDeliveryAddress(
			$customerId,
			$validated
		);

		return response()->json([
			'message' => 'Delivery address created successfully',
			'data' => $address,
		], 201);
	}

	public function getDeliveryAddress(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$addresses = $this->deliveryAddressService->getDeliveryAddress($customerId);

		return response()->json([
			'message' => 'Delivery addresses retrieved successfully',
			'data' => $addresses,
		]);
	}

	public function updateDeliveryAddress(Request $request, int $deliveryAddressId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'label' => 'sometimes|string|max:100',
			'recipient_name' => 'sometimes|string|max:255',
			'phone_number' => 'sometimes|string|max:50',
			'full_address' => 'sometimes|string',
			'city' => 'sometimes|string|max:255',
			'google_map_link' => 'nullable|string|max:2048',
		]);

		// ✅ GPS extraction (same as create)
		if (array_key_exists('google_map_link', $validated)) {
			[$lat, $lng] = $this->parseGps($validated['google_map_link']);

			$validated['latitude'] = $lat;
			$validated['longitude'] = $lng;
		}

		$address = $this->deliveryAddressService->updateDeliveryAddress(
			$customerId,
			$deliveryAddressId,
			$validated
		);

		if (!$address) {
			return response()->json(['message' => 'Delivery address not found'], 404);
		}

		return response()->json([
			'message' => 'Delivery address updated successfully',
			'data' => $address,
		]);
	}

	public function deleteDeliveryAddress(Request $request, int $deliveryAddressId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$deleted = $this->deliveryAddressService->deleteDeliveryAddress($customerId, $deliveryAddressId);

		if (!$deleted) {
			return response()->json(['message' => 'Delivery address not found'], 404);
		}

		return response()->json([
			'message' => 'Delivery address deleted successfully',
		]);
	}

	public function setDefaultAddress(
		Request $request,
		int $deliveryAddressId
	): JsonResponse {

		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json([
				'message' => 'Customer profile not found'
			], 404);
		}

		$updated = $this->deliveryAddressService
			->setDefaultAddress(
				$customerId,
				$deliveryAddressId
			);

		if (!$updated) {
			return response()->json([
				'message' => 'Delivery address not found'
			], 404);
		}

		return response()->json([
			'message' => 'Default address updated successfully'
		]);
	}
}
