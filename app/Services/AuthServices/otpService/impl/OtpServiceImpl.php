<?php

namespace App\Services\AuthServices\otpService\impl;

use App\Services\AuthServices\otpService\OtpService;
use App\Services\AuthServices\otpService\SmsSenderInterface;
use Illuminate\Support\Facades\Cache;

class OtpServiceImpl implements OtpService
{

    private const MAX_ATTEMPTS = 5;
    private const OTP_TTL = 5; // minutes
    private const COOLDOWN = 60; // seconds

    public function __construct(
        private SmsSenderInterface $sms,
    ) {}

    public function send(string $phoneE164): string
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(
            $this->otpKey($phoneE164),
            hash('sha256', $code),
            now()->addMinutes(5)
        );

        Cache::put(
            $this->attemptsKey($phoneE164),
            0,
            now()->addMinutes(5)
        );

        Cache::put(
            $this->cooldownKey($phoneE164),
            true,
            now()->addSeconds(60)
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
public function resend(string $phoneE164): string
{
    if (Cache::has($this->cooldownKey($phoneE164))) {
        throw new \DomainException('Please wait before resending OTP.');
    }

    // OPTIONAL: reset attempts on resend (recommended UX improvement)
    Cache::put($this->attemptsKey($phoneE164), 0, now()->addMinutes(5));

    return $this->send($phoneE164);
}
}
