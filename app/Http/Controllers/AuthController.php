<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Models\Customers;
use App\Models\Otp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|min:8|same:password'
        ]);

        if ($validator->fails()) {
            return $this->sendError('error', $validator->errors(), 400);
        }

        $existingCustomer = Customers::where('email', $request->email)->first();
        if ($existingCustomer) {
            return $this->sendError('email', 'Email already registered', 400);
        }

        Otp::where('email', $request->email)->delete();

        $otp = rand(100000, 999999);

        $userData = $request->only(['name', 'surname', 'email', 'password']);

        Otp::create([
            'email' => $request->email,
            'otp' => $otp,
            'expire_at' => now()->addMinutes(10),
            'user_data' => json_encode($userData)
        ]);

        // Send the OTP via SMS/Email
        // $this->sendVerifyCode($request->email, $otp);

        return $this->sendResponse('success', 'OTP sent to email address', 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:350',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->sendError('validation', $validator->errors(), 400);
        }

        $otpRecord = Otp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expire_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return $this->sendError('invalid otp', "Invalid or expired OTP", 400);
        }

        $userData = json_decode($otpRecord->user_data, true);

        $existingCustomer = Customers::where('email', $userData['email'])->first();
        if ($existingCustomer) {
            return $this->sendError('error', 'Email already verified', 400);
        }

        $customer = Customers::create([
            'name' => $userData['name'],
            'surname' => $userData['surname'],
            'email' => $userData['email'],
            'password' => $userData['password'],
        ]);

        $otpRecord->verified = 1;
        $otpRecord->save();

        $token = $customer->createToken('auth_token')->plainTextToken;

        return $this->sendResponse(['access_token' => $token, 'token_type' => 'Bearer'],'Success login', 201);
    }

    public function login(Request $request): JsonResponse
    {
        if (!Auth::guard('customer')->attempt($request->only('email', 'password'))) {
            return $this->sendError('unauthorized', "Login failed", 401);
        }

        $customer = Customers::where('email', $request->email)->firstOrFail();

        $otpRecord = Otp::where('email', $customer->email)
            ->where('verified', 1)
            ->first();

        if (!$otpRecord) {
            return $this->sendError('email not verified', "Email is not verified", 401);
        }

        $token = $customer->createToken('auth_token')->plainTextToken;

        return $this->sendResponse(['access_token' => $token, 'token_type' => 'Bearer'],'Success login');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->sendResponse('logout','Success logout');
    }

    public function validateToken(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Token is valid'], 200);
    }
}
