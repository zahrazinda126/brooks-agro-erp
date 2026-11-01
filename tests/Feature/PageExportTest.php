<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PageExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_screen_exports_to_a_standard_excel_workbook(): void
    {
        $user = User::create(['username'=>'export-test','name'=>'Export Test','password'=>'password','is_active'=>true,'must_change_password'=>false]);
        // Captured from the Cash Book screen by public/js/app-ui.js (title, filters, summary figures, table with totals).
        $payload = file_get_contents(base_path('tests/fixtures/page-export-cash-book.json'));

        $response = $this->actingAs($user)->post(route('page.export'), ['payload' => $payload]);

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $content, 'a zip-based .xlsx file');
        if (class_exists(ZipArchive::class)) {
            $path = tempnam(sys_get_temp_dir(), 'xlsx');
            file_put_contents($path, $content);
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('BROOKS AGRO FARM LIMITED', $sheet);
            $this->assertStringContainsString('Cash Book', $sheet);
            $this->assertStringContainsString('Opening balance', $sheet);
            $zip->close();
            unlink($path);
        }
    }

    public function test_empty_pages_are_refused(): void
    {
        $user = User::create(['username'=>'export-test','name'=>'Export Test','password'=>'password','is_active'=>true,'must_change_password'=>false]);
        $this->actingAs($user)->post(route('page.export'), ['payload' => json_encode(['title' => 'Nothing', 'tables' => []])])->assertStatus(422);
    }
}
