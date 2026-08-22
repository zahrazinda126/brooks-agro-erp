<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandedReportExport
{
    public function download(
        string $title,
        string $subtitle,
        array $headers,
        iterable $rows,
        string $filename,
        ?string $generatedBy = null,
    ): StreamedResponse {
        $normalisedRows = collect($rows)->map(function ($row) {
            if (is_array($row) && array_key_exists('cells', $row)) {
                return ['cells' => array_values($row['cells']), 'style' => $row['style'] ?? 'detail'];
            }

            return ['cells' => array_values((array) $row), 'style' => 'detail'];
        })->all();

        $logoPath = public_path('images/logo.png');
        $logo = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;
        $name = str($filename)->beforeLast('.')->slug().'.xls';

        return response()->streamDownload(function () use ($title, $subtitle, $headers, $normalisedRows, $generatedBy, $logo) {
            echo view('exports.branded-table', [
                'title' => $title,
                'subtitle' => $subtitle,
                'headers' => $headers,
                'rows' => $normalisedRows,
                'generatedBy' => $generatedBy,
                'generatedAt' => now(),
                'logo' => $logo,
            ])->render();
        }, $name, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
        ]);
    }
}
