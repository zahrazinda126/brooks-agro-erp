<?php

namespace Tests\Feature;

use App\Services\BrandedReportExport;
use Tests\TestCase;

class BrandedReportExportTest extends TestCase
{
    public function test_report_download_contains_branding_and_excel_headers(): void
    {
        $response = app(BrandedReportExport::class)->download(
            'Poultry Unit Report', '01 Aug 2026 to 31 Aug 2026',
            ['Date', 'Quantity'], [['2026-08-21', 125]], 'poultry-report.xls', 'Test User'
        );

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('poultry-report.xls', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('BROOKS AGRO FARM LTD.', $content);
        $this->assertStringContainsString('Poultry Unit Report', $content);
        $this->assertStringContainsString('data:image/png;base64,', $content);
        $this->assertStringContainsString('background:#9f2730', $content);
    }
}
