<?php

namespace App\Services\OwnerServices\impl;

use App\Models\Customer\Invoice;
use App\Models\Customer\Order;
use App\Services\CustomerServices\InvoiceService;
use App\Services\OwnerServices\OwnerInvoiceService;
use RuntimeException;

class OwnerInvoiceServiceImpl implements OwnerInvoiceService
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function generateInvoice(int $ownerId, int $orderId): Invoice
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->where('owner_id', $ownerId)
            ->with('payments')
            ->first();

        if (!$order) {
            throw new RuntimeException('Order not found for this owner.');
        }

        if ((string) $order->payment_status !== 'paid') {
            throw new RuntimeException('Invoice can only be issued for paid orders.');
        }

        $payment = $order->payments->first();

        if (!$payment) {
            throw new RuntimeException('No payment record found for this order.');
        }

        return $this->invoiceService->generateInvoiceForCustomer($order->customer_id, $orderId);
    }
}
