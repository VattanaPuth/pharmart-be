<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Customer\RefundImage;
use RuntimeException;



class RefundController extends Controller
{
	public function __construct(private RefundService $refundService) {}

	private function currentCustomerId(Request $request): ?int
	{
		return $request->user()?->customer?->id;
	}

	private function currentOwnerId(Request $request): ?int
	{
		return $request->user()?->owner?->id;
	}

	public function create(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$images = $request->file('images', []);
		if ($images instanceof \Illuminate\Http\UploadedFile) {
			$images = [$images];
		}

		if (count($images) > 3) {
			return response()->json([
				'message' => 'Maximum 3 images allowed'
			], 422);
		}
		$validated = $request->validate([
			'order_id' => 'required|integer|exists:customer_order,id',
			'payment_id' => 'nullable|integer|exists:payments,id',
			'reason' => 'required|string|max:255',
			'note' => 'nullable|string|max:1000',
			'refund_type' => 'required|in:full,partial',
			'refund_amount' => 'nullable|numeric|min:0.01',

			'items' => 'nullable|array|min:1',
			'items.*.order_item_id' => 'required_with:items|integer|exists:customer_order_items,id',
			'items.*.quantity' => 'required_with:items|integer|min:1',

			'images' => 'nullable',
			'images.*' => 'image|mimes:jpg,jpeg,png',
		]);



		try {
			$refund = $this->refundService->createRefundRequest(
				$customerId,
				(int) $request->user()->id,
				array_merge($validated, [
					'images' => $request->file('images', [])
				])
			);
		} catch (RuntimeException $exception) {
			return response()->json([
				'message' => $exception->getMessage()
			], 422);
		}

		return response()->json([
			'message' => 'Refund request submitted successfully',
			'data' => $refund,
		], 201);
	}

	public function show(Request $request, int $refundId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$refund = $this->refundService->getCustomerRefundById($customerId, $refundId);

		if (!$refund) {
			return response()->json(['message' => 'Refund request not found'], 404);
		}

		return response()->json([
			'message' => 'Refund request retrieved successfully',
			'data' => $refund,
		]);
	}

	public function indexOwner(Request $request): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);

		if (!$ownerId) {
			return response()->json(['message' => 'Owner profile not found'], 404);
		}

		$refunds = $this->refundService->listForOwner($ownerId);

		return response()->json([
			'message' => 'Refund requests retrieved successfully',
			'data' => $refunds,
		]);
	}

	public function showOwner(Request $request, int $refundId): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);

		if (!$ownerId) {
			return response()->json(['message' => 'Owner profile not found'], 404);
		}

		$refund = $this->refundService->getOwnerRefundById($ownerId, $refundId);

		if (!$refund) {
			return response()->json(['message' => 'Refund request not found'], 404);
		}

		return response()->json([
			'message' => 'Refund retrieved successfully',
			'data' => $refund,
		]);
	}

	public function reviewOwner(Request $request, int $refundId): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);

		if (!$ownerId) {
			return response()->json(['message' => 'Owner profile not found'], 404);
		}

		$existing = $this->refundService->getOwnerRefundById($ownerId, $refundId);

		if (!$existing) {
			return response()->json(['message' => 'Refund request not found'], 404);
		}

		try {
			$refund = $this->refundService->reviewRefund($refundId, (int) $request->user()->id);
		} catch (RuntimeException $exception) {
			return response()->json(['message' => $exception->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Refund request approved successfully',
			'data' => $refund,
		]);
	}

	public function processOwner(Request $request, int $refundId): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);

		if (!$ownerId) {
			return response()->json(['message' => 'Owner profile not found'], 404);
		}

		$existing = $this->refundService->getOwnerRefundById($ownerId, $refundId);

		if (!$existing) {
			return response()->json(['message' => 'Refund request not found'], 404);
		}

		try {
			$refund = $this->refundService->processRefund($refundId, (int) $request->user()->id);
		} catch (RuntimeException $exception) {
			return response()->json(['message' => $exception->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Refund marked as returning successfully',
			'data' => $refund,
		]);
	}

	public function verifyOwner(Request $request, int $refundId): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);


		if (!$ownerId) {
			return response()->json(['message' => 'Owner profile not found'], 404);
		}

		$existing = $this->refundService->getOwnerRefundById($ownerId, $refundId);

		if (!$existing) {
			return response()->json(['message' => 'Refund request not found'], 404);
		}

		if (!$existing->inspection_note || $existing->inspectionImages->count() === 0) {
			return response()->json([
				'message' => 'Cannot verify without inspection note and images'
			], 422);
		}

		try {
			$refund = $this->refundService->verifyRefund($refundId, (int) $request->user()->id);
		} catch (RuntimeException $exception) {
			return response()->json(['message' => $exception->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Refund verified successfully',
			'data' => $refund,
		]);
	}

	public function completeOwner(Request $request, int $refundId): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);

		if (!$ownerId) {
			return response()->json(['message' => 'Owner profile not found'], 404);
		}

		$existing = $this->refundService->getOwnerRefundById($ownerId, $refundId);

		if (!$existing) {
			return response()->json(['message' => 'Refund request not found'], 404);
		}

		try {
			$refund = $this->refundService->completeRefund($refundId, (int) $request->user()->id);
		} catch (RuntimeException $exception) {
			return response()->json(['message' => $exception->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Refund completed successfully',
			'data' => $refund,
		]);
	}

	public function cancelOwner(Request $request, int $refundId): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);

		if (!$ownerId) {
			return response()->json(['message' => 'Owner profile not found'], 404);
		}

		$existing = $this->refundService->getOwnerRefundById($ownerId, $refundId);

		if (!$existing) {
			return response()->json(['message' => 'Refund request not found'], 404);
		}

		try {
			$refund = $this->refundService->cancelRefund($refundId, (int) $request->user()->id, 'OWNER');
		} catch (RuntimeException $exception) {
			return response()->json(['message' => $exception->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Refund canceled successfully',
			'data' => $refund,
		]);
	}


	public function uploadInspection(Request $request, int $refundId): JsonResponse
	{
		$ownerId = $this->currentOwnerId($request);

		if (!$ownerId) {
			return response()->json(['message' => 'Owner not found'], 404);
		}

		$refund = $this->refundService->getOwnerRefundById($ownerId, $refundId);

		if (!$refund) {
			return response()->json(['message' => 'Refund not found'], 404);
		}

		if ($refund->status !== 'returning') {
			return response()->json([
				'message' => 'Inspection allowed only in returning status'
			], 422);
		}

		$request->validate([
			'images' => 'required|array|min:1',
			'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
			'note' => 'required|string|max:1000',
		]);

		// SAVE NOTE
		$refund->update([
			'inspection_note' => $request->note,
		]);

		// SAVE IMAGES
		foreach ($request->file('images') as $file) {
			$path = $file->store('refunds/inspection', 'public');

			RefundImage::create([
				'refund_id' => $refund->id,
				'image_path' => $path,
				'uploaded_by_type' => 'pharmacy',
				'uploaded_by_id' => $request->user()->id,
			]);
		}

		return response()->json([
			'message' => 'Inspection uploaded successfully',
			'data' => $refund->fresh(['inspectionImages']),
		]);
	}
}
