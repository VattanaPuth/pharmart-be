<?php

namespace App\Http\Controllers\AuthControllers\registerController;

use App\Http\Controllers\Controller;
use App\Services\AuthServices\registerService\RegisterService;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    // DI
    public function __construct(private RegisterService $registerService) {}

    public function registerViaOtp(Request $request)
    {
        $data = $request->validate([
            'phone'    => 'required|string',
        ]);

        try {
            $token = $this->registerService->sendOtp(
                $data['phone']
            );

            return response()->json([
                'message' => 'OTP sent',
                'pending_token' => $token,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function registerViaVerifyOtp(Request $request)
    {
        $data = $request->validate([
            'pending_token' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        try {
            $this->registerService->verifyOtp(
                $data['pending_token'],
                $data['code'],
            );

            return response()->json([
                'message' => 'OTP verified',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function registerComplete(Request $request)
    {
        $data = $request->validate([
            'pending_token' => 'required|string',
            'role' => 'required|string',
        ]);

        try {
            $result = $this->registerService->complete(
                $data['pending_token'],
                $data['role'],
            );

            return response()->json([
                'message' => 'Register successful',
                'token' => $result['token'],
                'user' => $result['user'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

public function resendOtp(Request $request)
{
    $data = $request->validate([
        'pending_token' => 'required|string',
    ]);

    try {
        $this->registerService->resendOtp($data['pending_token']);

        return response()->json([
            'message' => 'OTP resent',
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 422);
    }
}
}
