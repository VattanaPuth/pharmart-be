<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CartController extends Controller
{
	public function __construct(private CartService $cartService) {}

	private function currentCustomerId(Request $request): ?int
	{
		return $request->user()?->customer?->id;
	}

	public function index(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$perPage = min((int) $request->query('per_page', 15), 50);

		$result = $this->cartService->getCart($customerId, $perPage);

		return response()->json(['data' => $result]);
	}

	public function addToCart(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'product_id' => 'required|integer|exists:owner_product,id',
			'quantity' => 'required|integer|min:1',
			'package_id' => 'nullable|integer|exists:owner_package,id',
		]);

		try {
			$result = $this->cartService->addToCart(
				$customerId,
				(int) $validated['product_id'],
				(int) $validated['quantity'],
				isset($validated['package_id']) ? (int) $validated['package_id'] : null
			);
		} catch (RuntimeException $exception) {
			return response()->json([
				'message' => $exception->getMessage(),
			], 422);
		}

		return response()->json([
			'message' => 'Item added to cart successfully',
			'data' => $result,
		], 201);
	}


	public function updateItem(Request $request, int $cartItemId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'quantity' => 'required|integer|min:1',
		]);

		try {
			$result = $this->cartService->updateItem(
				$customerId,
				$cartItemId,
				(int) $validated['quantity']
			);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Cart item updated successfully',
			'data' => $result
		]);
	}


	public function removeItem(Request $request, int $cartItemId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		try {
			$this->cartService->removeItem($customerId, $cartItemId);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Item removed successfully'
		]);
	}

	public function removeProduct(Request $request, int $productId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		try {
			$this->cartService->removeProduct($customerId, $productId);
		} catch (RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 422);
		}

		return response()->json([
			'message' => 'Product removed from cart successfully'
		]);
	}

	public function getCartCount(Request $request)
{
    $customerId = $this->currentCustomerId($request);

    $count = $this->cartService->getCartItemCount($customerId);

    return response()->json([
        'success' => true,
        'count' => $count,
    ]);
}
}
