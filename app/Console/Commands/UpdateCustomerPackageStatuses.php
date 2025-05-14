<?php

namespace App\Console\Commands;

use App\Models\CustomerPackages;
use Illuminate\Console\Command;

class UpdateCustomerPackageStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-customer-package-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update statuses based on created_at and remaining_scans';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        CustomerPackages::where('status', 'active')->chunk(100, function ($items) use ($now) {
            foreach ($items as $item) {
                $updated = false;

                // 1 ay keçibsə -> expired
                if ($item->created_at->lt($now->copy()->subMonth())) {
                    $item->status = 'expired';
                    $updated = true;
                }

                // remaining_scans = 0 isə -> out_of_limit
                if ($item->remaining_scans === 0) {
                    $item->status = 'out_of_limit';
                    $updated = true;
                }

                if ($updated) {
                    $item->save();
                }
            }
        });

        $this->info('Statuses updated successfully.');
    }
}
