<?php

namespace App\Services\AuthServices\otpService\impl;

use App\Services\AuthServices\otpService\SmsSenderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class InfobipSmsSender implements SmsSenderInterface
{
    /**
     * @throws \Throwable
     * @throws ConnectionException
     */
    public function smsSend(string $phoneE164, string $message): void
    {
        $baseUrl = rtrim((string) config('services.infobip.base_url'), '/');
        $apiKey = (string) config('services.infobip.api_key');
        $sender = (string) config('services.infobip.sender');

        if ($baseUrl === '' || $apiKey === '' || $sender === '') {
            throw new \Exception('Infobip is not configured correctly.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($baseUrl . '/sms/2/text/advanced', [
                'messages' => [[
                    'from' => $sender,
                    'destinations' => [['to' => $phoneE164]],
                    'text' => $message,
                ]],
            ]);
        } catch (\Throwable $e) {
            Cache::forget($phoneE164);
            throw $e;
        }

        if (! $response->successful()) {
            throw new \Exception('Failed to send SMS via Infobip: ' . $response->body());
        }
    }
}
