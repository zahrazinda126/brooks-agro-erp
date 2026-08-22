<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FeedStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FeedStockBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_issue_cannot_make_stock_negative(): void
    {
        $user = User::factory()->create(['username' => 'feed-test-one', 'is_active' => true]);
        DB::table('feed_stock_movements')->insert([
            'unit_code' => 'poultry', 'movement_date' => today(),
            'movement_type' => 'received', 'feed_name' => 'Bran',
            'quantity_kg' => 50, 'recorded_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            app(FeedStockService::class)->issue([
                'unit_code' => 'poultry', 'movement_date' => today(),
                'movement_type' => 'consumed', 'feed_name' => 'Bran',
                'quantity_kg' => 50.01, 'recorded_by' => $user->id,
            ]);
            $this->fail('A feed issue larger than available stock was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Only 50.00 kg', $exception->errors()['quantity_kg'][0]);
        }

        $this->assertSame(1, DB::table('feed_stock_movements')->count());
        $this->assertSame(50.0, app(FeedStockService::class)->balance('poultry', 'Bran'));
    }

    public function test_feed_issue_can_use_the_exact_available_balance(): void
    {
        $user = User::factory()->create(['username' => 'feed-test-two', 'is_active' => true]);
        DB::table('feed_stock_movements')->insert([
            'unit_code' => 'goats', 'movement_date' => today(),
            'movement_type' => 'received', 'feed_name' => 'Bran',
            'quantity_kg' => 25, 'recorded_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(FeedStockService::class)->issue([
            'unit_code' => 'goats', 'movement_date' => today(),
            'movement_type' => 'fed', 'feed_name' => 'Bran',
            'quantity_kg' => 25, 'recorded_by' => $user->id,
        ]);

        $this->assertSame(0.0, app(FeedStockService::class)->balance('goats', 'Bran'));
    }
}
