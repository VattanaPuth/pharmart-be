<?php

namespace App\Http\Controllers\AuthControllers\loginController;

use App\Http\Controllers\Controller;
use App\Services\AuthServices\loginService\LoginService;
use Illuminate\Http\Request;

class LoginController extends Controller
{

    public function __construct(private LoginService $loginService){}

    // input phone number
    public function loginViaOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
        ]);

        try {

            $pendingToken = $this->loginService->sendOtp($data['phone']);

            return response()->json([
                'message' => 'OTP sent',
                'pending_token' => $pendingToken,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // after click sign in, input otp code and pending_token is taken from session memeory (automatically input, user handle only otp code) 
    // public function loginViaVerifyOtp(Request $request)
    // {
    //     $data = $request->validate([
    //         'pending_token' => 'required|string',
    //         'code' => 'required|string|size:6',
    //     ]);

    //     try {
    //         $result = $this->loginService->verifyOtp(
    //             $data['pending_token'],
    //             $data['code'],
    //         );

    //         return response()->json([
    //             'message' => 'Login successful',
    //             'token' => $result['token'],
    //             'user' => $result['user'],
    //         ]);
    //     } catch (\Throwable $e) {
    //         return response()->json(['error' => $e->getMessage()], 422);
    //     }
    // }

    public function loginViaVerifyOtp(Request $request)
{
    $data = $request->validate([
        'pending_token' => 'required|string',
        'code' => 'required|string|size:6',
    ]);

    try {
        $result = $this->loginService->verifyOtp(
            $data['pending_token'],
            $data['code'],
        );

        return response()->json([
            'message' => 'Login successful',

            // 🔥 unified auth response (same as Google)
            'user' => $result['user'],
            'owner' => $result['owner'] ?? null,
            'token' => $result['token'],

            'requires_role_selection' => $result['requires_role_selection'],
            'next_step' => $result['next_step'],
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
        ], 422);
    }
}
}
