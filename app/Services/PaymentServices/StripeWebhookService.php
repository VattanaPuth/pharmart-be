<?php

namespace App\Services\PaymentServices;

interface StripeWebhookService
{
    public function handle(string $rawPayload, string $sigHeader): void;
}
