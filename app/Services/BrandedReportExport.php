<?php

namespace App\Services;

use App\Support\Xlsx\XlsxWorkbook;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The standard report export: a native Excel (.xlsx) workbook in the brand format
 * (company line, title, period, prepared by/at, maroon table headers, gold section rows,
 * subtotal and total rows, real numbers with UGX formatting, frozen header, filters,
 * landscape print fitted to the page width).
 */
class BrandedReportExport
{
    /** One table. Rows may be plain lists or ['cells' => [...], 'style' => detail|heading|subtotal|total|percent]. */
    public function download(
        string $title,
        string $subtitle,
        array $headers,
        iterable $rows,
        string $filename,
        ?string $generatedBy = null,
    ): StreamedResponse {
        return $this->downloadSections($title, $subtitle, [['headers' => $headers, 'rows' => $rows]], $filename, $generatedBy);
    }

    /**
     * Several tables in one report, each with an optional heading, on one sheet; or pass
     * ['sheet' => 'Name'] on a section to start a new worksheet for it.
     */
    public function downloadSections(string $title, string $subtitle, array $sections, string $filename, ?string $generatedBy = null): StreamedResponse
    {
        $workbook = new XlsxWorkbook();
        $meta = ['Prepared '.now()->format('d M Y, H:i').($generatedBy ? ' by '.$generatedBy : '')];
        $sheets = [];
        foreach ($sections as $section) {
            $sheetName = $section['sheet'] ?? ($sheets ? array_key_last($sheets) : $title);
            $sheets[$sheetName][] = [
                'heading' => $section['heading'] ?? null,
                'headers' => array_values($section['headers'] ?? []),
                'rows' => collect($section['rows'] ?? [])->all(),
            ];
        }
        foreach ($sheets as $name => $sheetSections) {
            $workbook->addSheet(['name' => $name, 'title' => $title, 'subtitle' => $subtitle, 'meta' => $meta, 'sections' => $sheetSections]);
        }

        $name = str($filename)->beforeLast('.')->slug().'.xlsx';
        $content = $workbook->toString();

        return response()->streamDownload(fn () => print($content), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
        ]);
    }
}
