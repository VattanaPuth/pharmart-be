<?php

namespace App\Services\CustomerServices\impl;

use App\Enums\Invoice\DeliveredMethod;
use App\Models\Customer\Invoice;
use App\Models\Customer\InvoiceItem;
use App\Models\Customer\Order;
use App\Models\Customer\Payment;
use App\Services\CustomerServices\InvoiceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceServiceImpl implements InvoiceService
{
	private function generateInvoice(int $orderId, int $paymentId): Invoice
	{
		return DB::transaction(function () use ($orderId, $paymentId): Invoice {
			$existing = Invoice::query()
				->with(['items', 'order', 'payment'])
				->where('order_id', $orderId)
				->first();

			if ($existing) {
				return $existing;
			}

			$order = Order::query()
				->with(['items', 'customer.information', 'customer.register', 'owner.setting'])
				->where('id', $orderId)
				->first();

			if (!$order) {
				throw new RuntimeException('Order not found for invoice generation.');
			}

			$payment = Payment::query()
				->where('id', $paymentId)
				->where('customer_id', $order->customer_id)
				->first();

			if (!$payment) {
				throw new RuntimeException('Payment not found for invoice generation.');
			}

			if ($payment->status !== 'success') {
				throw new RuntimeException('Invoice can only be generated for successful payments.');
			}

			if ((string) $order->payment_status !== 'paid') {
				throw new RuntimeException('Invoice can only be generated for paid orders.');
			}

			$customerInfo = $order->customer?->information;
			$customerRegister = $order->customer?->register;
			$ownerSetting = $order->owner?->setting;

			$invoice = Invoice::query()->create([
				'invoice_number' => $this->generateInvoiceNumber(),
				'order_id' => $order->id,
				'payment_id' => $payment->id,
				'customer_id' => $order->customer_id,
				'owner_id' => $order->owner_id,
				'order_number' => 'ORD-' . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
				'payment_ref' => $payment->transaction_id ?: ('PAY-' . $payment->id),
				'bill_to_name' => $customerInfo?->customer_name ?: ('Customer #' . $order->customer_id),
				'bill_to_email' => $customerInfo?->email ?: $customerRegister?->email,
				'bill_to_address' => $this->resolveBillToAddress($order),
				'from_name' => $ownerSetting?->pharmacy_name ?: ('Owner #' . $order->owner_id),
				'from_tax_id' => null,
				'from_address' => $this->resolveFromAddress($ownerSetting?->address, $ownerSetting?->city),
				'delivered_method' => in_array((string) $order->fulfillment_method, [DeliveredMethod::PICKUP->value, DeliveredMethod::DELIVERY->value], true)
					? (string) $order->fulfillment_method
					: DeliveredMethod::PICKUP->value,
				'invoice_date' => now()->toDateString(),
				'subtotal' => round((float) $order->subtotal, 2),
				'shipping_fee' => round((float) $order->delivery_fee, 2),
				'discount_amount' => 0,
				'tax_amount' => 0,
				'total' => round((float) $order->total, 2),
				'currency' => strtolower((string) ($payment->currency ?: 'usd')),
				'notes' => null,
				'issued_at' => now(),
			]);

			foreach ($order->items as $item) {
				InvoiceItem::query()->create([
					'invoice_id' => $invoice->id,
					'order_item_id' => $item->id,
					'product_id' => $item->product_id,
					'item_name' => $item->product_name,
					'item_description' => null,
					'quantity' => (int) $item->quantity,
					'unit_price' => round((float) $item->unit_price, 2),
					'line_total' => round((float) $item->line_total, 2),
					'created_at' => now(),
				]);
			}

			return $invoice->fresh(['items', 'order', 'payment', 'owner', 'customer']);
		});
	}

	public function generateInvoiceForCustomer(int $customerId, int $orderId): Invoice
	{
		$order = Order::query()
			->where('id', $orderId)
			->where('customer_id', $customerId)
			->with('payments')
			->first();

		if (!$order) {
			throw new RuntimeException('Order not found.');
		}

		if ((string) $order->payment_status !== 'paid') {
			throw new RuntimeException('Invoice can only be generated for paid orders.');
		}

		$payment = $order->payments->first();

		if (!$payment) {
			throw new RuntimeException('No payment record found for this order.');
		}

		return $this->generateInvoice($orderId, $payment->id);
	}

	public function getInvoiceById(int $invoiceId, int $customerId): ?array
	{
		$invoice = $this->scopedInvoiceQuery($customerId)
			->where('id', $invoiceId)
			->first();

		if (!$invoice) {
			return null;
		}

		return $this->toInvoiceDetailResponse($invoice);
	}

	public function getInvoices(array $filters, int $customerId): Collection
	{
		$query = $this->scopedInvoiceQuery($customerId);

		if (!empty($filters['invoice_number'])) {
			$query->where('invoice_number', 'like', '%' . $filters['invoice_number'] . '%');
		}

		if (!empty($filters['order_number'])) {
			$query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
		}

		if (!empty($filters['invoice_date_from'])) {
			$query->whereDate('invoice_date', '>=', $filters['invoice_date_from']);
		}

		if (!empty($filters['invoice_date_to'])) {
			$query->whereDate('invoice_date', '<=', $filters['invoice_date_to']);
		}

		if (!empty($filters['delivered_method']) && in_array($filters['delivered_method'], ['pickup', 'delivery'], true)) {
			$query->where('delivered_method', $filters['delivered_method']);
		}

		return $query
			->orderByDesc('invoice_date')
			->orderByDesc('id')
			->get()
			->map(fn (Invoice $invoice) => [
				'id' => $invoice->id,
				'invoice_number' => $invoice->invoice_number,
				'invoice_date' => optional($invoice->invoice_date)->toDateString(),
				'order_number' => $invoice->order_number,
				'payment_ref' => $invoice->payment_ref,
				'bill_to_name' => $invoice->bill_to_name,
				'from_name' => $invoice->from_name,
				'delivered_method' => $invoice->delivered_method?->value ?? $invoice->delivered_method,
				'total' => (float) $invoice->total,
				'currency' => $invoice->currency,
			]);
	}

	public function getPrintableInvoice(int $invoiceId, int $customerId): ?array
	{
		$detail = $this->getInvoiceById($invoiceId, $customerId);

		if (!$detail) {
			return null;
		}

		$detail['print'] = [
			'title' => 'Invoice ' . $detail['header']['invoice_number'],
			'print_ready' => true,
			'generated_at' => now()->toIso8601String(),
		];

		return $detail;
	}

	private function scopedInvoiceQuery(int $customerId)
	{
		return Invoice::query()
			->with(['items', 'order', 'payment', 'owner', 'customer'])
			->where('customer_id', $customerId);
	}

	private function toInvoiceDetailResponse(Invoice $invoice): array
	{
		return [
			'id' => $invoice->id,
			'header' => [
				'invoice_number' => $invoice->invoice_number,
				'invoice_date' => optional($invoice->invoice_date)->toDateString(),
				'issued_at' => optional($invoice->issued_at)->toIso8601String(),
				'delivery_badge' => strtoupper((string) ($invoice->delivered_method?->value ?? $invoice->delivered_method)),
				'company' => [
					'name' => $invoice->from_name,
					'tax_id' => $invoice->from_tax_id,
					'address' => $invoice->from_address,
				],
			],
			'bill_to' => [
				'name' => $invoice->bill_to_name,
				'email' => $invoice->bill_to_email,
				'address' => $invoice->bill_to_address,
			],
			'from' => [
				'name' => $invoice->from_name,
				'tax_id' => $invoice->from_tax_id,
				'address' => $invoice->from_address,
			],
			'order_payment' => [
				'order_number' => $invoice->order_number,
				'payment_reference' => $invoice->payment_ref,
			],
			'items' => $invoice->items->map(fn (InvoiceItem $item) => [
				'id' => $item->id,
				'order_item_id' => $item->order_item_id,
				'product_id' => $item->product_id,
				'item_name' => $item->item_name,
				'item_description' => $item->item_description,
				'quantity' => $item->quantity,
				'unit_price' => (float) $item->unit_price,
				'line_total' => (float) $item->line_total,
			])->values()->all(),
			'summary' => [
				'subtotal' => (float) $invoice->subtotal,
				'shipping_fee' => (float) $invoice->shipping_fee,
				'discount_amount' => (float) $invoice->discount_amount,
				'tax_amount' => (float) $invoice->tax_amount,
				'total' => (float) $invoice->total,
				'currency' => $invoice->currency,
			],
			'footer' => [
				'note' => $invoice->notes,
			],
		];
	}

	private function resolveBillToAddress(Order $order): ?string
	{
		$address = $order->customer?->deliveryAddresses()
			->latest('id')
			->first();

		if (!$address) {
			return null;
		}

		return trim(implode(', ', array_filter([
			$address->full_address,
			$address->city,
		])));
	}

	private function resolveFromAddress(?string $address, ?string $city): ?string
	{
		$value = trim(implode(', ', array_filter([$address, $city])));
		return $value !== '' ? $value : null;
	}

	private function generateInvoiceNumber(): string
	{
		do {
			$candidate = 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
		} while (Invoice::query()->where('invoice_number', $candidate)->exists());

		return $candidate;
	}

}
