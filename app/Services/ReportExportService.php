<?php

namespace App\Services;

use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor laporan ke XLSX memakai OpenSpout (sudah menjadi dependensi Filament,
 * jadi tidak perlu paket tambahan) dan ke PDF lewat HTML yang bisa dicetak —
 * tanpa library PDF baru, karena kolom `value` di tabel laporan berupa angka
 * sehingga tidak butuh tata letak rumit.
 */
class ReportExportService
{
    public function __construct(protected ReportService $reports) {}

    /**
     * @param array{0: array<int, string>, 1: array<int, array<int, string|int>>} $table
     */
    public function xlsx(array $table, string $filename): StreamedResponse
    {
        [$header, $rows] = $table;

        return response()->streamDownload(function () use ($header, $rows): void {
            $options = new Options;
            $writer = new Writer($options);
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues($header));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(
                    fn ($value) => is_int($value) ? $value : (string) $value,
                    $row,
                )));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param array{0: array<int, string>, 1: array<int, array<int, string|int>>} $table
     */
    public function pdf(array $table, string $filename, string $title, string $subtitle): \Illuminate\Http\Response
    {
        [$header, $rows] = $table;

        return response()->view('reports.print', [
            'title' => $title,
            'subtitle' => $subtitle,
            'header' => $header,
            'rows' => $rows,
            'siteName' => \App\Models\SiteSetting::get('site_name', 'ArtaPedia'),
            'generatedAt' => now(),
        ], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ])->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * Rentang tanggal dari pilihan admin. Mengembalikan [from, to] Carbon.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function resolveRange(?string $from, ?string $to): array
    {
        $end = $to ? now()->parse($to) : now();
        $start = $from ? now()->parse($from) : $end->copy()->startOfMonth();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }
}
