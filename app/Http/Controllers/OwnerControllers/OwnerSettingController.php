<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use App\Services\OwnerServices\OwnerSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Throwable;
use Illuminate\Support\Facades\Log;

class OwnerSettingController extends Controller
{
	public function __construct(private OwnerSettingService $ownerSettingService) {}

	private function currentOwnerId(Request $request): int
	{
		return $request->user()->owner->id;
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

	public function getSetting(Request $request): JsonResponse
	{

		// [$lat, $lng] = $this->parseGps($data['gps_location'] ?? null);

		// $data['latitude'] = $lat;
		// $data['longitude'] = $lng;

		$ownerId = $this->currentOwnerId($request);
		$setting = $this->ownerSettingService->getSetting($ownerId);

		return response()->json([
			'message' => $setting ? 'Owner setting retrieved successfully' : 'Owner setting not found',
			'data' => $setting,
		]);
	}

	public function setSetting(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'pharmacy_name' => 'required|string|max:255',
			'owner_name' => 'required|string|max:255',
			'address' => 'required|string',
			'city' => 'required|string|max:255',
			'gps_location' => 'nullable|string|max:500|regex:/^(https?:\/\/)?(www\.)?(google\.com\/maps\/.*|maps\.app\.goo\.gl\/.*)$/i',
			'phone_number' => 'required|string|max:30',
			'displayable_email' => 'nullable|string|max:255',
			'logo' => 'nullable|image',
			'notification_enabled' => 'sometimes|boolean',
			'low_stock_alert' => 'nullable|integer|min:0',
		]);

		// ✅ FIX: use validated
		[$lat, $lng] = $this->parseGps($validated['gps_location'] ?? null);

		$validated['latitude'] = $lat;
		$validated['longitude'] = $lng;

		$ownerId = $this->currentOwnerId($request);
		$setting = $this->ownerSettingService->setSetting($ownerId, $validated);

		return response()->json([
			'message' => 'Owner setting created successfully',
			'data' => $setting,
		], 201);
	}

	public function updateSetting(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'pharmacy_name' => 'sometimes|string|max:255',
			'owner_name' => 'sometimes|string|max:255',
			'address' => 'sometimes|string',
			'city' => 'sometimes|string|max:255',
			'gps_location' => 'nullable|string|max:255',
			'phone_number' => 'sometimes|string|max:30',
			'notification_enabled' => 'sometimes|boolean',
			'low_stock_alert' => 'nullable|integer|min:0',
		]);

		// ✅ FIX: use validated (not $data)
		if (isset($validated['gps_location'])) {
			[$lat, $lng] = $this->parseGps($validated['gps_location']);

			$validated['latitude'] = $lat;
			$validated['longitude'] = $lng;
		}

		$ownerId = $this->currentOwnerId($request);
		$setting = $this->ownerSettingService->updateSetting($ownerId, $validated);

		return response()->json([
			'message' => 'Owner setting updated successfully',
			'data' => $setting,
		]);
	}

	public function uploadLogo(Request $request): JsonResponse
	{
		$request->validate([
			'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
		]);

		$ownerId = $this->currentOwnerId($request);

		$setting = $this->ownerSettingService->getSetting($ownerId);

		if (!$setting) {
			return response()->json([
				'message' => 'Setting not found'
			], 404);
		}

		$file = $request->file('logo');

		// delete old logo
		if ($setting->logo) {
			Storage::disk('public')->delete($setting->logo);
		}

		// store new logo
		$path = $file->store('owner/settings', 'public');

		$setting->logo = $path;
		$setting->save();

		return response()->json([
			'message' => 'Logo uploaded successfully',
			'data' => [
				'logo_url' => asset('storage/' . $path),
				'logo_path' => $path,
			]
		]);
	}
}
