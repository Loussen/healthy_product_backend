<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Models\Customers;
use App\Models\Otp;
use App\Services\DebugWithTelegramService;
use App\Services\OtpService;
use Google_Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    public function register(Request $request, OtpService $otpService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
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

        Otp::where('email', $request->email)->where('type','register')->delete();

        $otp = rand(100000, 999999);

        $userData = $request->only(['name', 'surname', 'email', 'password']);

        Otp::create([
            'email' => $request->email,
            'otp' => $otp,
            'expire_at' => now()->addMinutes(10),
            'user_data' => json_encode($userData)
        ]);

        // Send the OTP via SMS/Email
        $otpService->sendOtpEmail($request->email, $otp);

        return $this->sendResponse('success', 'OTP sent to email address', 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->merge([
            'type' => $request->input('type', 'register'),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:350',
            'otp' => 'required|string|size:6',
            'type' => 'required|in:register,reset_password',
        ]);

        if ($validator->fails()) {
            return $this->sendError('validation', $validator->errors(), 400);
        }

        $otpRecord = Otp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('type', $request->type)
            ->where('expire_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return $this->sendError('invalid otp', "Invalid or expired OTP", 400);
        }

        if($request->type == 'register') {
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
        }

        $otpRecord->verified = 1;
        $otpRecord->save();

        if($request->type == 'reset_password') {
            return $this->sendResponse('success','Otp verified!');
        }

        $token = $customer->createToken('auth_token')->plainTextToken;

        return $this->sendResponse(['access_token' => $token, 'token_type' => 'Bearer'],'Success login', 201);
    }

    public function resendOtp(Request $request, OtpService $otpService): JsonResponse
    {
        $request->merge([
            'type' => $request->input('type', 'register'),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type' => 'required|in:register,reset_password',
        ]);

        if ($validator->fails()) {
            return $this->sendError('error', $validator->errors(), 400);
        }

        $email = $request->email;

        // OTP limiti yoxla
        $key = 'otp_limit_for_'.$request->type.':' . $email;
        $attempts = Cache::get($key, 0);

        if ($attempts >= 3) {
            return $this->sendError('limit', 'The OTP limit has been exceeded. Please try again later or write message to us.', 429);
        }

        $otpRecord = Otp::where('email', $email)->where('type',$request->type)->latest()->first();

        if (!$otpRecord) {
            return $this->sendError('not_found', 'OTP not found or the user has not registered.', 404);
        }

        $otp = rand(100000, 999999);

        $otpRecord->update([
            'otp' => $otp,
            'expire_at' => now()->addMinutes(10)
        ]);

        $otpService->sendOtpEmail($email, $otp, $request->type);

        Cache::put($key, $attempts + 1, now()->addMinutes(5));

        return $this->sendResponse('success', 'A new OTP code has been sent to your email address.', 200);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('error', $validator->errors(), 400);
        }

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

    public function redirectToGoogle(): JsonResponse
    {
        try {
            $url = Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return $this->sendResponse(['url' => $url], 'Login URL generated');
        } catch (\Exception $e) {
            return $this->sendError('google_auth_error', $e->getMessage(), 500);
        }
    }

    public function handleGoogleCallback(): JsonResponse
    {
       try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Email zaten kayıtlı mı kontrol et
            $existingCustomer = Customers::where('email', $googleUser->email)->first();

            if ($existingCustomer) {
                // Kullanıcı zaten varsa, direkt giriş yap
                $token = $existingCustomer->createToken('auth_token')->plainTextToken;

                return $this->sendResponse([
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ], 'Success login');
            }

            // Yeni kullanıcı oluştur
            $customer = Customers::create([
                'name' => explode(' ', $googleUser->name)[0] ?? $googleUser->name,
                'surname' => explode(' ', $googleUser->name)[1] ?? '',
                'email' => $googleUser->email,
                'email_verified_at' => now(),
                'password' => Str::random(16),
                'google_id' => $googleUser->id
            ]);

            // Google ile giriş yapan kullanıcılar için otomatik verify
            Otp::create([
                'email' => $googleUser->email,
                'otp' => '000000', // Dummy OTP
                'expire_at' => now()->addYears(10),
                'verified' => 1,
                'user_data' => json_encode([
                    'name' => $customer->name,
                    'surname' => $customer->surname,
                    'email' => $customer->email
                ])
            ]);

            $token = $customer->createToken('auth_token')->plainTextToken;

            return $this->sendResponse([
                'access_token' => $token,
                'token_type' => 'Bearer'
            ], 'Success login', 201);

       } catch (\Exception $e) {
           return $this->sendError('google_auth_error', $e->getMessage(), 500);
       }
    }

    public function signInWithGoogle(Request $request): JsonResponse
    {
        try {
            $client = new Google_Client([
                'client_id' => config('services.google.client_id')
            ]);

            // Google'dan gelen token'ı doğrula
            $payload = $client->verifyIdToken($request->token);

            if (!$payload) {
                $log = new DebugWithTelegramService();
                $log->debug('google_auth_error - Invalid Token - 401');
                return $this->sendError('google_auth_error', 'Invalid token', 401);
            }

            // Kullanıcıyı bul veya oluştur
            $customer = Customers::updateOrCreate(
                ['email' => $request->email],
                [
                    'name' => $request->name,
                    'surname' => $request->surname,
                    'google_id' => $request->google_id,
                    'email_verified_at' => now(),
//                    'password' => Str::random(16),
                ]
            );

            // JWT token oluştur
            $token = $customer->createToken('auth_token')->plainTextToken;

            // Google ile giriş yapan kullanıcılar için otomatik verify
            Otp::create([
                'email' => $request->email,
                'otp' => '000000', // Dummy OTP
                'expire_at' => now()->addMinutes(10),
                'verified' => 1,
                'user_data' => json_encode([
                    'name' => $customer->name,
                    'surname' => $customer->surname,
                    'email' => $customer->email
                ])
            ]);

            return $this->sendResponse([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $customer
            ], 'Success login');

        } catch (\Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'response' => $e->getMessage()
            ], 500);
        }
    }

    public function getBearerToken(Request $request)
    {
        $customer = Customers::where('email',$request->email)->firstOrFail();
        $token = $customer->createToken('auth_token')->plainTextToken;

        return $this->sendResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $customer
        ], 'Success token');
    }

    public function forgetPassword(Request $request, OtpService $otpService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                'exists:customers,email'
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('error', $validator->errors(), 400);
        }

        Otp::where('email', $request->email)->where('type','reset_password')->delete();

        $otp = rand(100000, 999999);

        $userData = $request->only(['email']);

        Otp::create([
            'email' => $request->email,
            'otp' => $otp,
            'expire_at' => now()->addMinutes(10),
            'user_data' => json_encode($userData),
            'type' => 'reset_password'
        ]);

        // Send the OTP via SMS/Email
        $otpService->sendOtpEmail($request->email, $otp, 'reset_password');

        return $this->sendResponse('success', 'OTP sent to email address', 201);
    }
}
