<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use App\Services\OwnerServices\OwnerPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Owner\OwnerPackage;

class OwnerPackageController extends Controller
{
	public function __construct(private OwnerPackageService $ownerPackageService) {}

	private function currentOwnerId(Request $request): int
	{
		return $request->user()->owner->id;
	}

	public function read(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'page' => 'nullable|integer|min:1',
			'per_page' => 'nullable|integer|min:1|max:100',
		]);

		$ownerId = $this->currentOwnerId($request);

		return response()->json($this->ownerPackageService->read($ownerId, $validated));
	}

	public function addPackage(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'owner_product_id' => 'required|integer|exists:owner_product,id',
			'package_name' => 'required|in:box,strip,bottle,unit',
			'contains' => 'required|string|max:255',
			'price' => 'required|numeric|min:0',
			'stock_quantity' => 'required|integer|min:0',
		]);

		$ownerId = $this->currentOwnerId($request);

		$package = $this->ownerPackageService->addPackage($ownerId, $validated);

		return response()->json([
			'message' => 'Package created successfully',
			'data' => $package,
		], 201);
	}

	public function updatePackage(Request $request, int $packageId): JsonResponse
	{
		$validated = $request->validate([
			'owner_product_id' => 'sometimes|integer|exists:owner_product,id',
			'package_name' => 'sometimes|in:box,strip,bottle,unit',
			'contains' => 'sometimes|string|max:255',
			'price' => 'sometimes|numeric|min:0',
			'stock_quantity' => 'sometimes|integer|min:0',
		]);

		$ownerId = $this->currentOwnerId($request);

		$package = $this->ownerPackageService->updatePackage($ownerId, $packageId, $validated);

		return response()->json([
			'message' => 'Package updated successfully',
			'data' => $package,
		]);
	}

public function setDefault(int $productId, int $packageId, Request $request): JsonResponse
{
    $ownerId = $this->currentOwnerId($request);

    $this->ownerPackageService->setDefaultPackage(
        $ownerId,
        $productId,
        $packageId
    );

    return response()->json([
        'message' => 'Default package set successfully'
    ]);
}
}
