<?php

namespace App\Services\OwnerServices;

use App\Models\Customer\Invoice;

interface OwnerInvoiceService
{
    public function generateInvoice(int $ownerId, int $orderId): Invoice;
}
