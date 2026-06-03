<?php

namespace App\Services\AuthServices\otpService\impl;

use App\Services\AuthServices\otpService\SmsSenderInterface;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client;

class TwilioSmsSender implements SmsSenderInterface {
     public function smsSend(string $phoneE164, string $message): void
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $messagingServiceSid = config('services.twilio.messaging_service_sid');

        if (empty($sid) || empty($token) || empty($messagingServiceSid)) {
            throw new \Exception('Twilio is not configured correctly.');
        }

        try {
            $client = new Client($sid, $token);

            $client->messages->create($phoneE164, [
                'messagingServiceSid' => $messagingServiceSid,
                'body' => $message,
            ]);
        } catch (\Throwable $e) {
        
            throw $e;
        }
    }
}
