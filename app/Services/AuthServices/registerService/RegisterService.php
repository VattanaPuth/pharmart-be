<?php

namespace App\Services\AuthServices\registerService;

interface RegisterService
{
	public function sendOtp(string $phone): string;
	public function verifyOtp(string $pendingToken, string $code): void;
	public function complete(string $pendingToken, string $role): array;
	public function resendOtp(string $phone): void;
}
