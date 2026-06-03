<?php

namespace App\Services\CustomerServices\impl;

use App\Enums\Notification\NotificationType;
use App\Mail\CustomerNotificationMail;
use App\Models\Customer\Order;
use App\Models\Customer\OrderItems;
use App\Models\Customer\Refund;
use App\Models\Customer\RefundItem;
use App\Models\Customer\RefundImage;
use Illuminate\Support\Facades\Storage;
use App\Services\CustomerServices\RefundService;
use App\Services\NotificationServices\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Support\Facades\Log;
use App\Models\Customer\Payment;
use App\Models\Notification\Notification;
use App\Services\NotificationServices\impl\NotificationServiceImpl;
use App\Models\Owner\OwnerNotification;

class RefundServiceImpl implements RefundService
{
	public function __construct(private NotificationService $notificationService) {}


	public function createRefundRequest(int $customerId, int $requestedBy, array $payload): Refund
	{
		return DB::transaction(function () use ($customerId, $requestedBy, $payload): Refund {

			$order = Order::query()
				->with(['items', 'payment'])
				->where('id', (int) $payload['order_id'])
				->where('customer_id', $customerId)
				->first();

			if (!$order) {
				throw new RuntimeException('Order not found for this customer.');
			}

			$paymentId = isset($payload['payment_id'])
				? (int) $payload['payment_id']
				: (int) $order->payment?->id;

			if (isset($payload['payment_id']) && $order->payment?->id !== $paymentId) {
				throw new RuntimeException('Payment does not belong to this order.');
			}

			$itemsPayload = $payload['items'] ?? [];
			$refundType = $payload['refund_type'];

			if ($refundType === 'partial' && empty($itemsPayload)) {
				throw new RuntimeException('Partial refund requires at least one refund item.');
			}

			$lineRows = [];
			$computedAmount = 0.0;

			foreach ($itemsPayload as $itemPayload) {
				$orderItem = OrderItems::query()
					->where('id', (int) $itemPayload['order_item_id'])
					->where('order_id', $order->id)
					->first();

				if (!$orderItem) {
					throw new RuntimeException('Invalid order item for this order.');
				}

				$quantity = (int) $itemPayload['quantity'];

				if ($quantity <= 0 || $quantity > (int) $orderItem->quantity) {
					throw new RuntimeException('Refund quantity is invalid for one or more items.');
				}

				$unitPrice = round((float) $orderItem->unit_price, 2);
				$lineAmount = round($unitPrice * $quantity, 2);

				$lineRows[] = [
					'order_item_id' => $orderItem->id,
					'product_id' => $orderItem->product_id,
					'quantity' => $quantity,
					'unit_price' => $unitPrice,
					'line_refund_amount' => $lineAmount,
				];

				$computedAmount += $lineAmount;
			}

			if ($refundType === 'full') {
				if (empty($lineRows)) {
					foreach ($order->items as $orderItem) {
						$unitPrice = round((float) $orderItem->unit_price, 2);
						$lineAmount = round($unitPrice * (int) $orderItem->quantity, 2);

						$lineRows[] = [
							'order_item_id' => $orderItem->id,
							'product_id' => $orderItem->product_id,
							'quantity' => (int) $orderItem->quantity,
							'unit_price' => $unitPrice,
							'line_refund_amount' => $lineAmount,
						];

						$computedAmount += $lineAmount;
					}
				}

				$computedAmount = round((float) $order->total, 2);
			}

			$requestedAmount = isset($payload['refund_amount'])
				? round((float) $payload['refund_amount'], 2)
				: round($computedAmount, 2);

			if ($requestedAmount <= 0) {
				throw new RuntimeException('Refund amount must be greater than zero.');
			}

			if ($requestedAmount > round((float) $order->total, 2)) {
				throw new RuntimeException('Refund amount cannot exceed order total.');
			}

			$refund = Refund::query()->create([
				'order_id' => $order->id,
				'payment_id' => $paymentId ?: null,
				'customer_id' => $customerId,
				'owner_id' => (int) $order->owner_id,
				'refund_number' => $this->generateRefundNumber(),
				'reason' => $payload['reason'],
				'note' => $payload['note'] ?? null,
				'status' => 'requested',
				'refund_type' => $refundType,
				'refund_amount' => $requestedAmount,
				'requested_by' => $requestedBy,
				'reviewed_by' => null,
				'requested_at' => now(),
				'reviewed_at' => null,
				'processed_at' => null,
			]);

			// =========================
			// HANDLE REFUND IMAGES (MAX 3)
			// =========================
			$images = $payload['images'] ?? [];
			if ($images instanceof \Illuminate\Http\UploadedFile) {
				$images = [$images]; // convert single file → array
			}

			if (count($images) > 3) {
				throw new RuntimeException('Maximum 3 images allowed per refund.');
			}

			foreach ($images as $image) {

				// store file
				$path = $image->store('refunds', 'public');

				// save record
				RefundImage::create([
					'refund_id' => $refund->id,
					'image_path' => $path,
					'uploaded_by_id' => $requestedBy,
					'uploaded_by_type' => 'customer',
				]);
			}

			foreach ($lineRows as $row) {
				RefundItem::query()->create([
					'refund_id' => $refund->id,
					'order_item_id' => $row['order_item_id'],
					'product_id' => $row['product_id'],
					'quantity' => $row['quantity'],
					'unit_price' => $row['unit_price'],
					'line_refund_amount' => $row['line_refund_amount'],
					'created_at' => now(),
				]);
			}

			try {

				Notification::create([
					'customer_id' => $refund->customer_id,
					'order_id'    => $refund->order_id,
					'refund_id'   => $refund->id,
					'owner_id'    => $refund->owner_id,
					'type' => 'refund_returning',
					'title' => 'Return In Progress',
					'message' =>
					"Your refund request #{$refund->refund_number} is now in the returning process.",
					'target_role' => 'customer',
					'is_read' => false,
				]);

				NotificationServiceImpl::customer(
					$refund->customer,
					NotificationType::REFUND_REQUESTED->value,
					[
						'order_id'  => $refund->order_id,
						'refund_id' => $refund->id,
						'owner_id'  => $refund->owner_id,
						'title' => 'Refund Request Submitted',
						'message' =>
						"Your refund request #{$refund->refund_number} has been received and is pending review.",
						'channels' => ['database', 'mail'],
					]
				);

				OwnerNotification::create([
					'owner_id' => $refund->owner_id,
					'customer_id' => $refund->customer_id,
					'order_id' => $refund->order_id,
					'refund_id' => $refund->id,
					'type' => 'refund_requested',
					'title' => 'New Refund Request',
					'message' =>
					"A new refund request #{$refund->refund_number} has been submitted by the customer and is awaiting review.",
					'channels' => [
						'web',
						'email',
					],
					'data' => [
						'refund_id' => $refund->id,
						'order_id' => $refund->order_id,
						'customer_id' => $refund->customer_id,
						'status' => 'requested',
					],

					'is_read' => false,
				]);

				NotificationServiceImpl::owner(
					$refund->owner,
					NotificationType::REFUND_REQUESTED->value,
					[
						'customer_id' => $refund->customer_id,
						'order_id'    => $refund->order_id,
						'refund_id'   => $refund->id,
						'title' => 'New Refund Request',
						'message' =>
						"A new refund request #{$refund->refund_number} has been submitted by the customer and is awaiting review.",
						'channels' => ['web', 'email'],
					]
				);
			} catch (\Throwable) {
			}

			return $refund->fresh(['items', 'order', 'payment']);
		});
	}


	public function createStripeRefund(
		Payment $payment,
		float $amount,
		array $metadata = []
	): \Stripe\Refund {

		if (!$payment->stripe_charge_id) {
			throw new RuntimeException('Missing Stripe charge ID.');
		}

		$stripe = new \Stripe\StripeClient(config('stripe.secret'));

		try {

			Log::info('STRIPE REFUND PAYLOAD', [
				'payment_id' => $payment->id,
				'amount' => $amount,
				'amount_cents' => (int) round($amount * 100),
				'charge_id' => $payment->stripe_charge_id,
			]);

			return $stripe->refunds->create([
				'charge' => $payment->stripe_charge_id,
				'amount' => (int) round($amount * 100),
				'metadata' => $metadata,
			]);
		} catch (\Throwable $e) {

			throw new RuntimeException(
				'Stripe refund failed: ' . $e->getMessage()
			);
		}
	}

	public function getCustomerRefundById(int $customerId, int $refundId): ?Refund
	{
		$refund = Refund::query()
			->with([
				'items.orderItem',
				'order',
				'payment',
				'images',
			])
			->where('id', $refundId)
			->where('customer_id', $customerId)
			->first();

		if (!$refund) {
			return null;
		}

		// ITEMS transform (OK)
		$refund->items = $refund->items->map(function ($item) {
			return array_merge(
				$item->orderItem->toArray(),
				[
					'refund_item_id' => $item->id,
					'refund_quantity' => $item->quantity,
					'refund_unit_price' => $item->unit_price,
					'line_refund_amount' => $item->line_refund_amount,
				]
			);
		});



		unset($refund->images);

		return $refund;
	}

	public function listForOwner(int $ownerId): Collection
	{
		return Refund::query()
			->with([
				'items',
				'items.product.ownerSetting',
				'order',
				'payment',
				'customer.information',
				'owner',
				'images'
			])
			->where('owner_id', $ownerId)
			->orderByDesc('requested_at')
			->get();
	}

	public function getOwnerRefundById(int $ownerId, int $refundId): ?Refund
	{
		$refund = Refund::query()
			->with([
				'items.product.ownerSetting',
				'items.product.defaultPackage',
				'order',
				'payment',
				'customer.information',
				'owner',
				'images'
			])
			->where('owner_id', $ownerId)
			->where('id', $refundId)
			->first();

		if (!$refund) {
			return null;
		}

		//  GROUP EVIDENCE
		unset($refund->images);
		return $refund;
	}



	public function reviewRefund(
		int $refundId,
		int $reviewedBy
	): Refund {

		$refund = Refund::query()
			->find($refundId);

		if (!$refund) {
			throw new RuntimeException(
				'Refund request not found.'
			);
		}

		if ($refund->status !== 'requested') {
			throw new RuntimeException(
				'Only requested refunds can be approved.'
			);
		}

		$refund->update([
			'status' => 'approved',
			'reviewed_by' => $reviewedBy,
			'reviewed_at' => now(),
		]);

		// =========================
		// CUSTOMER NOTIFICATION
		// =========================
		Notification::create([
			'customer_id' => $refund->customer_id,
			'order_id' => $refund->order_id,
			'refund_id' => $refund->id,
			'owner_id' => $refund->owner_id,
			'type' => 'refund_approved',
			'title' => 'Refund Approved',
			'message' =>
			"Your refund request #{$refund->refund_number} has been approved. Please return the item(s).",
			'target_role' => 'CUSTOMER',
		]);

		return $refund->fresh([
			'items',
			'order',
			'payment',
			'customer',
			'owner',
		]);
	}

	public function processRefund(
		int $refundId,
		int $reviewedBy
	): Refund {

		$refund = Refund::query()->find($refundId);

		if (!$refund) {
			throw new RuntimeException(
				'Refund request not found.'
			);
		}

		if ($refund->status !== 'approved') {
			throw new RuntimeException(
				'Only approved refunds can be marked as returning.'
			);
		}

		$refund->update([
			'status' => 'returning',
			'reviewed_by' => $reviewedBy,
			'processed_at' => now(),
		]);

		// =========================
		// CUSTOMER NOTIFICATION
		// =========================
		Notification::create([
			'customer_id' => $refund->customer_id,
			'order_id' => $refund->order_id,
			'refund_id' => $refund->id,
			'owner_id' => $refund->owner_id,
			'type' => 'refund_returning',
			'title' => 'Return In Progress',
			'message' =>
			"Your refund request #{$refund->refund_number} is now in the returning process.",
			'target_role' => 'CUSTOMER',
		]);

		NotificationServiceImpl::customer(
			$refund->customer,
			NotificationType::REFUND_RETURNING->value,
			[
				'order_id'  => $refund->order_id,
				'refund_id' => $refund->id,
				'owner_id'  => $refund->owner_id,

				'title' => 'Return In Progress',

				'message' =>
				"Your refund request #{$refund->refund_number} is now in the returning process.",

				'channels' => ['database', 'mail'],
			]
		);

		return $refund->fresh([
			'items',
			'order',
			'payment',
			'customer',
			'owner',
		]);
	}



	public function verifyRefund(
		int $refundId,
		int $reviewedBy
	): Refund {

		$refund = Refund::query()->find($refundId);

		if (!$refund) {
			throw new RuntimeException(
				'Refund request not found.'
			);
		}

		if ($refund->status !== 'returning') {
			throw new RuntimeException(
				'Only returning refunds can be verified.'
			);
		}

		$refund->update([
			'status' => 'verified',
			'reviewed_by' => $reviewedBy,
			'processed_at' => now(),
		]);

		// =========================
		// CUSTOMER NOTIFICATION
		// =========================
		Notification::create([
			'customer_id' => $refund->customer_id,
			'order_id' => $refund->order_id,
			'refund_id' => $refund->id,
			'owner_id' => $refund->owner_id,
			'type' => 'refund_verified',
			'title' => 'Refund Inspection Completed',
			'message' =>
			"Your returned item for refund #{$refund->refund_number} has been inspected and verified. It is now moving to refund approval.",
			'target_role' => 'CUSTOMER',
		]);

		NotificationServiceImpl::customer(
			$refund->customer,
			NotificationType::REFUND_VERIFIED->value,
			[
				'order_id'  => $refund->order_id,
				'refund_id' => $refund->id,
				'owner_id'  => $refund->owner_id,

				'title' => 'Refund Inspection Completed',

				'message' =>
				"Your returned item for refund #{$refund->refund_number} has been inspected and verified. It is now moving to refund approval.",

				'channels' => ['database', 'mail'],
			]
		);

		return $refund->fresh([
			'items',
			'order',
			'payment',
			'customer',
			'owner',
		]);
	}

	public function completeRefund(
		int $refundId,
		int $reviewedBy
	): Refund {

		return DB::transaction(function () use ($refundId, $reviewedBy) {

			$refund = Refund::where('id', $refundId)
				->lockForUpdate()
				->with(['customer.information', 'payment'])
				->first();

			if (!$refund) {
				throw new RuntimeException(
					'Refund request not found.'
				);
			}

			if ($refund->status === 'refunded') {
				throw new RuntimeException(
					'Refund already processed.'
				);
			}

			if ($refund->status !== 'verified') {
				throw new RuntimeException(
					'Only verified refunds can be marked as refunded.'
				);
			}

			if (
				!$refund->payment ||
				!$refund->payment->stripe_charge_id
			) {
				throw new RuntimeException(
					'Missing Stripe Charge for this refund.'
				);
			}

			$stripe = new \Stripe\StripeClient(
				config('stripe.secret')
			);

			try {

				$stripeRefund = $stripe->refunds->create([
					'charge' => $refund->payment->stripe_charge_id,

					'amount' =>
					(int) round(
						$refund->refund_amount * 100
					),

					'metadata' => [
						'our_refund_id' => $refund->id,
					],
				]);

				$refund->update([
					'stripe_refund_id' =>
					$stripeRefund->id,
					'status' => 'refunded',
					'reviewed_by' => $reviewedBy,
					'processed_at' => now(),
				]);

				// =========================
				// CUSTOMER NOTIFICATION
				// =========================
				Notification::create([
					'customer_id' => $refund->customer_id,
					'order_id' => $refund->order_id,
					'refund_id' => $refund->id,
					'owner_id' => $refund->owner_id,
					'type' => 'refund_completed',
					'title' => 'Refund Completed',
					'message' =>
					"Your refund #{$refund->refund_number} has been successfully processed. The amount has been returned to your original payment method.",
					'target_role' => 'CUSTOMER',
				]);

				NotificationServiceImpl::customer(
					$refund->customer,
					NotificationType::REFUND_COMPLETED->value,
					[
						'order_id'  => $refund->order_id,
						'refund_id' => $refund->id,
						'owner_id'  => $refund->owner_id,

						'title' => 'Refund Completed',

						'message' =>
						"Your refund #{$refund->refund_number} has been successfully processed. The amount has been returned to your original payment method.",

						'channels' => ['database', 'mail'],
					]
				);

				// =========================
				// OWNER NOTIFICATION
				// =========================
				OwnerNotification::create([
					'owner_id' => $refund->owner_id,
					'customer_id' => $refund->customer_id,
					'order_id' => $refund->order_id,
					'refund_id' => $refund->id,
					'type' => 'refund_completed',
					'title' => 'Refund Processed',
					'message' =>
					"Refund #{$refund->refund_number} has been completed and money has been returned to the customer.",
					'channels' => [
						'web',
						'email',
					],

					'data' => [
						'refund_id' => $refund->id,
						'stripe_refund_id' => $stripeRefund->id,
						'amount' => $refund->refund_amount,
						'status' => 'refunded',
					],
				]);

				NotificationServiceImpl::owner(
					$refund->owner,
					'refund_completed',
					[
						'customer_id' => $refund->customer_id,
						'order_id'    => $refund->order_id,
						'refund_id'   => $refund->id,

						'title' => 'Refund Processed',

						'message' =>
						"Refund #{$refund->refund_number} has been completed and money has been returned to the customer.",

						'channels' => ['web', 'email'],

						'data' => [
							'refund_id'       => $refund->id,
							'stripe_refund_id' => $stripeRefund->id,
							'amount'          => $refund->refund_amount,
							'status'          => 'refunded',
						],
					]
				);
			} catch (\Throwable $e) {

				throw new RuntimeException(
					'Stripe refund failed: ' . $e->getMessage()
				);
			}

			return $refund->fresh([
				'items',
				'order',
				'payment',
				'customer',
				'owner',
			]);
		});
	}

	public function cancelRefund(int $refundId, int $canceledBy, string $canceledByRole): Refund
	{
		$refund = Refund::query()->find($refundId);

		if (!$refund) {
			throw new RuntimeException('Refund request not found.');
		}

		if (!in_array($refund->status, ['requested', 'approved'])) {
			throw new RuntimeException('Only requested or approved refunds can be canceled.');
		}

		$refund->update([
			'status' => 'canceled',
			'reviewed_by' => $canceledBy,
			'reviewed_at' => now(),
		]);

		try {
			NotificationServiceImpl::customer(
				$refund->customer,
				NotificationType::REFUND_REJECTED->value,
				[
					'order_id'  => $refund->order_id,
					'refund_id' => $refund->id,
					'owner_id'  => $refund->owner_id,

					'title' => 'Refund Canceled',

					'message' =>
					"Your refund request #{$refund->refund_number} has been canceled.",

					'channels' => ['database', 'mail'],
				]
			);
		} catch (\Throwable) {
		}

		return $refund->fresh(['items', 'order', 'payment', 'customer', 'owner']);
	}

	private function generateRefundNumber(): string
	{
		return 'RFD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
	}
}
