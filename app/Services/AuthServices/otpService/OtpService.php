<?php

namespace App\Services\AuthServices\otpService;

interface OtpService
{
    public function send(string $phoneE164): string;
    public function resend(string $phoneE164): string;
    public function verify(string $phoneE164, string $code): void;
}
