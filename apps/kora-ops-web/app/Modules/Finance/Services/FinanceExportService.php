<?php

namespace App\Modules\Finance\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
                fputcsv($stream, array_map(fn (string|int|null $cell): string|int => $this->safeCell($cell), $row), ';', '"', '');
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
        return $this->workbook($filename, [[
            'title' => 'Données',
            'headers' => $headers,
            'rows' => $rows,
        ]]);
    }

    /**
     * @param  list<array{title: string, headers: list<string>, rows: iterable<array<int, string|int|null>>}>  $sheets
     */
    public function workbook(string $filename, array $sheets): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $index => $definition) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle($this->safeSheetTitle($definition['title']));
            $sheet->fromArray($definition['headers'], null, 'A1');
            $lastColumn = $sheet->getHighestColumn();
            $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '001D4E']],
            ]);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:'.$lastColumn.'1');

            $line = 2;
            foreach ($definition['rows'] as $row) {
                $sheet->fromArray(array_map(fn (string|int|null $cell): string|int => $this->safeCell($cell), $row), null, 'A'.$line);
                $line++;
            }

            foreach (range('A', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

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

    private function safeCell(string|int|null $value): string|int
    {
        if (is_int($value)) {
            return $value;
        }

        $cell = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@-]/u', $cell) === 1 ? "'".$cell : $cell;
    }

    private function safeSheetTitle(string $title): string
    {
        $sanitized = preg_replace('~[\\/?*\[\]:]~u', ' ', $title) ?? 'Feuille';

        return mb_substr(trim($sanitized), 0, 31) ?: 'Feuille';
    }
}
