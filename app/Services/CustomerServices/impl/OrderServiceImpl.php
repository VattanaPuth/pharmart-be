<?php

namespace App\Services\CustomerServices\impl;

use App\Enums\Notification\NotificationType;
use App\Mail\CustomerNotificationMail;
use App\Models\Customer\Cart;
use App\Models\Customer\Order;
use App\Services\CustomerServices\InvoiceService;
use App\Services\CustomerServices\OrderItemsService;
use App\Services\CustomerServices\PaymentOrderService;
use App\Services\CustomerServices\PaymentService;
use App\Services\CustomerServices\OrderService;
use App\Services\NotificationServices\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use App\Services\CustomerServices\RefundService;
use App\Models\Notification\Notification;
use App\Models\Owner\OwnerNotification;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationServices\impl\NotificationServiceImpl;
use Throwable;

class OrderServiceImpl implements OrderService
{
	public function __construct(
		private OrderItemsService $orderItemsService,
		private PaymentOrderService $paymentOrderService,
		private PaymentService $paymentService,
		private InvoiceService $invoiceService,
		private NotificationService $notificationService,
		private RefundService $refundService,
	) {}

	public function checkout(int $customerId, array $payload): array
	{

		return DB::transaction(function () use ($customerId, $payload): array {
			$cart = Cart::query()
				->where('customer_id', $customerId)
				->where('status', 'active')
				->with(['items.product'])
				->lockForUpdate()
				->first();

			if (!$cart) {
				throw new RuntimeException('Active cart not found.');
			}

			$cartItems = $cart->items;

			if ($cartItems->isEmpty()) {
				throw new RuntimeException('Cannot checkout an empty cart.');
			}

			$deliveryFee = (float) ($payload['delivery_fee'] ?? 0);

			if (($payload['fulfillment_method'] ?? 'pickup') === 'pickup') {
				$deliveryFee = 0;
			}

			$groupedByOwner = $cartItems->groupBy('owner_id');
			$createdOrderIds = [];
			$orderAmountRows = [];
			$grandTotal = 0.0;



			foreach ($groupedByOwner as $ownerId => $ownerItems) {
				$subtotal = (float) $ownerItems->sum(fn($item) => (float) $item->line_total);
				$total = round($subtotal + $deliveryFee, 2);

				$order = Order::query()->create([
					'customer_id' => $customerId,
					'owner_id' => (int) $ownerId,
					'status' => 'pending',
					'fulfillment_method' => $payload['fulfillment_method'],
					'payment_method' => $payload['payment_method'],
					'payment_status' => 'pending',
					'subtotal' => $subtotal,
					'delivery_fee' => $deliveryFee,
					'total' => $total,
				]);

				$this->orderItemsService->createFromCartItems($order->id, $ownerItems);
				$createdOrderIds[] = $order->id;
				$orderAmountRows[] = [
					'order_id' => $order->id,
					'amount' => $total,
				];
				$grandTotal += $total;
			}

			$payment = $this->paymentService->createPendingCombinedPayment(
				$customerId,
				$payload['payment_provider'],
				round($grandTotal, 2),
				$payload['currency'] ?? 'usd'
			);

			$this->paymentOrderService->createPaymentOrderRows($payment, $orderAmountRows);

			$providerResult = $this->paymentService->processWithProvider(
				$payload['payment_method'],
				$payload['payment_provider'],
				round($grandTotal, 2),
				$payload['provider_token'] ?? null,
			);

			$payment = $this->paymentService->finalizePayment(
				$payment,
				$providerResult['status'],
				$providerResult['transaction_id'] ?? null
			);

			[$orderPaymentStatus, $orderStatus] = $payment->status === 'success'
				? ['paid', 'confirmed']
				: ['pending', 'pending'];

			Order::query()
				->whereIn('id', $createdOrderIds)
				->update(array_filter([
					'payment_status' => $orderPaymentStatus,
					'status' => $orderStatus,
				], fn($value) => $value !== null));

			$generatedInvoices = [];
			if ($payment->status === 'success') {
				foreach ($createdOrderIds as $orderId) {
					$generatedInvoices[] = $this->invoiceService->generateInvoiceForCustomer($customerId, (int) $orderId);
				}
			}

			// Send a customer notification for each created order
			$isPaymentSuccess = $payment->status === 'success';
			$orders = Order::query()->whereIn('id', $createdOrderIds)->get()->keyBy('id');

			foreach ($createdOrderIds as $orderId) {
				$order = $orders->get($orderId);
				if (!$order) continue;

				[$notifType, $notifTitle, $notifMsg] = $isPaymentSuccess
					? [
						NotificationType::ORDER_CONFIRMED,
						'Order Confirmed',
						"Your order #{$orderId} has been confirmed and is being prepared.",
					]
					: [
						NotificationType::ORDER_PENDING,
						'Order Received',
						"Your order #{$orderId} has been received and is awaiting payment confirmation.",
					];

				// try {
				// 	$this->notificationService->createNotification([
				// 		'customer_id' => $order->customer_id,
				// 		'order_id'    => $order->id,
				// 		'owner_id'    => $order->owner_id,
				// 		'type'        => $notifType,
				// 		'title'       => $notifTitle,
				// 		'message'     => $notifMsg,
				// 		'target_role' => 'customer',
				// 	]);
				// } catch (\Throwable) {
				// }
			}

			$cart->update(['status' => 'checked_out']);

			$orders = Order::query()
				->with(['items', 'payments'])
				->whereIn('id', $createdOrderIds)
				->orderBy('id')
				->get();

			return [
				'orders' => $orders,
				'payment' => $payment->fresh('orders'),
				'invoices' => $generatedInvoices,
				'provider_result' => $providerResult,
			];
		});
	}

	public function getOrders(int $customerId, array $filters): LengthAwarePaginator
	{
		$query = Order::query()
			->where('customer_id', $customerId)
			->with([
				'owner:id',
				'owner.setting:id,owner_id,pharmacy_name',
				'items:id,order_id,product_name,quantity,package_name',
				'refund:id,order_id,status,refund_type,refund_amount',
			])
			->orderByDesc('created_at');

		if (!empty($filters['status'])) {
			$query->where('status', $filters['status']);
		}

		if (!empty($filters['refund_status'])) {
			$query->whereHas('refund', function ($q) use ($filters) {
				$q->where('status', $filters['refund_status']);
			});
		}


		$perPage = min((int) ($filters['per_page'] ?? 15), 50);

		return $query->paginate($perPage); // ✅ MUST be paginate
	}

	public function getOrderById(int $customerId, int $orderId): ?Order
	{
		return Order::query()
			->where('id', $orderId)
			->where('customer_id', $customerId)
			->with([
				'owner.setting.businessHours', // ✅ ADD THIS
				'items.product',
				'payment',
				'refund',
				'refund.items',
				'refund.payment',
				'refund.images',
			])
			->first();
	}

	public function updateStatus(int $ownerId, int $orderId, string $status): Order
	{
		return $this->advanceStatus($ownerId, $orderId, $status);
	}

	private function advanceStatus(
		int $ownerId,
		int $orderId,
		string $nextStatus,
		?string $reason = null
	): Order {
		$order = Order::query()
			->where('id', $orderId)
			->where('owner_id', $ownerId)
			->first();

		if (!$order) {
			throw new RuntimeException('Order not found for this owner.');
		}

		if ($order->status === 'completed') {
			throw new RuntimeException('Completed orders cannot be modified.');
		}

		$current = $order->status;

		$allowedTransitions = [
			'pending' => ['confirmed', 'declined'],

			'confirmed' => match ($order->fulfillment_method) {
				'pickup' => ['ready'],
				'delivery' => ['delivering'],
				default => [],
			},

			'ready' => ['processing_completion'],
			'delivering' => ['processing_completion'],
		];

		$allowedNext = $allowedTransitions[$current] ?? [];

		if (!in_array($nextStatus, $allowedNext, true)) {
			throw new RuntimeException(
				"Cannot change order from '{$current}' to '{$nextStatus}'"
			);
		}

		// decline
		if ($nextStatus === 'declined') {
			$order->decline_reason = $reason;
		}

		// pharmacy completed
		if ($nextStatus === 'processing_completion') {
			if ($order->pharmacy_completed_at) {
				throw new RuntimeException(
					'Pharmacy already marked this order complete.'
				);
			}
			$order->pharmacy_completed_at = now();
		}

		if ($nextStatus !== 'processing_completion') {
			$order->status = $nextStatus;
		}

		if ($nextStatus === 'confirmed') {
			$order->confirmed_at = now();

			//update product package qty
			foreach ($order->items as $item) {

				// package stock
				if ($item->package_id) {

					$package = \App\Models\Owner\OwnerPackage::query()
						->lockForUpdate()
						->find($item->package_id);

					if (!$package) {
						throw new RuntimeException(
							"Package not found for item {$item->product_name}"
						);
					}

					// insufficient stock
					if ($package->stock_quantity < $item->quantity) {
						throw new RuntimeException(
							"Insufficient stock for {$item->product_name}"
						);
					}

					// deduct
					$package->decrement(
						'stock_quantity',
						$item->quantity
					);
				}
			}
		}

		if ($nextStatus === 'ready') {
			$order->ready_at = now();
		}

		if ($nextStatus === 'delivering') {
			$order->ready_at = now();
		}
		$order->save();

		$this->checkCompletion($order);


		// =========================
		// CUSTOMER NOTIFICATION
		// =========================

		$title = 'Order Updated';
		$message = "Your order {$order->order_number} status was updated.";

		$type = 'order_updated';

		switch ($nextStatus) {

			case 'confirmed':
				$title = 'Order Accepted';
				$message =
					"Your order {$order->order_number} has been accepted by the pharmacy.";
				$type = 'order_confirmed';
				break;

			case 'declined':
				$title = 'Order Declined';
				$message =
					$reason
					? "Your order {$order->order_number} was declined. Reason: {$reason}"
					: "Your order {$order->order_number} was declined.";
				$type = 'order_declined';
				break;

			case 'ready':
				$title = 'Order Ready';
				$message =
					"Your order {$order->order_number} is ready for pickup.";
				$type = 'order_ready';
				break;

			case 'delivering':
				$title = 'Order Out for Delivery';
				$message =
					"Your order {$order->order_number} is out for delivery.";
				$type = 'order_delivering';
				break;

			case 'processing_completion':
				$title = 'Order Awaiting Confirmation';
				$message =
					"The pharmacy marked your order {$order->order_number} as completed. Please confirm receipt.";
				$type = 'order_processing_completion';
				break;
		}

		try {
			Notification::create([
				'customer_id' => $order->customer_id,

				'owner_id' => $order->owner_id,

				'order_id' => $order->id,

				'type' => $type,

				'title' => $title,

				'message' => $message,

				'target_role' => 'CUSTOMER',
			]);

			NotificationServiceImpl::customer(
				$order->customer,
				$type,
				[
					'order_id' => $order->id,
					'owner_id' => $order->owner_id,

					'title' => $title,
					'message' => $message,

					'channels' => ['database', 'mail'],
				]
			);
		} catch (Throwable $e) {
			Log::error('Notification failed', [
				'order_id' => $order->id,
				'error' => $e->getMessage()
			]);
		}
		//return $order->fresh();
		return $order->fresh(['payment']);
	}


	public function declineOrder(
		int $ownerId,
		int $orderId,
		string $reason
	): Order {
		$order = $this->advanceStatus(
			$ownerId,
			$orderId,
			'declined',
			$reason
		);

		try {
			if ($order->payment && $order->payment->status === 'success') {
				$this->refundService->createStripeRefund(
					$order->payment,
					(float) $order->total,
					[
						'order_id' => $order->id,
						'type' => 'pharmacy_decline',
					]
				);
			}
		} catch (Throwable $e) {
			Log::error('Refund failed', [
				'order_id' => $order->id,
				'error' => $e->getMessage()
			]);
		}

		return $order;
	}

	public function cancelByCustomer(int $customerId, int $orderId): Order
	{
		$order = Order::query()
			->where('id', $orderId)
			->where('customer_id', $customerId)
			->first();

		if (!$order) {
			throw new RuntimeException('Order not found.');
		}

		//  Important business rule
		$status = strtolower($order->status);

		if (!in_array($status, ['pending', 'confirmed'], true)) {
			throw new RuntimeException('Order cannot be cancelled at this stage.');
		}
		$order->update([
			'status' => 'cancelled',
			'customer_completed_at' => null,
			'pharmacy_completed_at' => null,
		]);

		// =========================
		// RESTORE STOCK
		// =========================

		if ($status === 'confirmed') {

			$order->load('items');

			foreach ($order->items as $item) {

				if ($item->package_id) {

					$package = \App\Models\Owner\OwnerPackage::query()
						->lockForUpdate()
						->find($item->package_id);

					if ($package) {

						$package->increment(
							'stock_quantity',
							$item->quantity
						);
					}
				}
			}
		}



		if (
			$order->payment &&
			$order->payment->status === 'success'
		) {

			$refundAmount = match ($status) {
				'pending' => $order->total,
				'confirmed' => round($order->total * 0.9, 2),
				default => 0,
			};

			if ($refundAmount > 0) {

				$this->refundService->createStripeRefund(
					$order->payment,
					$refundAmount,
					[
						'order_id' => $order->id,
						'type' => 'customer_cancel',
					]
				);
			}
		}

		// Optional: notification
		// try {

		// } catch (\Throwable) {

		// }


		return $order->fresh();
	}





	public function confirmReceived(
		int $customerId,
		int $orderId
	): Order {

		$order = Order::query()
			->where('id', $orderId)
			->where('customer_id', $customerId)
			->first();

		if (!$order) {
			throw new RuntimeException(
				'Order not found.'
			);
		}

		// =========================
		// MARK CUSTOMER RECEIVED
		// =========================
		$order->customer_completed_at = now();

		$order->save();

		// =========================
		// CHECK FINAL COMPLETION
		// =========================
		$this->checkCompletion($order);

		// =========================
		// OWNER NOTIFICATION
		// =========================
		OwnerNotification::create([
			'owner_id' => $order->owner_id,

			'customer_id' => $order->customer_id,

			'order_id' => $order->id,

			'type' => 'order_received',

			'title' => 'Order Received by Customer',

			'message' =>
			"Customer confirmed receiving order {$order->order_number}.",

			'channels' => [
				'web',
				'email',
			],

			'data' => [
				'order_id' => $order->id,
				'order_number' => $order->order_number,
				'status' => $order->status,
				'received_at' => $order->customer_completed_at,
			],
		]);

		return $order->fresh();
	}

	private function checkCompletion(Order $order): void
	{
		if ($order->pharmacy_completed_at && $order->customer_completed_at) {

			if ($order->status !== 'completed') {
				$order->status = 'completed';
				$order->payment_status = 'paid';
				$order->completed_at = now();
				$order->save();
			}
		}
	}

	public function progressOrder(
		int $ownerId,
		int $orderId
	): Order {
		$order = Order::query()
			->where('id', $orderId)
			->where('owner_id', $ownerId)
			->first();

		if (!$order) {
			throw new RuntimeException(
				'Order not found for this owner.'
			);
		}

		$nextStatus = match ($order->fulfillment_method) {
			'pickup' => 'ready',
			'delivery' => 'delivering',
			default => throw new RuntimeException(
				'Invalid fulfillment method'
			),
		};

		return $this->advanceStatus(
			$ownerId,
			$orderId,
			$nextStatus
		);
	}

	public function confirmOrder(
		int $ownerId,
		int $orderId
	): Order {
		return $this->advanceStatus(
			$ownerId,
			$orderId,
			'confirmed'
		);
	}

	public function completeOrder(
		int $ownerId,
		int $orderId
	): Order {
		return $this->advanceStatus(
			$ownerId,
			$orderId,
			'processing_completion'
		);
	}
}
