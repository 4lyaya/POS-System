<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\SendDailyReport::class,
        \App\Console\Commands\CheckLowStock::class,
        \App\Console\Commands\BackupDatabase::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Send daily report at 8 PM every day
        $schedule->command('report:daily')->dailyAt('20:00');

        // Check low stock every hour
        $schedule->command('stock:check-low')->hourly();

        // Backup database daily at midnight
        $schedule->command('backup:run')->dailyAt('00:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
