<?php

namespace Tests\Feature;

use App\Services\BrandedReportExport;
use Tests\TestCase;

class BrandedReportExportTest extends TestCase
{
    public function test_report_download_is_a_branded_native_excel_workbook(): void
    {
        $response = app(BrandedReportExport::class)->download(
            'Poultry Unit Report', '01 Aug 2026 to 31 Aug 2026',
            ['Date', 'Quantity', 'Value'], [['2026-08-21', 125, 'UGX 1,250,000'], ['cells' => ['Total', 125, 'UGX 1,250,000'], 'style' => 'total']],
            'poultry-report.xls', 'Test User'
        );

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('poultry-report.xlsx', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("PK\x03\x04", $content);

        $sheet = $this->zipEntry($content, 'xl/worksheets/sheet1.xml');
        $styles = $this->zipEntry($content, 'xl/styles.xml');
        $this->assertStringContainsString('BROOKS AGRO FARM LIMITED', $sheet);
        $this->assertStringContainsString('Poultry Unit Report', $sheet);
        $this->assertStringContainsString('Prepared', $sheet);
        $this->assertStringContainsString('<v>1250000</v>', $sheet, 'amounts are real numbers, not text');
        $this->assertStringContainsString('FF9B292D', $styles, 'brand maroon header');
    }

    /** Read one file from a zip (deflate) without the zip extension. */
    private function zipEntry(string $zip, string $name): string
    {
        $offset = 0;
        while (($offset = strpos($zip, "PK\x03\x04", $offset)) !== false) {
            $h = unpack('Vsig/vver/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen', substr($zip, $offset, 30));
            $entry = substr($zip, $offset + 30, $h['nlen']);
            $data = substr($zip, $offset + 30 + $h['nlen'] + $h['elen'], $h['csize']);
            if ($entry === $name) return $h['method'] === 8 ? gzinflate($data) : $data;
            $offset += 30 + $h['nlen'] + $h['elen'] + $h['csize'];
        }
        $this->fail("$name not found in workbook");
    }
}
