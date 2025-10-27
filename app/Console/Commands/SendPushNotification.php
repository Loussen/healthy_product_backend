<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-push-notification {--type=single}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $title = 'Vital Scan';
        $body = "Analyse product ingredients and make more informed shopping decisions. Please scan the ingredients section of the product to view the nutritional indicators.";

        $type = $this->option('type');

        if ($type == 'multi') {
            $deviceTokens = [
                'dQOI-gJeS26TWD2jh6Sc_W:APA91bE18u4_dget48hiHJqeBjk4os--umzT1aUvRv2BIWQssMa7EIx8YR9go20xWdweTenX2CMJ4EZyRA4j2QOsKCEdag3IVrtgZ8NaxLlImuAegB6i8wY',
                'eJM4xX-ZRXqEDYR_KEtZk8:APA91bGjBoZQ9-aeaduaI5QgZHwzbjtgnIGccO2hrg1eHFYQePYwS4-FzGtaVfMfVk4bcOq2nWrqimsnOtrdV4ZsJjd1cugALkGrpfZy8ZOQ_5hwx32L2tY'
            ];

            $deviceTokens = DeviceToken::pluck('device_token')->toArray();

            $validTokens = [];

            foreach ($deviceTokens as $token) {
                $device = DeviceToken::where('device_token', $token)->first();

                if (!$device) continue;

                $validTokens[] = $token;
            }

            foreach ($validTokens as $token) {
                $customerId = DeviceToken::where('device_token', $token)->value('customer_id');

                $notificationId = DB::table('push_notifications')->insertGetId([
                    'title' => $title,
                    'description' => $body,
                    'type' => 'multi',
                    'customer_id' => $customerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $data = ['notification_id' => (string) $notificationId];

                $response = $this->firebaseService->sendNotification($token, $title, $body, $data);

                if (isset($response['error'])) {
                    DeviceToken::where('device_token', $token)->delete();
                    $this->warn("Invalid token is deleted: $token");
                }
            }

            $this->info('Bildirimler gönderildi!');
        } else {
            $deviceToken = 'dQOI-gJeS26TWD2jh6Sc_W:APA91bE18u4_dget48hiHJqeBjk4os--umzT1aUvRv2BIWQssMa7EIx8YR9go20xWdweTenX2CMJ4EZyRA4j2QOsKCEdag3IVrtgZ8NaxLlImuAegB6i8wY';

            $customerId = DeviceToken::where('device_token', $deviceToken)->value('customer_id');

            if (!$customerId) {
                $this->warn("Token not found in DB: $deviceToken");
                return;
            }

            $notificationId = DB::table('push_notifications')->insertGetId([
                'title' => $title,
                'description' => $body,
                'type' => 'single',
                'customer_id' => $customerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $data = ['notification_id' => (string) $notificationId];

            $response = $this->firebaseService->sendNotification($deviceToken, $title, $body, $data);

            if (isset($response['error'])) {
                DeviceToken::where('device_token', $deviceToken)->delete();
                $this->warn("Invalid token is deleted: $deviceToken");
            } else {
                $this->info('Bildirim gönderildi!');
                $this->line('Firebase Response: ' . json_encode($response));
            }
        }
    }

}
