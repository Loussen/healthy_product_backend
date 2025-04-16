<?php

namespace App\Services;

class GooglePayService
{
    private function handleSubscriptionRenewed($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Package bilgisini bul
            $package = Package::where('google_product_id', $subscriptionInfo->subscriptionId)->first();

            // Subscription'ı güncelle
            $subscription->update([
                'expiry_date' => Carbon::createFromTimestamp($subscriptionInfo->expiryTimeMillis / 1000),
                'status' => 'active',
                'auto_renewing' => $subscriptionInfo->autoRenewing,
            ]);

            // Yeni customer package oluştur
            CustomerPackage::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $package->id,
                'remaining_scans' => $package->scan_limit,
            ]);

            // Renewal transaction'ı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $package->id,
                'subscription_id' => $subscription->id,
                'amount' => $package->price,
                'payment_method' => 'google_play',
                'status' => 'completed',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'renewal'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleSubscriptionCanceled($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Subscription'ı güncelle
            $subscription->update([
                'status' => 'cancelled',
                'auto_renewing' => false
            ]);

            // İptal transaction'ı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $subscription->package_id,
                'subscription_id' => $subscription->id,
                'payment_method' => 'google_play',
                'status' => 'cancelled',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'cancellation'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleSubscriptionExpired($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Subscription'ı güncelle
            $subscription->update([
                'status' => 'expired',
                'auto_renewing' => false
            ]);

            // Aktif customer package'ı bul ve güncelle
            CustomerPackage::where('customer_id', $subscription->customer_id)
                ->where('package_id', $subscription->package_id)
                ->where('remaining_scans', '>', 0)
                ->update(['remaining_scans' => 0]);

            // Expiration transaction'ı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $subscription->package_id,
                'subscription_id' => $subscription->id,
                'payment_method' => 'google_play',
                'status' => 'expired',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'expiration'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleSubscriptionOnHold($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Subscription'ı güncelle
            $subscription->update([
                'status' => 'on_hold',
            ]);

            // Transaction kaydı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $subscription->package_id,
                'subscription_id' => $subscription->id,
                'payment_method' => 'google_play',
                'status' => 'on_hold',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'on_hold'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleSubscriptionInGracePeriod($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Subscription'ı güncelle
            $subscription->update([
                'status' => 'grace_period',
            ]);

            // Transaction kaydı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $subscription->package_id,
                'subscription_id' => $subscription->id,
                'payment_method' => 'google_play',
                'status' => 'grace_period',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'grace_period'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleSubscriptionRestarted($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Package bilgisini bul
            $package = Package::where('google_product_id', $subscriptionInfo->subscriptionId)->first();

            // Subscription'ı güncelle
            $subscription->update([
                'status' => 'active',
                'auto_renewing' => true,
                'expiry_date' => Carbon::createFromTimestamp($subscriptionInfo->expiryTimeMillis / 1000),
            ]);

            // Yeni customer package oluştur
            CustomerPackage::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $package->id,
                'remaining_scans' => $package->scan_limit,
            ]);

            // Restart transaction'ı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $package->id,
                'subscription_id' => $subscription->id,
                'amount' => $package->price,
                'payment_method' => 'google_play',
                'status' => 'completed',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'restart'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleSubscriptionRevoked($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Subscription'ı güncelle
            $subscription->update([
                'status' => 'revoked',
                'auto_renewing' => false
            ]);

            // Aktif customer package'ı bul ve güncelle
            CustomerPackage::where('customer_id', $subscription->customer_id)
                ->where('package_id', $subscription->package_id)
                ->where('remaining_scans', '>', 0)
                ->update(['remaining_scans' => 0]);

            // Revoke transaction'ı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $subscription->package_id,
                'subscription_id' => $subscription->id,
                'payment_method' => 'google_play',
                'status' => 'revoked',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'revocation'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleSubscriptionRecovered($subscriptionInfo, $purchaseToken)
    {
        DB::beginTransaction();
        try {
            $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            // Package bilgisini bul
            $package = Package::where('google_product_id', $subscriptionInfo->subscriptionId)->first();

            // Subscription'ı güncelle
            $subscription->update([
                'status' => 'active',
                'auto_renewing' => true,
                'expiry_date' => Carbon::createFromTimestamp($subscriptionInfo->expiryTimeMillis / 1000),
            ]);

            // Yeni customer package oluştur
            CustomerPackage::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $package->id,
                'remaining_scans' => $package->scan_limit,
            ]);

            // Recovery transaction'ı oluştur
            Transaction::create([
                'customer_id' => $subscription->customer_id,
                'package_id' => $package->id,
                'subscription_id' => $subscription->id,
                'amount' => $package->price,
                'payment_method' => 'google_play',
                'status' => 'completed',
                'transaction_date' => now(),
                'purchase_token' => $purchaseToken,
                'product_id' => $subscriptionInfo->subscriptionId,
                'platform' => 'android',
                'type' => 'recovery'
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // Yardımcı fonksiyon
    private function findCustomerIdByPurchaseToken($purchaseToken)
    {
        // Purchase token'a göre customer_id'yi bul
        $subscription = Subscription::where('purchase_token', $purchaseToken)->first();
        if ($subscription) {
            return $subscription->customer_id;
        }

        throw new \Exception('Customer not found for purchase token: ' . $purchaseToken);
    }
}
