<?php

namespace App\Services\AuthServices\loginService\impl;

use App\Models\Auth\Register;
use App\Services\AuthServices\loginService\LoginService;
use App\Services\AuthServices\otpService\OtpService;
use App\Services\AuthServices\utils\PhoneFormat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class LoginServiceImpl implements LoginService
{

    public function __construct(
        private PhoneFormat $phoneFormat,
        private OtpService $otpService,
    ) {}

    public function sendOtp(string $phone): string
    {
        $phone = $this->phoneFormat->normalizePhone($phone);

        $user = Register::where('phone', $phone)->first();

        if (! $user) {
            throw new \Exception('User not found');
        }

        // 1. generate token FIRST
        $pendingToken = (string) Str::uuid();

        // 2. store session
        Cache::put("pending_login:{$pendingToken}", [
            'phone' => $phone,
            'user_id' => $user->id,
            'otp_verified' => false,
        ], now()->addMinutes(10));

        // 3. send OTP (DO NOT break flow)
        try {
            $this->otpService->send($phone);
        } catch (\Throwable $e) {
            Log::error('Login OTP send failed: ' . $e->getMessage());
        }

        return $pendingToken;
    }

    public function verifyOtp(string $pendingToken, string $code): array
    {
        $pendingKey = "pending_login:{$pendingToken}";
        $pending = Cache::get($pendingKey);

        if (! $pending) {
            throw new \Exception('Session expired. Please request OTP again.');
        }

        $this->otpService->verify($pending['phone'], $code);

        $user = Register::find($pending['user_id']);

        if (! $user) {
            throw new \Exception('User not found');
        }

        // CREATE JWT WITH CLAIMS (same as Google)
        $token = JWTAuth::claims([
            'role' => $user->role,
            'onboarding' => $user->onboarding_completed ?? 0,
        ])->fromUser($user);

        Cache::forget($pendingKey);

        $owner = null;

        if ($user->role === \App\Enums\Auth\Role::OWNER->value) {
            $owner = \App\Models\Owner\Owner::where('register_id', $user->id)->first();
        }

        return [
            'user' => $user,
            'owner' => $owner,
            'token' => $token,

            // same structure as Google
            'requires_role_selection' => $user->role === null,

            'next_step' => $user->role
                ? 'LOGIN_SUCCESS'
                : 'SELECT_ROLE',
        ];
    }
}
