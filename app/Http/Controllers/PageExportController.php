<?php

namespace App\Http\Controllers;

use App\Services\BrandedReportExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Export to Excel" for any screen: the page sends the summary figures, filters and tables
 * it is showing (public/js/app-ui.js) and receives them as a standard branded .xlsx.
 * Only data already displayed to the signed-in user is exported.
 */
class PageExportController extends Controller
{
    public function store(Request $request, BrandedReportExport $export): StreamedResponse
    {
        $request->validate(['payload' => ['required', 'string', 'max:8000000']]);
        $data = json_decode($request->input('payload'), true);
        abort_unless(is_array($data), 422, 'The page data could not be read.');

        $title = mb_substr(trim((string) ($data['title'] ?? '')) ?: 'Report', 0, 120);
        $filters = collect($data['filters'] ?? [])->take(20)
            ->map(fn ($f) => trim(mb_substr((string) ($f['label'] ?? ''), 0, 60).': '.mb_substr((string) ($f['value'] ?? ''), 0, 120)))
            ->filter(fn ($line) => $line !== ':')->join(' · ');

        $sections = [];
        $stats = collect($data['stats'] ?? [])->take(40)->map(fn ($s) => [mb_substr((string) ($s['label'] ?? ''), 0, 120), mb_substr((string) ($s['value'] ?? ''), 0, 120)])->all();
        if ($stats) $sections[] = ['heading' => 'Summary', 'headers' => ['Measure', 'Value'], 'rows' => $stats];

        foreach (array_slice($data['tables'] ?? [], 0, 40) as $table) {
            $headers = array_map(fn ($h) => mb_substr((string) $h, 0, 200), array_slice($table['headers'] ?? [], 0, 60));
            $rows = [];
            foreach (array_slice($table['rows'] ?? [], 0, 20000) as $row) {
                $style = in_array($row['style'] ?? 'detail', ['detail', 'heading', 'subtotal', 'total'], true) ? $row['style'] : 'detail';
                $rows[] = ['cells' => array_map(fn ($c) => mb_substr((string) $c, 0, 2000), array_slice($row['cells'] ?? [], 0, 60)), 'style' => $style];
            }
            if ($headers || $rows) $sections[] = ['heading' => mb_substr((string) ($table['heading'] ?? ''), 0, 160) ?: null, 'headers' => $headers, 'rows' => $rows];
        }
        abort_if($sections === [], 422, 'There is nothing on this page to export.');

        return $export->downloadSections($title, $filters ?: 'As shown on screen', $sections,
            $title.'-'.now()->format('Ymd-His'), $request->user()->displayName());
    }
}
