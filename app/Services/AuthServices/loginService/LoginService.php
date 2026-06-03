<?php

namespace App\Services\AuthServices\loginService;

interface LoginService
{
    public function sendOtp(string $phone): string;
    public function verifyOtp(string $pendingToken, string $code): array;
}
