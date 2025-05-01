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
        $title = 'salam';
        $body = "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.";

        $type = $this->option('type');

        if($type == 'multi') {
            $deviceTokens = ['dzSHRtDqSa2kzcFkzxdA9x:APA91bE8CMUKQFxCkBJrWSp5TYZLvxPYBLlyzKsJXsqbU8o4BQjwWJiJAMPWvIGWSlOQdt0ArVdpj6DiAuwVCTd9cHw6tytnB5A74QJBTcoMEPorJMHDm3k','eJM4xX-ZRXqEDYR_KEtZk8:APA91bGjBoZQ9-aeaduaI5QgZHwzbjtgnIGccO2hrg1eHFYQePYwS4-FzGtaVfMfVk4bcOq2nWrqimsnOtrdV4ZsJjd1cugALkGrpfZy8ZOQ_5hwx32L2tY'];

            $response = $this->firebaseService->sendMulticastNotification($deviceTokens, $title, $body);

            foreach ($response as $index => $resp) {
                $token = $deviceTokens[$index];

                if (isset($resp['error'])) {
                    $device = DeviceToken::where('device_token', $token)->first();

                    if($device) {
                        DeviceToken::where('device_token', $token)->delete();

                        $this->warn("Invalid token is deleted: $token");

                        unset($deviceTokens[$index]);
                    }
                }
            }

            foreach ($deviceTokens as $token) {
                $customerId = DeviceToken::where('device_token', $token)->value('customer_id');

                DB::table('push_notifications')->insert([
                    'title' => $title,
                    'description' => $body,
                    'type' => 'multi',
                    'customer_id' => $customerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->info('Bildirimler gönderildi!');
            $this->line('Firebase Response: ' . json_encode($response));
        } else {
            $deviceToken = 'fudfag5cQJ-L_gAKL4MHIE:APA91bFXmqT-0PPbUxw7pCr-WT0v5Ov3wjOUTbfS5ZnkRVMj1MgewW7jqWg1gy6zIVAYz4ZIXAqq1InJySgFzZjavZewjD22iYP_7VepJPL1H0Dk2wkaS_og';

            $response = $this->firebaseService->sendNotification($deviceToken, $title, $body);

            if($response['error']) {
                $device = DeviceToken::where('device_token', $deviceToken)->first();

                if($device) {
                    DeviceToken::where('device_token', $deviceToken)->delete();

                    $this->warn("Invalid token is deleted: $deviceToken");
                }
            } else {
                $customerId = DeviceToken::where('device_token', $deviceToken)->value('customer_id');

                DB::table('push_notifications')->insert([
                    'title' => $title,
                    'description' => $body,
                    'type' => 'single',
                    'customer_id' => $customerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);


                $this->info('Bildirim gönderildi!');
                $this->line('Firebase Response: ' . json_encode($response));
            }
        }
    }
}
