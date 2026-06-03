<?php

namespace App\Services\AuthServices\adminService;

interface AdminAuthService
{
    public function register(string $admin_name, string $password): array;
    public function login(string $admin_name, string $password): array;
    public function logout(): void;
    public function refresh(): string;
    public function getAuthenticatedAdmin(): mixed;
}
