<?php

namespace App\Http\Controllers\AuthControllers\adminController;

use App\Http\Controllers\Controller;
use App\Services\AuthServices\adminService\AdminAuthService;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function __construct(private AdminAuthService $adminAuthService) {}

    public function register(Request $request)
    {
        $data = $request->validate([
            'admin_name' => 'required|string|max:255|unique:admin,admin_name',
            'password' => 'required|string|min:8',
        ]);

        try {
            $result = $this->adminAuthService->register(
                $data['admin_name'],
                $data['password']
            );

            return response()->json([
                'message' => 'Admin registered successfully',
                'token' => $result['token'],
                'admin' => $result['admin'],
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'admin_name' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->adminAuthService->login(
                $data['admin_name'],
                $data['password']
            );

            return response()->json([
                'message' => 'Login successful',
                'token' => $result['token'],
                'admin' => $result['admin'],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function logout()
    {
        try {
            $this->adminAuthService->logout();

            return response()->json([
                'message' => 'Logout successful'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function refresh()
    {
        try {
            return response()->json([
                'token' => $this->adminAuthService->refresh()
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function me()
    {
        try {
            $admin = $this->adminAuthService->getAuthenticatedAdmin();

            if (!$admin) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return response()->json([
                'admin' => $admin
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}