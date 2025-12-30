<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyReportNotification;
use Illuminate\Console\Command;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily';
    protected $description = 'Send daily sales report to administrators';

    public function handle()
    {
        $admins = User::whereHas('role', function ($query) {
            $query->where('slug', 'admin');
        })->get();

        if ($admins->isEmpty()) {
            $this->info('No administrators found to send report to.');
            return;
        }

        foreach ($admins as $admin) {
            $admin->notify(new DailyReportNotification());
            $this->info('Daily report sent to: ' . $admin->email);
        }

        $this->info('Daily reports sent successfully.');
    }
}
