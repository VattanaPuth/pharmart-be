<?php

namespace App\Services\AuthServices\registerService\impl;

use App\Enums\Auth\Role;
use App\Models\Auth\Register;
use App\Models\Customer\Customer;
use App\Models\Owner\Owner;
use App\Services\AuthServices\otpService\OtpService;
use App\Services\AuthServices\registerService\RegisterService;
use App\Services\AuthServices\utils\PhoneFormat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterServiceImpl implements RegisterService
{
    public function __construct(
        private PhoneFormat $phoneFormat,
        private OtpService $otpService,
    ) {}

    // send otp to phone number and store pending register data in cache with pending token as key
public function sendOtp(string $phone): string
{
    $phoneStr = $this->phoneFormat->normalizePhone($phone);

    // 1. generate token FIRST (must always succeed)
    $pendingToken = (string) Str::uuid();

    // 2. store session immediately
    Cache::put("pending_register:{$pendingToken}", [
        'phone' => $phoneStr,
        'otp_verified' => false,
    ], now()->addMinutes(10));

    Cache::put("pending_register_phone:{$phoneStr}", $pendingToken, now()->addMinutes(10));

    // 3. try sending OTP (DO NOT block token if it fails)
    try {
        $this->otpService->send($phoneStr);
    } catch (\DomainException $e) {
        throw $e;
    } catch (\Throwable $e) {
        // optional: log error, but DO NOT stop flow
        Log::error('OTP send failed: ' . $e->getMessage());
    }

    // 4. ALWAYS return token
    return $pendingToken;
}

    // after filled the otp code, click on sign in or sth like this
    public function verifyOtp(string $pendingToken, string $code): void
    {
        $pendingKey = "pending_register:{$pendingToken}";
        $pending = Cache::get($pendingKey);

        if (! $pending) {
            throw new \Exception('Session expired. Please request OTP again.');
        }

        $this->otpService->verify($pending['phone'], $code);

        Cache::put($pendingKey, [
            'phone' => $pending['phone'],
            'otp_verified' => true,
        ], now()->addMinutes(10));
    }

    // click on creat account after otp verified, then create user and return token
    public function complete(string $pendingToken, string $role): array
    {
        $pendingKey = "pending_register:{$pendingToken}";
        $pending = Cache::get($pendingKey);

        if (! $pending) {
            throw new \Exception('Session expired. Please request OTP again.');
        }

        if (! ($pending['otp_verified'] ?? false)) {
            throw new \Exception('Please verify OTP first');
        }

        $resolvedRole = Role::tryFrom($role);

        if (! $resolvedRole) {
            throw new \Exception('Invalid role');
        }

        $result = DB::transaction(function () use ($pending, $resolvedRole) {

            // prevent duplicate user (VERY IMPORTANT FIX)
            $user = Register::create([
                'phone' => $pending['phone'],
                'role' => $resolvedRole->value,
                'phone_verified_at' => now(),
                'onboarding_completed' => false, // ADD THIS
            ]);

            // ensure role updated
            if (! $user->role) {
                $user->role = $resolvedRole->value;
                $user->save();
            }

            $owner = null;
            $customer = null;

            if ($resolvedRole === Role::OWNER) {
                $owner = Owner::firstOrCreate(['register_id' => $user->id]);
            }

            if ($resolvedRole === Role::CUSTOMER) {
                $customer = Customer::firstOrCreate(['register_id' => $user->id]);
            }

            return [
                'user' => $user,
                'owner' => $owner,
                'customer' => $customer,
                'token' => JWTAuth::claims([
                    'role' => $user->role,
                    'onboarding' => false,
                ])->fromUser($user),
            ];
        });

        Cache::forget($pendingKey);
        Cache::forget("pending_register_phone:{$pending['phone']}");


        return $result;
    }

    public function resendOtp(string $pendingToken): void
    {
        $pending = Cache::get("pending_register:{$pendingToken}");

        if (! $pending) {
            throw new \Exception('Session expired. Please request OTP again.');
        }

        $this->otpService->resend($pending['phone']);
    }
}
