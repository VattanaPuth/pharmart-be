<?php

namespace App\Http\Controllers\CustomerControllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerServices\OrderService;
use App\Models\Customer\OrderItems;
use App\Models\Customer\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Owner\OwnerNotification;
use RuntimeException;
use App\Services\NotificationServices\impl\NotificationServiceImpl;

class OrderController extends Controller
{
	public function __construct(private OrderService $orderService) {}

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

		$filters = $request->only(['status', 'per_page', 'refund_status']);

		$orders = $this->orderService
			->getOrders($customerId, $filters)
			->through(function ($order) {
				return [
					'id' => $order->id,
					'order_id' => 'ID' . $order->id,
					'order_number'=> $order->order_number,
					'status' => ucfirst($order->status),

					'pharmacy' =>
					$order->owner?->setting?->pharmacy_name ?? 'Unknown Pharmacy',

					'items' => $order->items->map(function ($item) {
						return [
							'name' => $item->product_name,
							'package_name' => $item->package_name,
							'quantity' => $item->quantity,
						];
					})->values(),

					'refund' => $order->refund ? [
						'status' => $order->refund->status,
						'refund_type' => $order->refund->refund_type,
						'refund_amount' => (float) $order->refund->refund_amount,
					] : null,

					'total_price' => (float) $order->total,
					'delivery_type' => ucfirst($order->fulfillment_method),
					'date' => $order->created_at->format('Y-m-d'),

				];
			});

		return response()->json($orders);
	}


	public function show(Request $request, int $orderId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$order = $this->orderService->getOrderById($customerId, $orderId)
			->load([
				'items',
				'reviews'
			]);

		if (!$order) {
			return response()->json(['message' => 'Order not found'], 404);
		}

		// ✅ Get today's business hour
		$today = strtolower(now()->format('l')); // monday, tuesday...

		$businessHour = optional(
			$order->owner?->setting?->businessHours
				->firstWhere('day_of_week', $today)
		);



		return response()->json([
			'data' => [
				'id' => $order->id,
				'order_id' => 'ORD-' . $order->id,
				'order_number'=> $order->order_number,
				'status' => ucfirst($order->status),
				'decline_reason' => $order->decline_reason ?? null,
				'date' => $order->created_at->format('Y-m-d'),

				'owner_id' => $order->owner->id,
				'pharmacy' =>
				$order->owner->setting->pharmacy_name ?? 'Unknown Pharmacy',
				'pharmacy_logo' => $order->owner->setting->logo,
				'customer_completed_at' => $order->customer_completed_at,
				'pharmacy_completed_at' => $order->pharmacy_completed_at,


				'fulfillment' => [
					'type' => ucfirst($order->fulfillment_method),

					// delivery
					'address' => $order->fulfillment_method === 'delivery'
						? $order->delivery_address
						: null,
					'courier' => $order->courier ?? null,
					'trackingId' => $order->tracking_id ?? null,

					// pickup
					'storeAddress' => $order->owner->setting->address ?? null,
					'openHour' => $businessHour?->open_time
						? substr($businessHour->open_time, 0, 5)
						: null,
					'closeHour' => $businessHour?->close_time
						? substr($businessHour->close_time, 0, 5)
						: null,
				],


				'items' => $order->items->map(function ($item) use ($order) {

					$review = $order->reviews->firstWhere('product_id', $item->product_id);

					return [
						'order_item_id' => $item->id,
						'product_id' => $item->product_id,
						'product_image' => $item->product_image,
						'name' => $item->product_name,
						'package_name' => $item->package_name,
						'quantity' => $item->quantity,
						'unit_price' => (float) $item->unit_price,
						'line_total' => (float) $item->line_total,

						// ✅ review per product (NOT package)
						'rating' => $review?->rating,
						'review' => $review?->review,
						'reviewed_at' => $review?->created_at,
					];
				})->values(),

				'status_history' => collect(json_decode($order->status_history, true))
					->map(fn($log) => [
						'status' => ucfirst($log['status']),
						'time' => $log['time'],
					])
					->values(),

				'totalAmount' => (float) $order->total,
				'delivery_fee' => (float) $order->delivery_fee,

				'payment_method' => match ($order->payment_method) {
					'online' => 'Card Payment',
					'pay_at_shop' => 'Pay at Shop',
					default => ucfirst($order->payment_method),
				},

				'invoice' => 'INV-' . $order->id,
				'paymentRef' => $order->payment_reference ?? null,
				'refund' => $order->refund ? [
					'id' => $order->refund->id,
					'status' => $order->refund->status,
					'refund_type' => $order->refund->refund_type,
					'refund_amount' => (float) $order->refund->refund_amount,
					'requested_at' => $order->refund->requested_at,
				] : null,
				'can_cancel' => in_array($order->status, ['pending', 'confirmed']),
				'can_review' => $order->status === 'completed',
				'can_track' => in_array($order->status, ['delivering', 'ready']),


			]
		]);
	}

	public function checkout(Request $request): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer profile not found'], 404);
		}

		$validated = $request->validate([
			'fulfillment_method' => 'required|in:pickup,delivery',
			'payment_method' => 'required|in:online,pay_at_shop',
			'payment_provider' => 'required|string|max:50',
			'currency' => 'nullable|string|size:3',
			'delivery_fee' => 'nullable|numeric|min:0',
			'provider_token' => 'nullable|string|max:255',
		]);

		try {
			$result = $this->orderService->checkout($customerId, $validated);
		} catch (RuntimeException $exception) {
			return response()->json(['message' => $exception->getMessage()], 422);
		} catch (\Throwable $exception) {
			return response()->json(['message' => $exception->getMessage()], 500);
		}

		return response()->json([
			'message' => 'Checkout processed successfully',
			'data' => $result,
		], 201);
	}

	public function cancel(Request $request, int $orderId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json([
				'message' => 'Customer profile not found'
			], 404);
		}

		try {
			$order = $this->orderService->cancelByCustomer($customerId, $orderId);


			NotificationServiceImpl::owner(
				$order->owner,
				'order_cancelled',
				[
					'customer_id' => $order->customer_id,
					'order_id' => $order->id,
					'order_number' => $order->order_number,

					'title' => 'Order Cancelled',

					'message' =>
					"Order {$order->order_number} was cancelled by customer.",

					'channels' => ['database', 'mail'],
				]
			);

			return response()->json([
				'message' => 'Order cancelled successfully',
				'data' => $order
			]);
		} catch (RuntimeException $e) {
			return response()->json([
				'message' => $e->getMessage()
			], 422);
		} catch (\Throwable $e) {
			return response()->json([
				'message' => 'Something went wrong'
			], 500);
		}
	}


	public function confirmReceived(
		Request $request,
		int $orderId
	): JsonResponse {

		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json([
				'message' => 'Customer profile not found'
			], 404);
		}

		try {

			$order = $this->orderService->confirmReceived(
				$customerId,
				$orderId
			);

			// =========================
			// OWNER NOTIFICATION
			// =========================
			OwnerNotification::create([
				'owner_id' => $order->owner_id,

				'customer_id' => $order->customer_id,

				'order_id' => $order->id,

				'type' => 'order_completed',

				'title' => 'Order Completed',

				'message' =>
				"Customer confirmed receipt for order {$order->order_number}.",

				'channels' => [
					'web',
					'email',
				],

				'data' => [
					'order_id' => $order->id,
					'order_number' => $order->order_number,
					'status' => $order->status,
					'payment_status' => $order->payment_status,
				],
			]);

			NotificationServiceImpl::owner(
				$order->owner,
				'order_completed',
				[
					'customer_id' => $order->customer_id,
					'order_id' => $order->id,
					'order_number' => $order->order_number,

					'title' => 'Order Completed',

					'message' =>
					"Customer confirmed receipt for order {$order->order_number}.",

					'channels' => ['web', 'email'],

					'status' => $order->status,
					'payment_status' => $order->payment_status,
				]
			);

			return response()->json([
				'message' => 'Order received confirmed',
				'data' => $order,
			]);
		} catch (RuntimeException $e) {

			return response()->json([
				'message' => $e->getMessage()
			], 422);
		} catch (\Throwable $e) {

			return response()->json([
				'message' => 'Something went wrong'
			], 500);
		}
	}


	public function submitReview(Request $request, int $orderId): JsonResponse
	{
		$customerId = $this->currentCustomerId($request);

		if (!$customerId) {
			return response()->json(['message' => 'Customer not found'], 404);
		}

		$validated = $request->validate([
			'items' => 'required|array',
			'items.*.product_id' => 'required|integer',
			'items.*.rating' => 'required|integer|min:1|max:5',
			'items.*.review' => 'nullable|string|max:1000',
		]);

		$order = $this->orderService->getOrderById($customerId, $orderId);

		if (!$order) {
			return response()->json(['message' => 'Order not found'], 404);
		}

		if ($order->status !== 'completed') {
			return response()->json([
				'message' => 'Order must be completed before reviewing'
			], 422);
		}

		DB::beginTransaction();

		try {

			// 🔥 STEP 1: GROUP BY PRODUCT ID (VERY IMPORTANT)
			$grouped = collect($validated['items'])
				->groupBy('product_id')
				->map(function ($items) {
					return [
						'product_id' => $items->first()['product_id'],
						'rating' => round($items->avg('rating')), // optional average
						'review' => collect($items)
							->pluck('review')
							->filter()
							->implode(" | ") // combine reviews if multiple lines
					];
				});

			foreach ($grouped as $item) {

				ProductReview::updateOrCreate(
					[
						'order_id' => $orderId,
						'product_id' => $item['product_id'],
						'customer_id' => $customerId,
					],
					[
						'rating' => $item['rating'],
						'review' => $item['review'] ?: null,
					]
				);
			}

			DB::commit();

			return response()->json([
				'message' => 'Review submitted successfully'
			]);
		} catch (\Throwable $e) {
			DB::rollBack();

			return response()->json([
				'message' => 'Failed to submit review',
				'error' => $e->getMessage()
			], 500);
		}
	}
}
