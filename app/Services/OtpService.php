<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function sendOtpEmail(string $email, int $otp): void
    {
        $subject = 'Your OTP Code';
        $siteUrl = config('app.url'); // Və ya manual yaz: $siteUrl = 'https://vitalscan.com';
        $logoUrl = "$siteUrl/assets/images/logo_new.png"; // Logo faylının tam yolu

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

        Mail::html($body, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }
}
