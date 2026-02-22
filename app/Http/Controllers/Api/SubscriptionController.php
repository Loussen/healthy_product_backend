<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Models\CustomerPackages;
use App\Models\Packages;
use App\Models\Subscription;
use App\Services\AppStoreVerificationService;
use App\Services\DebugWithTelegramService;
use App\Services\GooglePayService;
use App\Services\GooglePayVerificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends BaseController
{
    public function verifySubscription(Request $request)
    {
        $log = new DebugWithTelegramService();

        try {
            $user = $request->user();

            $validated = $request->validate([
                'product_id' => 'required|string',
                'purchase_token' => 'required|string',
                'transaction_date' => 'required|date',
            ]);

            $googleService = new GooglePayService();
            $subscriptionInfo = $googleService->verifySubscription(
                $validated['product_id'],
                $validated['purchase_token']
            );

            $now = now();
            $expiryDate = Carbon::createFromTimestamp($subscriptionInfo->expiryTimeMillis / 1000);

            if ($now->gte($expiryDate)) {
                return $this->sendError('expired', 'Subscription has already expired.', 400);
            }

            if (!isset($subscriptionInfo->paymentState) || $subscriptionInfo->paymentState != 1) {
                return $this->sendError('invalid_payment', 'Payment not completed.', 400);
            }

            $existingSubscription = Subscription::where('purchase_token', $validated['purchase_token'])->first();
            if ($existingSubscription) {
                return $this->sendResponse('already_exists', 'Subscription already exists.', 200);
            }

            $package = Packages::where('product_id_for_payment', $validated['product_id'])->firstOrFail();

            DB::transaction(function () use ($user, $validated, $subscriptionInfo, $package, $expiryDate) {
                $subscription = Subscription::create([
                    'customer_id' => $user->id,
                    'product_id' => $validated['product_id'],
                    'purchase_token' => $validated['purchase_token'],
                    'start_date' => Carbon::createFromTimestamp($subscriptionInfo->startTimeMillis / 1000),
                    'expiry_date' => $expiryDate,
                    'status' => SubscriptionStatus::ACTIVE->value,
                    'auto_renewing' => $subscriptionInfo->autoRenewing ?? false,
                    'payment_details' => json_encode($subscriptionInfo),
                    'amount' => $package->price,
                ]);

                CustomerPackages::create([
                    'customer_id' => $user->id,
                    'package_id' => $package->id,
                    'remaining_scans' => $package->scan_count,
                    'subscription_id' => $subscription->id,
                    'status' => SubscriptionStatus::ACTIVE->value,
                ]);
            });

            return $this->sendResponse('success', 'Subscription verified successfully.', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('validation_error', $e->errors(), 422);

        } catch (\Throwable $e) {
            Log::error('verifySubscription error', ['type' => get_class($e), 'message' => $e->getMessage()]);
            try { $log->debug('Error verifying subscription: ' . $e->getMessage()); } catch (\Throwable $ignore) {}
            return $this->sendError('payment_failed', 'An error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function verifyPurchase(Request $request)
    {
        Log::info("payment");
        $log = new DebugWithTelegramService();

        try {
            $user = $request->user();

            $validated = $request->validate([
                'product_id' => 'required|string',
                'platform' => 'required|in:ios,android',
                'receipt_data' => 'required_if:platform,ios|string',
                'transaction_id' => 'required_if:platform,ios|string',
                'purchase_token' => 'required_if:platform,android|string',
            ]);

            $purchaseToken = $validated['platform'] === 'ios'
                ? $validated['transaction_id']
                : $validated['purchase_token'];

            // 🔒 Cache lock
            $lockKey = 'purchase_lock_' . $purchaseToken;
            $lock = Cache::lock($lockKey, 30);

            if (!$lock->get()) {
                return $this->sendResponse('processing', 'Purchase is being processed.', 200);
            }

            try {
                // ✅ Sadece ACTIVE durumda olan aynı purchase_token var mı kontrol et
                $existingActivePurchase = Subscription::where('purchase_token', $purchaseToken)
                    ->where('status', SubscriptionStatus::ACTIVE->value)
                    ->first();

                if ($existingActivePurchase) {
                    return $this->sendResponse('already_exists', 'Purchase already exists.', 200);
                }

                // Platform'a göre doğrulama
                if ($validated['platform'] === 'ios') {
                    $appStoreService = new AppStoreVerificationService();
                    $purchaseInfo = $appStoreService->verifyPurchase(
                        $validated['receipt_data'],
                        $validated['transaction_id']
                    );

                    if (!$purchaseInfo->isValid) {
                        return $this->sendError('invalid_purchase', 'Invalid purchase.', 400);
                    }

                    $orderId = $purchaseInfo->transactionId;
                } else {
                    $googlePlayService = new GooglePayVerificationService();
                    $purchaseInfo = $googlePlayService->verifyPurchase(
                        $validated['product_id'],
                        $validated['purchase_token']
                    );

                    // Google Play purchase states:
                    // 0 = Purchased
                    // 1 = Cancelled
                    // 2 = Pending
                    if ($purchaseInfo->purchaseState !== 0) {
                        return $this->sendError('invalid_purchase', 'Invalid purchase state.', 400);
                    }

                    $orderId = $purchaseInfo->orderId;
                }

                $productColumn = $validated['platform'] === 'ios'
                    ? 'product_id_for_purchase_apple'
                    : 'product_id_for_purchase';

                Log::info('Looking up package', [
                    'column' => $productColumn,
                    'product_id' => $validated['product_id'],
                ]);

                $product = Packages::where($productColumn, $validated['product_id'])->firstOrFail();

                Log::info('Package found', [
                    'package_id' => $product->id,
                    'package_name' => $product->name,
                    'scan_count' => $product->scan_count,
                ]);

                DB::transaction(function () use ($user, $validated, $purchaseInfo, $product, $purchaseToken, $orderId) {
                    // ✅ Transaction içinde de ACTIVE kontrolü yap (pessimistic lock)
                    $exists = Subscription::where('purchase_token', $purchaseToken)
                        ->where('status', SubscriptionStatus::ACTIVE->value)
                        ->lockForUpdate()
                        ->exists();

                    if ($exists) {
                        throw new \Exception('Duplicate active purchase detected');
                    }

                    $purchase = Subscription::create([
                        'customer_id' => $user->id,
                        'product_id' => $product->id,
                        'purchase_token' => $purchaseToken,
                        'platform' => $validated['platform'],
                        'status' => SubscriptionStatus::ACTIVE->value,
                        'transaction_id' => $orderId,
                        'payment_details' => json_encode($purchaseInfo),
                        'amount' => $product->price
                    ]);

                    CustomerPackages::create([
                        'customer_id' => $user->id,
                        'package_id' => $product->id,
                        'remaining_scans' => $product->scan_count,
                        'subscription_id' => $purchase->id,
                        'status' => SubscriptionStatus::ACTIVE->value,
                    ]);
                });

                Log::info("payment end");

                return $this->sendResponse('success', 'Purchase verified successfully.', 200);

            } finally {
                $lock->release();
            }

        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return $this->sendResponse('already_exists', 'Purchase already exists.', 200);
            }

            Log::error('verifyPurchase error', ['type' => get_class($e), 'message' => $e->getMessage()]);
            try { $log->debug('Error verifying purchase: ' . $e->getMessage()); } catch (\Throwable $ignore) {}
            return $this->sendError('purchase_failed', 'An error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function webhookGoogleSubscription(Request $request)
    {
        $log = new DebugWithTelegramService();

        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            $log->debug('Webhook Payload2: ' . json_encode($payload));

//            $notification = $payload['voidedPurchaseNotification'] ?? null;
            $notification = $payload['subscriptionNotification'] ?? null;

//            return $this->sendResponse('success', 'Webhook processed successfully.', 200);

            if (!$notification) {
                $log->debug('subscriptionNotification');
                return $this->sendError('invalid_notification', 'No subscription notification found.', 400);
            }

            $purchaseToken = $notification['purchaseToken'] ?? null;
            $refundType = $notification['refundType'] ?? 0;

            if (!$purchaseToken) {
                $log->debug('not_found_token - '.$purchaseToken);
                return $this->sendError('invalid_payload', 'Missing required fields.', 400);
            }

            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();

            if($subscription) {
                $customerPackage = CustomerPackages::where('subscription_id', $subscription->id)->first();

                if(!$customerPackage) {
                    $log->debug('not_found_customer_package - '.$purchaseToken);
                    return $this->sendError('customer_package_not_found', 'Customer package not found.');
                }
            } else {
                $log->debug('not_found_sub - '.$purchaseToken);
                return $this->sendError('subscription_not_found', 'Subscription not found.');
            }

            $customerPackage->status = $refundType == 1 ? 'refund' : 'unknown';
            $customerPackage->save();

            $purchase = $subscription->replicate();
            $purchase->payment_details = json_encode($payload);
            $purchase->status = $refundType == 1 ? 'refund' : 'unknown';
            $purchase->parent_id = $subscription->id;
            $purchase->save();

            $log->debug('Subscription status updated to: ' . ($refundType == 1 ? 'refund' : 'unknown'));

            return $this->sendResponse('success', 'Webhook processed successfully.', 200);

        } catch (\Throwable $e) {
            Log::error('webhook error', ['type' => get_class($e), 'message' => $e->getMessage()]);
            try { $log->debug('Error processing webhook: ' . $e->getMessage()); } catch (\Throwable $ignore) {}
            return $this->sendError('webhook_error', 'Error: ' . $e->getMessage(), 500);
        }
    }

    public function getOrderHistory(Request $request)
    {
        $user = $request->user();

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = CustomerPackages::with(['subscription','package']) // İlişkili subscription'u yükle
        ->where('customer_id', $user->id)
            ->orderBy('id', 'desc');

        $paginatedResults = $query->paginate($perPage, ['*'], 'page', $page);

        $response = [
            'data' => $paginatedResults->items(),
            'pagination' => [
                'current_page' => $paginatedResults->currentPage(),
                'last_page' => $paginatedResults->lastPage(),
                'per_page' => $paginatedResults->perPage(),
                'total' => $paginatedResults->total(),
            ]
        ];

        return $this->sendResponse($response, 'success');
    }

    private function activateCustomerPackage(Subscription $subscription)
    {
        $package = Packages::where('product_id_for_payment', $subscription->product_id)->firstOrFail();

        $customerPackage = CustomerPackages::where('subscription_id', $subscription->id)->first();

        if($customerPackage) {
            CustomerPackages::where('subscription_id', $subscription->id)->where('status',SubscriptionStatus::ACTIVE->value)
                ->update(['status' => SubscriptionStatus::INACTIVE->value]);
        }

        CustomerPackages::create([
            'customer_id' => $subscription->customer_id,
            'package_id' => $package->id,
            'remaining_scans' => $package->scan_count,
            'subscription_id' => $subscription->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    private function deactivateCustomerPackage(Subscription $subscription, string $newStatus)
    {
        $customerPackage = CustomerPackages::where('subscription_id', $subscription->id)->first();
        if ($customerPackage) {
            CustomerPackages::where('subscription_id', $subscription->id)->where('status',SubscriptionStatus::ACTIVE->value)
                ->update(['status' => $newStatus]);
        }
    }
}
