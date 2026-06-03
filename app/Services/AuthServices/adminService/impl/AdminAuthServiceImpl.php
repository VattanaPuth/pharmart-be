<?php

namespace App\Services\AuthServices\adminService\impl;

use App\Models\Auth\Admin;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\AuthServices\adminService\AdminAuthService;

class AdminAuthServiceImpl implements AdminAuthService
{
    public function register(string $admin_name, string $password): array
    {
        if (Admin::where('admin_name', $admin_name)->exists()) {
            throw new \Exception('Admin name already exists');
        }

        $admin = Admin::create([
            'admin_name' => $admin_name,
            'password' => Hash::make($password),
        ]);

        $token = auth('admin')->login($admin);

        return [
            'token' => $token,
            'admin' => $admin,
        ];
    }

    public function login(string $admin_name, string $password): array
    {
        $admin = Admin::where('admin_name', $admin_name)->first();

        if (!$admin || !Hash::check($password, $admin->password)) {
            throw new \Exception('Invalid credentials');
        }

        $token = auth('admin')->login($admin);

        return [
            'token' => $token,
            'admin' => $admin,
        ];
    }

    public function logout(): void
    {
        auth('admin')->logout();
    }

    public function refresh(): string
    {
        return auth('admin')->refresh();
    }

    public function getAuthenticatedAdmin(): mixed
    {
        return auth('admin')->user();
    }
}