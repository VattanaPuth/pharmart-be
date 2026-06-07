<?php

namespace App\Services\AuthServices\otpService\impl;

use App\Services\AuthServices\otpService\OtpService;
use App\Services\AuthServices\otpService\SmsSenderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpServiceImpl implements OtpService
{

    private const MAX_ATTEMPTS = 5;
    private const MAX_SENDS_PER_DAY = 15;
    private const OTP_TTL = 5; // minutes
    private const COOLDOWN = 60; // seconds

    public function __construct(
        private SmsSenderInterface $sms,
    ) {}

    public function send(string $phoneE164): string
    {
        if (Cache::has($this->cooldownKey($phoneE164))) {
            throw new \DomainException('Please wait before resending OTP.');
        }

        $sendCount = (int) Cache::get($this->sendLimitKey($phoneE164), 0);

        if ($sendCount >= self::MAX_SENDS_PER_DAY) {
            throw new \DomainException('Daily OTP send limit reached. Please try again tomorrow.');
        }

        $code = (string) random_int(100000, 999999);

         Cache::put(
            $this->otpKey($phoneE164),
            hash('sha256', $code),
            now()->addMinutes(self::OTP_TTL)
        );

        Cache::put(
            $this->attemptsKey($phoneE164),
            0,
            now()->addMinutes(self::OTP_TTL)
        );

        Cache::put(
            $this->cooldownKey($phoneE164),
            true,
            now()->addSeconds(self::COOLDOWN)
        );

        Cache::put(
            $this->sendLimitKey($phoneE164),
            $sendCount + 1,
            now()->addDay()
        );

        $this->sms->smsSend(
            $phoneE164,
            "Your OTP is: {$code}"
        );

        return "OTP sent";
    }

    public function verify(string $phoneE164, string $code): void
    {
        $storedHash = Cache::get($this->otpKey($phoneE164));

        if (! $storedHash) {
            throw new \DomainException('OTP expired or not found. Please request a new OTP.');
        }

        $attempts = (int) Cache::get($this->attemptsKey($phoneE164), 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($this->otpKey($phoneE164));
            Cache::forget($this->attemptsKey($phoneE164));

            throw new \DomainException('Too many invalid attempts. Please request a new OTP.');
        }

        if (! hash_equals($storedHash, hash('sha256', $code))) {
            Cache::put($this->attemptsKey($phoneE164), $attempts + 1, now()->addMinutes(5));
            throw new \DomainException('Invalid OTP code.');
        }

        Cache::forget($this->otpKey($phoneE164));
        Cache::forget($this->attemptsKey($phoneE164));
        Cache::forget($this->cooldownKey($phoneE164));
    }

    private function otpKey(string $phone): string
    {
        return "otp:{$phone}";
    }

    private function attemptsKey(string $phone): string
    {
        return "otp_attempts:{$phone}";
    }

    private function cooldownKey(string $phone): string
    {
        return "otp_cooldown:{$phone}";
    }

    private function sendLimitKey(string $phone): string
    {
        return "otp_send_count:{$phone}";
    }
public function resend(string $phoneE164): string
{
    // OPTIONAL: reset attempts on resend (recommended UX improvement)
    Cache::put($this->attemptsKey($phoneE164), 0, now()->addMinutes(self::OTP_TTL));

    return $this->send($phoneE164);
}
}
