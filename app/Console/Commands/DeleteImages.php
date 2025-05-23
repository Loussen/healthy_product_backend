<?php

namespace App\Console\Commands;

use App\Models\ScanResults;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $scanResults = ScanResults::where('customer_id', 17)->get();

        foreach ($scanResults as $result) {
            if ($result->image && Storage::disk('public')->exists($result->image)) {
                Storage::disk('public')->delete($result->image);
            }

            $result->deleted_at = now();
            $result->save();
        }
    }
}
