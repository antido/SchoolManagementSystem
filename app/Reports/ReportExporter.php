<?php

namespace App\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a ReportResult into a downloadable CSV, Excel or PDF file.
 */
class ReportExporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function download(ReportResult $result, string $format): Response
    {
        $filename = Str::slug($result->title).'-'.now()->format('Y-m-d-His').'.'.$format;

        return match ($format) {
            'csv' => $this->csv($result, $filename),
            'xlsx' => $this->xlsx($result, $filename),
            'pdf' => $this->pdf($result, $filename),
        };
    }

    private function csv(ReportResult $result, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($result): void {
            $out = fopen('php://output', 'w');
            // A byte-order mark so Excel opens the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $result->columns, escape: '');

            foreach ($result->rows as $row) {
                fputcsv($out, array_map($this->safeCell(...), $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(ReportResult $result, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit($result->title, 28, ''));

        $line = 1;
        $sheet->setCellValueExplicit("A{$line}", $result->title, DataType::TYPE_STRING);
        $sheet->getStyle("A{$line}")->getFont()->setBold(true)->setSize(14);

        foreach ([...$result->subtitle, ...array_map(fn (array $item) => "{$item['label']}: {$item['value']}", $result->summary)] as $text) {
            $sheet->setCellValueExplicit('A'.++$line, $text, DataType::TYPE_STRING);
        }

        $line += 2;
        $sheet->fromArray($result->columns, null, "A{$line}");
        $sheet->getStyle("A{$line}:".$sheet->getHighestColumn().$line)->getFont()->setBold(true);
        $sheet->freezePane('A'.($line + 1));

        foreach ($result->rows as $row) {
            $line++;

            foreach (array_values($row) as $index => $value) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).$line;

                // Text is stored as text, so a value that looks like a formula is never run.
                is_string($value)
                    ? $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING)
                    : $sheet->setCellValue($cell, $value);
            }
        }

        foreach (range(1, count($result->columns)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function pdf(ReportResult $result, string $filename): Response
    {
        return Pdf::loadView('reports.pdf', [
            'result' => $result,
            'school' => config('app.name'),
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])
            ->setPaper('a4', count($result->columns) > 5 ? 'landscape' : 'portrait')
            ->download($filename);
    }

    /**
     * Stop spreadsheet programs from running CSV text as a formula: a value
     * that starts with =, +, - or @ gets a leading apostrophe.
     */
    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }
}
