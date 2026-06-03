<?php

namespace App\Services\CustomerServices\impl;

use App\Models\Customer\Payment;
use App\Services\CustomerServices\PaymentService;
use Carbon\Carbon;
use RuntimeException;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Stripe\Exception\CardException;
use Stripe\Exception\ApiErrorException;

class PaymentServiceImpl implements PaymentService
{
	public function createPendingCombinedPayment(
		int $customerId,
		string $paymentProvider,
		float $amount,
		string $currency
	): Payment {
		return Payment::query()->create([
			'customer_id' => $customerId,
			'payment_provider' => $paymentProvider,
			'transaction_id' => null,
			'amount' => round($amount, 2),
			'currency' => strtolower($currency),
			'status' => 'pending',
		]);
	}

	public function processWithProvider(
		string $paymentMethod,
		string $paymentProvider,
		float $amount,
		?string $providerToken = null
	): array {
		if ($paymentMethod === 'pay_at_shop') {
			return [
				'status' => 'pending',
				'transaction_id' => null,
				'provider' => $paymentProvider,
				'amount' => round($amount, 2),
			];
		}

		if ($paymentMethod !== 'online') {
			throw new RuntimeException('Unsupported payment method.');
		}

		if ($paymentProvider === 'stripe') {
			return $this->processStripe($amount, $providerToken);
		}

		if ($paymentProvider === 'mock') {
			if ($providerToken !== null && strtolower($providerToken) === 'fail') {
				return [
					'status' => 'failed',
					'transaction_id' => 'MOCK-DECLINED-' . Str::upper(Str::random(12)),
					'provider' => 'mock',
					'amount' => round($amount, 2),
				];
			}

			return [
				'status' => 'success',
				'transaction_id' => 'MOCK-' . Carbon::now()->format('YmdHis') . '-' . Str::upper(Str::random(10)),
				'provider' => 'mock',
				'amount' => round($amount, 2),
			];
		}

		return [
			'status' => 'failed',
			'transaction_id' => strtoupper($paymentProvider) . '-FAILED-' . Str::upper(Str::random(12)),
			'provider' => $paymentProvider,
			'amount' => round($amount, 2),
			'reason' => 'Unsupported payment provider.',
		];
	}

	private function processStripe(float $amount, ?string $paymentMethodId): array
	{
		if (!$paymentMethodId) {
			throw new RuntimeException('A Stripe payment_method ID (provider_token) is required for online Stripe payments.');
		}

		$stripe = new StripeClient(config('stripe.secret'));

		try {
			$intent = $stripe->paymentIntents->create([
				'amount'   => (int) round($amount * 100),
				'currency' => strtolower((string) config('stripe.currency', 'usd')),
				'payment_method' => $paymentMethodId,
				'confirm' => true,
				'automatic_payment_methods' => [
					'enabled'         => true,
					'allow_redirects' => 'never',
				],
			]);

			return match ($intent->status) {
				'succeeded' => [
					'status'         => 'success',
					'transaction_id' => $intent->id,
					'provider'       => 'stripe',
					'amount'         => round($amount, 2),
				],
				'requires_action' => [
					// Stored as 'pending' in DB; frontend uses client_secret for 3DS
					'status'          => 'pending',
					'requires_action' => true,
					'transaction_id'  => $intent->id,
					'client_secret'   => $intent->client_secret,
					'provider'        => 'stripe',
					'amount'          => round($amount, 2),
				],
				default => [
					'status'         => 'failed',
					'transaction_id' => $intent->id,
					'provider'       => 'stripe',
					'amount'         => round($amount, 2),
				],
			};
		} catch (CardException $e) {
			return [
				'status'         => 'failed',
				'transaction_id' => null,
				'provider'       => 'stripe',
				'amount'         => round($amount, 2),
				'reason'         => $e->getMessage(),
			];
		} catch (ApiErrorException $e) {
			throw new RuntimeException('Stripe API error: ' . $e->getMessage());
		}
	}

	public function finalizePayment(Payment $payment, string $status, ?string $transactionId = null): Payment
	{
		$payload = [
			'status'         => $status,
			'transaction_id' => $transactionId,
		];

		if ($status === 'success') {
			$payload['paid_at'] = now();
		}

		$payment->update($payload);

		return $payment->fresh();
	}

}
