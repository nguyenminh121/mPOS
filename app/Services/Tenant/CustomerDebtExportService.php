<?php

namespace App\Services\Tenant;

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CustomerDebtExportService
{
    public function generate(User $user, Customer $customer, array $filters = []): array
    {
        $tenantId = (int) $user->tenant_id;
        $customerId = (int) $customer->id;

        $query = AccountReceivable::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->with(['order:id,code'])
            ->orderBy('paid_at')
            ->orderBy('created_at')
            ->orderBy('id');

        $fromDate = Arr::get($filters, 'from_date');
        $toDate = Arr::get($filters, 'to_date');
        $orderCode = trim((string) Arr::get($filters, 'order_code', ''));

        if ($fromDate) {
            $query->whereDate('paid_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('paid_at', '<=', $toDate);
        }

        if ($orderCode !== '') {
            $query->whereHas('order', function ($orderQuery) use ($orderCode) {
                $orderQuery->where('code', 'like', '%'.$orderCode.'%');
            });
        }

        $rows = $query->get();

        $spreadsheet = $this->loadTemplate();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'THỜI GIAN');
        $sheet->setCellValue('B1', 'MÃ CHỨNG TỪ');
        $sheet->setCellValue('C1', 'LOẠI');
        $sheet->setCellValue('D1', 'DIỄN GIẢI');
        $sheet->setCellValue('E1', 'GHI NỢ');
        $sheet->setCellValue('F1', 'GHI CÓ');
        $sheet->setCellValue('G1', 'DƯ NỢ SAU CÙNG');
        $sheet->setCellValue('H1', 'GHI CHÚ');

        $line = 2;
        foreach ($rows as $entry) {
            $amount = (float) ($entry->amount ?? 0);
            $sheet->setCellValue('A'.$line, $this->formatDateTime($entry->paid_at ?? $entry->created_at));
            $sheet->setCellValue('B'.$line, $entry->order?->code ?? '-');
            $sheet->setCellValue('C'.$line, (string) $entry->type);
            $sheet->setCellValue('D'.$line, $this->typeText((string) $entry->type));
            $sheet->setCellValue('E'.$line, $amount > 0 ? $amount : 0);
            $sheet->setCellValue('F'.$line, $amount < 0 ? abs($amount) : 0);
            $sheet->setCellValue('G'.$line, (float) ($entry->balance_after ?? 0));
            $sheet->setCellValue('H'.$line, (string) ($entry->note ?? ''));
            $line++;
        }

        $sheet->setCellValue('A'.$line, 'Khách hàng: '.$customer->name);
        $sheet->setCellValue('B'.$line, 'Tổng bút toán: '.$rows->count());

        $filename = 'cong-no-'.$customerId.'-'.now()->format('Ymd_His').'.xlsx';
        $fullPath = storage_path('app/temp/'.$filename);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($fullPath);

        return [
            'path' => $fullPath,
            'filename' => $filename,
        ];
    }

    private function loadTemplate(): Spreadsheet
    {
        $templatePaths = [
            base_path('../documents/server_docs/Mau_cong_no.xlsx'),
            base_path('documents/server_docs/Mau_cong_no.xlsx'),
        ];

        foreach ($templatePaths as $path) {
            if (is_file($path)) {
                return IOFactory::load($path);
            }
        }

        return new Spreadsheet();
    }

    private function typeText(string $type): string
    {
        return match (strtoupper($type)) {
            'INVOICE' => 'Ghi nhận công nợ',
            'PAYMENT' => 'Thanh toán công nợ',
            'CREDIT_NOTE' => 'Giảm công nợ',
            'DEBIT_NOTE' => 'Tăng công nợ',
            'ADJUST' => 'Điều chỉnh công nợ',
            'WRITE_OFF' => 'Xóa nợ',
            default => $type,
        };
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format('d/m/Y H:i');
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
