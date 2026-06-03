<?php

namespace App\Services\AuthServices\GoogleService\impl;

use App\Enums\Auth\Role;
use App\Models\Auth\Register;
use App\Models\Customer\Customer;
use App\Models\Owner\Owner;
use App\Services\AuthServices\googleService\GoogleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Google\Client as GoogleClient;
use Throwable;
use Illuminate\Support\Facades\Log;
use App\Models\Ekyc\OwnerEkyc;

class GoogleServiceImpl implements GoogleService
{

    public function loginOrRegister(string $idToken): array
    {
        try {

            $user = $this->resolveGoogleUser($idToken);

            $owner = null;
            $ekycStatus = null;

            if ($user->role === Role::OWNER->value) {
                $owner = Owner::where('register_id', $user->id)->first();
                if ($owner) {
        $ekyc = OwnerEkyc::where('owner_id', $owner->id)->first();
        $ekycStatus = $ekyc?->status;
    }
            }

            return [
                'user' => $user,
                'owner' => $owner ?? null,
                'token' => $user->role
                    ? JWTAuth::claims([
                        'role' => $user->role,
                        'onboarding' => $user->onboarding_completed,
                        'ekyc_status' => $ekycStatus,
                    ])->fromUser($user)
                    : null,

                'requires_role_selection' => $user->role === null,

                'next_step' => $user->role
                    ? 'LOGIN_SUCCESS'
                    : 'SELECT_ROLE',
            ];
        } catch (Throwable $e) {

            Log::error('Google Login Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    
    public function completeRole(string $idToken, string $role): array
    {
        $user = $this->resolveGoogleUser($idToken);

        $owner = Owner::where('register_id', $user->id)->first();

        if ($user->role !== null) {
            // role selected before
            return [
                'user' => $user->fresh(),
                'owner' => $owner ?? null,
                'token' => JWTAuth::claims([
                    'role' => $user->role,
                    'onboarding' => false
                ])->fromUser($user),
            ];
        }

        $resolvedRole = Role::tryFrom($role);

        if (! $resolvedRole) {
            throw new \Exception('Invalid role');
        }

        $result = DB::transaction(function () use ($user, $resolvedRole) {

            $user->role = $resolvedRole->value;
            $user->onboarding_completed = false; // 🔥 IMPORTANT FIX
            $user->save();

            $owner = null;

            if ($resolvedRole === Role::OWNER) {
                $owner = Owner::updateOrCreate(
                    ['register_id' => $user->id],
                    []
                )->fresh();
            }

            if ($resolvedRole === Role::CUSTOMER) {
                Customer::updateOrCreate(['register_id' => $user->id]);
            }

            return [
                'user' => $user->fresh(),
                'owner' => $owner ?? null,
                'token' => JWTAuth::claims([
                    'role' => $user->role,
                    'onboarding' => false
                ])->fromUser($user),
            ];
        });

        return $result;
    }

    public function makeSlug(string $text): string
    {
        return Str::slug($text, '_') ?: 'user';
    }

    private function resolveGoogleUser(string $idToken): Register
    {
        if (!$idToken) {
            throw new \Exception('Missing Google ID token');
        }

        $client = new GoogleClient([
            'client_id' => config('services.google.client_id'),
        ]);

        $payload = $client->verifyIdToken($idToken);

        if (! $payload) {
            throw new \Exception('Invalid Google token');
        }

        $googleId = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? null;

        if (! $googleId) {
            throw new \Exception('Google token missing sub');
        }

        $user = Register::where(function ($q) use ($googleId) {
            $q->where('oauth_provider', 'google')
                ->where('oauth_provider_id', $googleId);
        })
            ->when($email, function ($q) use ($email) {
                $q->orWhere(function ($q2) use ($email) {
                    $q2->where('email', $email)
                        ->whereNull('oauth_provider_id');
                });
            })
            ->first();

        if (! $user) {
            $user = Register::create([
                'email' => $email,
                'role' => null,
                'oauth_provider' => 'google',
                'oauth_provider_id' => $googleId,
                'onboarding_completed' => false,
            ]);
        }

        // ✅ MERGE UPDATE
        $user->oauth_provider = 'google';
        $user->oauth_provider_id = $googleId;

        if ($email && !$user->email) {
            $user->email = $email;
        }

        $user->save();

        return $user;
    }
}
