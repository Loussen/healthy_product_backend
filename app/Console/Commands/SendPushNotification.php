<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;

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

            $this->info('Bildirimler gönderildi!');
            $this->line('Firebase Response: ' . json_encode($response));
        } else {
            $deviceToken = 'dmZEmYwaQsWuDfEnUVUbll:APA91bGA7bksMseJp39eCi0E9t9tioibhVIiJB1oO4I86IzJ4XE-GWfc0Emj6PB3s7MjgEO5E9Rh5rRNqm2f2HN4xwlMrrcPCvgIzL-fWAG0_7X5LqrUPlg';

            $response = $this->firebaseService->sendNotification($deviceToken, $title, $body);

            $this->info('Bildirim gönderildi!');
            $this->line('Firebase Response: ' . json_encode($response));
        }


    }
}
