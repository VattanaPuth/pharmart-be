<?php
namespace App\Services\CustomerServices;

use App\Models\Customer\CustomerCheckoutSession;

interface CheckoutSessionServiceInterface
{
    public function createSession(int $customerId, array $storeIds): CustomerCheckoutSession;

    public function getSession(int $customerId, int $sessionId): CustomerCheckoutSession;

    public function updateSession(int $customerId, int $sessionId, array $data): CustomerCheckoutSession;

    public function confirmSession(int $customerId, int $sessionId): array;
}