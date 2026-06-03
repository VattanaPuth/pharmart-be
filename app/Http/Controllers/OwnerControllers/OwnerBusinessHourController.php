<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use App\Services\OwnerServices\OwnerBusinessHourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OwnerBusinessHourController extends Controller
{
	public function __construct(private OwnerBusinessHourService $ownerBusinessHourService) {}

	private function currentOwnerId(Request $request): int
	{
		return $request->user()->owner->id;
	}

	public function getBusinessHour(Request $request): Collection
	{
		$ownerId = $this->currentOwnerId($request);

		return $this->ownerBusinessHourService->getBusinessHour($ownerId);
	}

	public function setBusinessHour(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
			'open_time' => 'nullable|date_format:H:i',
			'close_time' => 'nullable|date_format:H:i|after:open_time',
			'is_open' => 'sometimes|boolean',
		]);

		$ownerId = $this->currentOwnerId($request);
		$businessHour = $this->ownerBusinessHourService->setBusinessHour($ownerId, $validated);

		return response()->json([
			'message' => 'Business hour created successfully',
			'data' => $businessHour,
		], 201);
	}

	public function updateBusinessHour(Request $request, int $businessHourId): JsonResponse
	{
		$validated = $request->validate([
			'day_of_week' => 'sometimes|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
			'open_time' => 'nullable|date_format:H:i',
			'close_time' => 'nullable|date_format:H:i',
			'is_open' => 'sometimes|boolean',
		]);

		if (isset($validated['open_time']) && isset($validated['close_time']) && $validated['close_time'] <= $validated['open_time']) {
			return response()->json([
				'message' => 'Validation failed',
				'errors' => [
					'close_time' => ['The close time must be after open time.'],
				],
			], 422);
		}

		$ownerId = $this->currentOwnerId($request);
		$businessHour = $this->ownerBusinessHourService->updateBusinessHour($ownerId, $businessHourId, $validated);

		return response()->json([
			'message' => 'Business hour updated successfully',
			'data' => $businessHour,
		]);
	}

	public function bulkUpsert(Request $request): JsonResponse
{
    $validated = $request->validate([
        'groups' => 'required|array|min:1',
        'groups.*.days' => 'required|array|min:1',
        'groups.*.days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        'groups.*.open_time' => 'nullable|date_format:H:i',
        'groups.*.close_time' => 'nullable|date_format:H:i|after:groups.*.open_time',
        'groups.*.is_open' => 'boolean',
    ]);

    $ownerId = $this->currentOwnerId($request);

    $result = $this->ownerBusinessHourService->bulkUpsert($ownerId, $validated['groups']);

    return response()->json([
        'message' => 'Business hours updated successfully',
        'data' => $result,
    ]);
}

}
