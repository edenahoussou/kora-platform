<?php

namespace App\Modules\Finance\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceExportService
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, string|int|null>>  $rows
     */
    public function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn (string|int|null $cell): string => $this->safeCell($cell), $row), ';', '"', '');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, string|int|null>>  $rows
     */
    public function xlsx(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $line = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_map(fn (string|int|null $cell): string => $this->safeCell($cell), $row), null, 'A'.$line);
            $line++;
        }

        $path = tempnam(storage_path('app'), 'kora-finance-');
        if ($path === false) {
            throw new \RuntimeException('Impossible de préparer l’export XLSX.');
        }
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function safeCell(string|int|null $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@-]/u', $cell) === 1 ? "'".$cell : $cell;
    }
}
