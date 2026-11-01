<?php

namespace Tests\Feature;

use App\Services\WorkbookImportStager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkbookImportStagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_flags_duplicates_links_accounts_and_is_repeatable(): void
    {
        $sale = ['date'=>'2025-07-01T00:00:00','transaction_type'=>'Revenue','account_group'=>'Sales','account'=>'Sales_Large_Eggs','amount'=>700000,'particular'=>'Centenary_Bank_UGX_Account_(Sales)','offset_account'=>'Centenary_Bank_UGX_Account_(Sales)','qa_status'=>'Reviewed','transaction_id'=>1];
        $feed = ['date'=>'2025-07-02T00:00:00','transaction_type'=>'Purchase','account'=>'Feeds','amount'=>250000,'offset_account'=>'Feeds Payable','qa_status'=>null,'transaction_id'=>2];
        $path = tempnam(sys_get_temp_dir(), 'wb');
        file_put_contents($path, json_encode(['transactions'=>[$sale, $feed, $sale], 'analysis'=>['by_type'=>['Revenue'=>['amount'=>1400000]]]]));

        $result = app(WorkbookImportStager::class)->stage($path);
        app(WorkbookImportStager::class)->stage($path);

        $this->assertSame(3, DB::table('finance_import_rows')->count(), 're-staging does not duplicate rows');
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(1, $result['unreviewed']);
        $this->assertTrue($result['revenue_reconciles']);
        $this->assertSame(['Feeds Payable'=>1], $result['unmatched_accounts']);
        $this->assertNotNull(DB::table('finance_import_rows')->where('account', 'Sales_Large_Eggs')->value('finance_account_id'));
        $this->assertSame(0, DB::table('financial_transactions')->count(), 'nothing is posted');
        unlink($path);
    }
}
