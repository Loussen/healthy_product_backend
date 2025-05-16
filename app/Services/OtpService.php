<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function sendOtpEmail(string $email, int $otp, $type = 'register'): bool
    {
        $subject = $type = 'reset_password' ? 'Your OTP code for reset password' : 'Your OTP Code for registration';
        $siteUrl = config('app.url');
        $logoUrl = "$siteUrl/assets/images/logo_new.png";

        if($type == 'reset_password') {
            $body = "
                <div style='background-color: #f9f9f9; padding: 40px 0; font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);'>

                        <div style='text-align: center; margin-bottom: 20px;'>
                            <img src='$logoUrl' alt='VitalScan Logo' style='max-width: 150px;'>
                        </div>

                        <h2 style='color: #2F855A; text-align: center;'>Reset Your Password</h2>

                        <p style='font-size: 16px; color: #333;'>Dear User,</p>
                        <p style='font-size: 16px; color: #333;'>You recently requested to reset your password. Please use the OTP code below to proceed:</p>

                        <div style='text-align: center; margin: 20px 0;'>
                            <p style='font-size: 30px; font-weight: bold; color: #2F855A;'>$otp</p>
                        </div>

                        <p style='font-size: 14px; color: #555;'>This code is valid for 10 minutes.</p>

                        <p style='font-size: 14px; color: #555;'>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>

                        <hr style='margin: 30px 0;'>

                        <p style='font-size: 14px; color: #999; text-align: center;'>— VitalScan Team</p>
                        <p style='font-size: 12px; color: #aaa; text-align: center;'>
                            Visit us at <a href='$siteUrl' style='color: #2F855A; text-decoration: none;'>$siteUrl</a>
                        </p>
                    </div>
                </div>
            ";
        } else {
            $body = "
                <div style='background-color: #f9f9f9; padding: 40px 0; font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);'>

                        <div style='text-align: center; margin-bottom: 20px;'>
                            <img src='$logoUrl' alt='VitalScan Logo' style='max-width: 150px;'>
                        </div>

                        <h2 style='color: #2F855A; text-align: center;'>Email Verification</h2>

                        <p style='font-size: 16px; color: #333;'>Dear User,</p>
                        <p style='font-size: 16px; color: #333;'>Thank you for signing up. Please use the following OTP code to verify your email address:</p>

                        <div style='text-align: center; margin: 20px 0;'>
                            <p style='font-size: 30px; font-weight: bold; color: #2F855A;'>$otp</p>
                        </div>

                        <p style='font-size: 14px; color: #555;'>This code will expire in 10 minutes.</p>

                        <p style='font-size: 14px; color: #555;'>If you did not request this code, please ignore this email.</p>

                        <hr style='margin: 30px 0;'>

                        <p style='font-size: 14px; color: #999; text-align: center;'>— VitalScan Team</p>
                        <p style='font-size: 12px; color: #aaa; text-align: center;'>
                            Visit us at <a href='$siteUrl' style='color: #2F855A; text-decoration: none;'>$siteUrl</a>
                        </p>
                    </div>
                </div>
            ";
        }

        try {
            Mail::html($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            return true;
        } catch (Exception $e) {
            $log = new DebugWithTelegramService();
            $log->debug('Invalid email for register: '.$e->getMessage());

            return false;
        }
    }
}
