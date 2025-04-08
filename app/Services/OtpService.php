<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function sendOtpEmail(string $email, int $otp): void
    {
        $subject = 'Your OTP Code';
        $body = "
            <h2 style='font-family: Arial, sans-serif;'>Email Verification</h2>
            <p style='font-size: 16px; color: #333;'>Dear User,</p>
            <p style='font-size: 16px; color: #333;'>Your OTP code is:</p>
            <p style='font-size: 24px; font-weight: bold; color: #2F855A;'>$otp</p>
            <p style='font-size: 14px; color: #777;'>This code will expire in 10 minutes.</p>
            <br>
            <p style='font-size: 14px; color: #999;'>— VitalScan Team</p>
        ";

        Mail::html($body, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }
}
