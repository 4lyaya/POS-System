<?php

namespace App\Pdf;

use App\Models\Sale;
use App\Models\User;
use Mpdf\Mpdf;
use Carbon\Carbon;

class SalesReportPdf
{
    protected $startDate;
    protected $endDate;
    protected $userId;
    protected $paymentMethod;
    protected $mpdf;
    protected $storeInfo;

    public function __construct($startDate, $endDate, $userId = null, $paymentMethod = null)
    {
        $this->startDate = Carbon::parse($startDate);
        $this->endDate = Carbon::parse($endDate);
        $this->userId = $userId;
        $this->paymentMethod = $paymentMethod;
        $this->storeInfo = [
            'name' => config('app.name', 'Toko Saya'),
            'address' => 'Jl. Contoh No. 123, Kota Contoh',
            'phone' => '(021) 12345678',
        ];
        $this->initializeMpdf();
    }

    protected function initializeMpdf(): void
    {
        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,
            'default_font' => 'arial',
            'tempDir' => storage_path('app/mpdf/tmp'),
        ];

        $this->mpdf = new Mpdf($config);
        $this->mpdf->SetHeader('|Laporan Penjualan|');
        $this->mpdf->SetFooter('{PAGENO}');
    }

    public function generate(): string
    {
        $sales = $this->getSalesData();
        $summary = $this->getSummaryData($sales);

        $this->mpdf->WriteHTML($this->getHeader());
        $this->mpdf->WriteHTML($this->getReportInfo());
        $this->mpdf->WriteHTML($this->getSummarySection($summary));
        $this->mpdf->WriteHTML($this->getSalesTable($sales));

        return $this->mpdf->Output('', 'S');
    }

    protected function getSalesData()
    {
        $query = Sale::with(['customer', 'user'])
            ->whereBetween('sale_date', [$this->startDate, $this->endDate])
            ->orderBy('sale_date', 'desc');

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->paymentMethod) {
            $query->where('payment_method', $this->paymentMethod);
        }

        return $query->get();
    }

    protected function getSummaryData($sales)
    {
        $totalSales = $sales->sum('grand_total');
        $totalItems = $sales->sum('items_count');
        $totalTransactions = $sales->count();
        $averageTransaction = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;

        $paymentMethodBreakdown = $sales->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('grand_total')
            ];
        });

        return [
            'total_sales' => $totalSales,
            'total_items' => $totalItems,
            'total_transactions' => $totalTransactions,
            'average_transaction' => $averageTransaction,
            'payment_methods' => $paymentMethodBreakdown,
        ];
    }

    protected function getHeader(): string
    {
        return '
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="margin: 0; color: #333;">' . $this->storeInfo['name'] . '</h1>
            <p style="margin: 5px 0; color: #666;">' . $this->storeInfo['address'] . ' | ' . $this->storeInfo['phone'] . '</p>
            <h2 style="margin: 10px 0; color: #555;">Laporan Penjualan</h2>
        </div>
        ';
    }

    protected function getReportInfo(): string
    {
        $period = $this->startDate->format('d/m/Y') . ' - ' . $this->endDate->format('d/m/Y');
        $cashierInfo = $this->userId ? User::find($this->userId)->name : 'Semua Kasir';
        $paymentMethodInfo = $this->paymentMethod ? ucfirst($this->paymentMethod) : 'Semua Metode';

        $generatedAt = now()->translatedFormat('l, d F Y H:i:s');

        return '
        <div style="margin-bottom: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 30%;"><strong>Periode:</strong></td>
                    <td>' . $period . '</td>
                </tr>
                <tr>
                    <td><strong>Kasir:</strong></td>
                    <td>' . $cashierInfo . '</td>
                </tr>
                <tr>
                    <td><strong>Metode Pembayaran:</strong></td>
                    <td>' . $paymentMethodInfo . '</td>
                </tr>
                <tr>
                    <td><strong>Dibuat Pada:</strong></td>
                    <td>' . $generatedAt . '</td>
                </tr>
            </table>
        </div>
        ';
    }

    protected function getSummarySection($summary): string
    {
        $html = '
        <div style="margin-bottom: 20px;">
            <h3 style="color: #555; border-bottom: 2px solid #007bff; padding-bottom: 5px;">Ringkasan</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #007bff; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Total Penjualan</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">Rp ' . number_format($summary['total_sales'], 0, ',', '.') . '</p>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #28a745; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Total Transaksi</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">' . number_format($summary['total_transactions'], 0, ',', '.') . '</p>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #17a2b8; color: white; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Rata-rata Transaksi</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">Rp ' . number_format($summary['average_transaction'], 0, ',', '.') . '</p>
                </div>
                <div style="flex: 1; min-width: 200px; padding: 15px; background: #ffc107; color: #333; border-radius: 5px;">
                    <h4 style="margin: 0 0 5px 0; font-size: 14px;">Total Item Terjual</h4>
                    <p style="margin: 0; font-size: 24px; font-weight: bold;">' . number_format($summary['total_items'], 0, ',', '.') . '</p>
                </div>
            </div>
        ';

        if ($summary['payment_methods']->isNotEmpty()) {
            $html .= '
            <div style="margin-top: 15px;">
                <h4 style="margin: 0 0 10px 0; color: #555;">Breakdown Metode Pembayaran</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: #6c757d; color: white;">
                            <th style="padding: 8px; text-align: left;">Metode</th>
                            <th style="padding: 8px; text-align: right;">Jumlah Transaksi</th>
                            <th style="padding: 8px; text-align: right;">Total (Rp)</th>
                            <th style="padding: 8px; text-align: right;">Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            foreach ($summary['payment_methods'] as $method => $data) {
                $percentage = $summary['total_sales'] > 0 ? ($data['total'] / $summary['total_sales']) * 100 : 0;
                $methodLabel = ucfirst($method);

                $html .= '
                <tr style="border-bottom: 1px solid #dee2e6;">
                    <td style="padding: 8px;">' . $methodLabel . '</td>
                    <td style="padding: 8px; text-align: right;">' . number_format($data['count'], 0, ',', '.') . '</td>
                    <td style="padding: 8px; text-align: right;">' . number_format($data['total'], 0, ',', '.') . '</td>
                    <td style="padding: 8px; text-align: right;">' . number_format($percentage, 1) . '%</td>
                </tr>
                ';
            }

            $html .= '
                    </tbody>
                </table>
            </div>
            ';
        }

        $html .= '</div>';

        return $html;
    }

    protected function getSalesTable($sales): string
    {
        $html = '
        <div style="margin-bottom: 20px;">
            <h3 style="color: #555; border-bottom: 2px solid #007bff; padding-bottom: 5px;">Detail Transaksi</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                <thead>
                    <tr style="background: #343a40; color: white;">
                        <th style="padding: 8px; text-align: left;">No. Invoice</th>
                        <th style="padding: 8px; text-align: left;">Tanggal</th>
                        <th style="padding: 8px; text-align: left;">Customer</th>
                        <th style="padding: 8px; text-align: left;">Kasir</th>
                        <th style="padding: 8px; text-align: right;">Items</th>
                        <th style="padding: 8px; text-align: right;">Subtotal</th>
                        <th style="padding: 8px; text-align: right;">Pajak</th>
                        <th style="padding: 8px; text-align: right;">Diskon</th>
                        <th style="padding: 8px; text-align: right;">Total</th>
                        <th style="padding: 8px; text-align: left;">Metode</th>
                        <th style="padding: 8px; text-align: left;">Status</th>
                    </tr>
                </thead>
                <tbody>
        ';

        foreach ($sales as $sale) {
            $statusColor = match ($sale->payment_status) {
                'paid' => '#28a745',
                'partial' => '#ffc107',
                'unpaid' => '#dc3545',
                'cancelled' => '#6c757d',
                default => '#6c757d',
            };

            $paymentMethod = match ($sale->payment_method) {
                'cash' => 'Tunai',
                'transfer' => 'Transfer',
                'qris' => 'QRIS',
                'debit' => 'Debit',
                'credit' => 'Kredit',
                default => $sale->payment_method,
            };

            $html .= '
            <tr style="border-bottom: 1px solid #dee2e6;">
                <td style="padding: 8px;">' . $sale->invoice_number . '</td>
                <td style="padding: 8px;">' . $sale->sale_date->format('d/m/Y') . '</td>
                <td style="padding: 8px;">' . ($sale->customer?->name ?? 'Umum') . '</td>
                <td style="padding: 8px;">' . $sale->user->name . '</td>
                <td style="padding: 8px; text-align: right;">' . $sale->items_count . '</td>
                <td style="padding: 8px; text-align: right;">' . number_format($sale->subtotal, 0, ',', '.') . '</td>
                <td style="padding: 8px; text-align: right;">' . number_format($sale->tax, 0, ',', '.') . '</td>
                <td style="padding: 8px; text-align: right;">' . number_format($sale->discount, 0, ',', '.') . '</td>
                <td style="padding: 8px; text-align: right; font-weight: bold;">' . number_format($sale->grand_total, 0, ',', '.') . '</td>
                <td style="padding: 8px;">' . $paymentMethod . '</td>
                <td style="padding: 8px; color: ' . $statusColor . '; font-weight: bold;">' . ucfirst($sale->payment_status) . '</td>
            </tr>
            ';
        }

        $html .= '
                </tbody>
            </table>
        </div>
        ';

        return $html;
    }
}
