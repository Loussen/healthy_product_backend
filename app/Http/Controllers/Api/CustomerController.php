<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Models\BugReports;
use App\Models\ContactUs;
use App\Models\Customers;
use App\Models\DeviceToken;
use App\Services\DebugWithTelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends BaseController
{
    public function customer(Request $request): JsonResponse
    {
        $locale = $request->header('Accept-Language', 'en');

        $user = $request->user();
        $getCustomer = Customers::with('scan_results')->find($user->id);

        if (is_null($getCustomer)) {
            return $this->sendError('customer_not_found', 'Customer not found');
        }

        $highestScoreScan = $getCustomer->scan_results()->where('product_score','>',0)->orderByDesc('product_score')->with('category')->first();
        $lowestScoreScan = $getCustomer->scan_results()
            ->where('product_score','>',0)
            ->orderBy('product_score')
            ->with('category')
            ->first();

        if ($highestScoreScan) {
            $highestScoreScan->product_name_ai = $highestScoreScan->product_name_ai ?: ($highestScoreScan->category_name_ai ?: 'Unspecified');
        }

        if ($lowestScoreScan) {
            $lowestScoreScan->product_name_ai = $lowestScoreScan->product_name_ai ?: ($lowestScoreScan->category_name_ai ?: 'Unspecified');
        }

        $thisMonthScans = $getCustomer->scan_results()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $todayScans = $getCustomer->scan_results()
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $allScans = $getCustomer->scan_results()
            ->count();

        $averageHealthScore = $getCustomer->scan_results()->where('product_score', '>', 0)->avg('product_score');

        $getCustomer->makeHidden(['scan_results']);

        // Most recent active package for display (newest purchase)
        $displayPackage = $user->packages()
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where('remaining_scans', '>', 0)
            ->orderByDesc('id')
            ->first();

        // Any active package exists (for permit_scan check)
        $hasActivePackage = $displayPackage !== null;

        // Total remaining scans across all active packages
        $totalRemainingScans = $user->packages()
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where('remaining_scans', '>', 0)
            ->sum('remaining_scans');

        $activePackageArray = $displayPackage ? $displayPackage->toArray() : null;

        if ($displayPackage) {
            $activePackageArray['package'] = [
                'id' => $displayPackage->package->id,
                'name' => $displayPackage->package->getTranslation('name', $locale) ?? 'Unknown',
                'color' => $displayPackage->package->color,
                'price' => $displayPackage->package->price,
                'scan_count' => $displayPackage->package->scan_count,
                'per_scan' => $displayPackage->package->per_scan,
                'saving' => $displayPackage->package->saving,
                'is_popular' => $displayPackage->package->is_popular,
                'created_at' => $displayPackage->package->created_at,
                'updated_at' => $displayPackage->package->updated_at
            ];
        }

        return $this->sendResponse(
            array_merge($getCustomer->toArray(), [
                'highest_score_scan' => $highestScoreScan,
                'lowest_score_scan' => $lowestScoreScan,
                'monthly_scans' => $thisMonthScans,
                'daily_scans' => $todayScans,
                'free_scan_limit' => config('services.free_package_limit'),
                'usage_limit' => $allScans > config('services.free_package_limit') ? config('services.free_package_limit') : $allScans,
                'active_package' => $activePackageArray,
                'total_remaining_scans' => (int) $totalRemainingScans,
                'permit_scan' => $hasActivePackage || $allScans < config('services.free_package_limit'),
                'average_health_score' => round($averageHealthScore) ?? 0,
            ]),
            'success'
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|string|min:8|same:password'
            ]);

            if ($validator->fails()) {
                return $this->sendError('error', $validator->errors(), 400);
            }

            $existingCustomer = Customers::find($user->id);
            if (!$existingCustomer) {
                return $this->sendError('not_found_user', 'User not found', 400);
            }

            if (!\Hash::check($request->current_password, $existingCustomer->getAttributes()['password'])) {
                return $this->sendError('wrong_password', 'Current password is incorrect', 400);
            }

            $existingCustomer->password = $request->password;
            $existingCustomer->save();

            return $this->sendResponse('success', 'Password changed', 201);

        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('change_password', "Change password error - ".$e->getMessage(), 500);
        }
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->subscriptions()->delete();
        $user->packages()->delete();
        $user->scan_results()->withoutGlobalScopes()->delete();
        $user->delete();

        return $this->sendResponse('success', 'Account deleted successfully', 200);
    }

    public function setDefaultCategory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = validator($request->all(), [
                'category_id' => 'required|exists:categories,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors()->first(), 422);
            }

            $user->default_category_id = $request->category_id;
            $user->save();

            return $this->sendResponse('success', 'Set default category successfully');
        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('set_default_category', "Set default category error - ".$e->getMessage(), 500);
        }
    }

    public function setDefaultLanguage(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'language' => [
                    'required',
                    Rule::in(array_keys(config('backpack.crud.locales'))),
                ],
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors()->first(), 422);
            }

            $user->language = $request->language;
            $user->save();

            return $this->sendResponse('success', 'Set default language successfully');
        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('set_default_category', "Set default language error - ".$e->getMessage(), 500);
        }
    }

    public function setDefaultCountry(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = validator($request->all(), [
                'country_id' => 'required|exists:countries,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors()->first(), 422);
            }

            $user->country_id = $request->country_id;
            $user->save();

            return $this->sendResponse('success', 'Set default country successfully');
        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('set_default_country', "Set default country error - ".$e->getMessage(), 500);
        }
    }

    public function storeDeviceToken(Request $request)
    {
        $log = new DebugWithTelegramService();
        $user = $request->user();

        $log->debug($request->all());
        $log->debug($user);

        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
            'device_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('error', $validator->errors(), 422);
        }

        try {
            // Kullanıcının mevcut token'ını kontrol et
            $existingToken = DeviceToken::where('customer_id', $user->id)
                ->where('device_token', $request->device_token)
                ->where('device_type', $request->device_type)
                ->first();

            if (!$existingToken) {
                // Yeni token kaydet
                DeviceToken::create([
                    'customer_id' => $user->id,
                    'device_token' => $request->device_token,
                    'device_type' => $request->device_type,
                ]);
            }

            return $this->sendResponse('success','Device token saved successfully');

        } catch (\Throwable $e) {
            return $this->sendError('failed_to_save_device_token','Failed to save device token: ' . $e->getMessage(), 500);
        }
    }

    public function bugReport(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|min:3|max:100',
                'type' => 'required|string',
                'description' => 'required|string|min:5|max:250',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors(), 400);
            }

            // Handle file upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('bug_reports', 'public');

                // Create bug report record
                $bugReport = BugReports::create([
                    'customer_id' => $user->id,
                    'title' => $request->title,
                    'type' => $request->type,
                    'screenshot' => $path,
                    'description' => $request->description
                ]);

                return $this->sendResponse([
                    $bugReport
                ], 'Bug report created successfully');
            }

            return $this->sendError('upload_error', 'No image file provided', 400);

        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('bug_report_error', "Bug report error - " . $e->getMessage(), 500);
        }
    }

    public function contactUs(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validate the request
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|min:3|max:100',
                'email' => 'required|email',
                'subject' => 'required|string|min:3|max:50',
                'message' => 'required|string|min:3|max:150',
            ]);

            if ($validator->fails()) {
                return $this->sendError('validation_error', $validator->errors(), 400);
            }


            $bugReport = ContactUs::create([
                'customer_id' => $user->id,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
            ]);

            return $this->sendResponse([
                $bugReport
            ], 'Contact us created successfully');

        } catch (\Throwable $e) {
            $log = new DebugWithTelegramService();
            $log->debug($e->getMessage());
            return $this->sendError('contact_us_error', "Contact us error - " . $e->getMessage(), 500);
        }
    }
}
