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
            'email' => 'required|string|email|max:255|unique:customers',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|min:8|same:password'
        ]);

        if ($validator->fails()) {
            return $this->sendError('error',$validator->errors(),400);
        }

        $otp = rand(100000, 999999);

        Otp::create([
            'email' => $request->email,
            'otp' => $otp,
            'expire_at' => now()->addMinutes(10)
        ]);

        Customers::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        // Send the OTP via SMS
//        $createCustomer->sendVerifyCode($createCustomer, $otp);

        return $this->sendResponse('success','OTP sent to email address',201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:350',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $otpRecord = Otp::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('expire_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $customer = Customers::where('email',$request->email)->first();

        if(!$customer) {
            return response()->json(['message' => 'Invalid email address'], 400);
        }

        $otpRecord->verified = 1;
        $otpRecord->save();

        // Create authentication token for the customer
        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        if (!Auth::guard('customer')->attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $customer = Customers::where('email', $request->email)->firstOrFail();

        $otpRecord = Otp::where('email', $customer->email)
            ->where('verified', 1)
            ->first();

        if(!$otpRecord) {
            return response()->json(['message' => 'Email is not verified'], 401);
        }

        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function validateToken(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Token is valid'], 200);
    }
}
