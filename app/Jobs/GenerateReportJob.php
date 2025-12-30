<?php

namespace App\Jobs;

use App\Models\User;
use App\Exports\SalesExport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\ReportGeneratedNotification;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $reportType;
    public $startDate;
    public $endDate;
    public $filters;

    public function __construct($userId, $reportType, $startDate, $endDate, $filters = [])
    {
        $this->userId = $userId;
        $this->reportType = $reportType;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->filters = $filters;
    }

    public function handle()
    {
        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        try {
            $filename = $this->generateFilename();
            $path = 'reports/' . $filename;

            switch ($this->reportType) {
                case 'sales':
                    $export = new SalesExport(
                        $this->startDate,
                        $this->endDate,
                        $this->filters['user_id'] ?? null,
                        $this->filters['payment_method'] ?? null
                    );
                    Excel::store($export, $path, 'public');
                    break;

                // Add other report types here

                default:
                    throw new \Exception('Jenis laporan tidak didukung');
            }

            // Send notification to user
            $user->notify(new ReportGeneratedNotification(
                $this->reportType,
                $filename,
                Storage::url($path)
            ));
        } catch (\Exception $e) {
            // Log error or send error notification
            Log::error('Report generation failed: ' . $e->getMessage());
        }
    }

    private function generateFilename()
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "{$this->reportType}_report_{$timestamp}.xlsx";
    }
}
