<?php

namespace App\Services\AuthServices\GoogleService;

use App\Models\Auth\Register;
use Google\Client as GoogleClient;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

interface GoogleService
{
    public function loginOrRegister(string $idToken): array;
    public function completeRole(string $idToken, string $role): array;
    public function makeSlug(string $text): string;
}
