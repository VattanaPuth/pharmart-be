<?php

namespace App\Services\AuthServices\otpService;

interface SmsSenderInterface
{
    public function smsSend(string $phoneE164, string $message): void;
}
