<?php

namespace App\Http\Controllers\PaymentControllers;

use App\Http\Controllers\Controller;
use App\Services\PaymentServices\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(private StripeWebhookService $webhookService) {}

    public function handle(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $sigHeader  = $request->header('Stripe-Signature', '');

        try {
            $this->webhookService->handle($rawPayload, $sigHeader);
        } catch (SignatureVerificationException) {
            return response('Webhook signature verification failed.', 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing error', ['error' => $e->getMessage()]);
            return response('Webhook handler error.', 500);
        }

        return response('OK', 200);
    }
}
