<?php

namespace App\Http\Controllers\AuthControllers\googleController;

use App\Http\Controllers\Controller;
use App\Services\AuthServices\googleService\GoogleService;
use Illuminate\Http\Request;

class GoogleController extends Controller
{
    // DI
    public function __construct(private GoogleService $googleService) {}

    public function loginOrRegisterWithGoogle(Request $request)
    {
        try {
            $data = $request->validate([
                'id_token' => 'required|string',
            ]);

            $result = $this->googleService->loginOrRegister($data['id_token']);

            return response()->json([
                'message' => 'Logged in with Google',
                'user' => $result['user'],
                'owner'=> $result['owner'],
                'token' => $result['token'], // may be null
                'requires_role_selection' => $result['requires_role_selection'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Google login failed',
                'error' => $e->getMessage(),
                'requires_role_selection' => true, // 👈 ADD THIS SAFETY
            ], 422);
        }
    }

    public function completeRole(Request $request)
    {
        try {
            $data = $request->validate([
                'id_token' => 'required|string',
                'role' => 'required|in:OWNER,CUSTOMER',
            ]);

            $result = $this->googleService->completeRole(
                $data['id_token'],
                $data['role'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Role selected successfully',
                'data' => [
                    'owner'=>$result['owner'],
                    'user' => $result['user'],
                    'token' => $result['token'],
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role selection failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
