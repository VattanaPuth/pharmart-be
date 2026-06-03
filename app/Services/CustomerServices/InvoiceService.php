<?php

namespace App\Services\CustomerServices;

use App\Models\Customer\Invoice;
use Illuminate\Support\Collection;

interface InvoiceService
{
	public function generateInvoiceForCustomer(int $customerId, int $orderId): Invoice;
	public function getInvoiceById(int $invoiceId, int $customerId): ?array;
	public function getInvoices(array $filters, int $customerId): Collection;
	public function getPrintableInvoice(int $invoiceId, int $customerId): ?array;
}
