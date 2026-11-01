<?php

namespace Tests\Feature;

use App\Models\{AnimalGroup, Department, Module, MortalityRecord, Role, User};
use App\Services\SellableStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellableStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_on_hand_for_any_day_and_daily_movement(): void
    {
        Module::create(['key' => 'sales', 'name' => 'Sales', 'group' => 'Sales', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $user = User::create(['username' => 'stock-test', 'name' => 'Sales', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $department = Department::create(['code' => 'poultry', 'name' => 'Poultry']);
        $d = fn (int $daysAgo) => today()->subDays($daysAgo)->toDateString();

        // Birds: 100 received 10 days ago, 3 died 5 days ago.
        $batch = AnimalGroup::create(['code' => 'B-001', 'department_id' => $department->id, 'purpose' => 'layer', 'received_date' => $d(10), 'quantity_received' => 100, 'current_quantity' => 97, 'status' => 'active']);
        MortalityRecord::create(['animal_group_id' => $batch->id, 'department_id' => $department->id, 'occurred_on' => $d(5), 'quantity_died' => 3, 'quantity_culled' => 0, 'recorded_by' => $user->id, 'recorded_at' => now(), 'status' => 'pending_assessment']);

        // Goats: group of 10 registered 20 days ago, 2 kids born 4 days ago, 1 died yesterday.
        $group = DB::table('livestock_groups')->insertGetId(['unit_code' => 'goats', 'group_code' => 'G-01', 'breed' => 'Boer', 'source' => 'purchased', 'acquired_on' => $d(20), 'initial_quantity' => 10, 'current_quantity' => 11, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('livestock_production_records')->insert(['livestock_group_id' => $group, 'recorded_on' => $d(4), 'production_type' => 'births', 'quantity' => 2, 'measure_unit' => 'kids', 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('livestock_mortality_records')->insert(['livestock_group_id' => $group, 'occurred_on' => $d(1), 'quantity' => 1, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        // Garden: 50 kg tomatoes harvested 6 days ago, 5 kg lost 2 days ago (approved records only).
        $garden = fn (string $type, int $daysAgo, float $qty, string $status = 'approved') => DB::table('farm_unit_records')->insert(['unit_code' => 'garden', 'record_type' => $type, 'record_date' => $d($daysAgo), 'subject' => 'Tomatoes', 'quantity' => $qty, 'measure_unit' => 'kg', 'verification_status' => $status, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $garden('harvest', 6, 50);
        $garden('loss', 2, 5);
        $garden('harvest', 1, 999, 'submitted'); // not yet approved: not counted

        $stock = new SellableStock;
        $today = $stock->onHand(today())->keyBy('item');
        $this->assertSame(97.0, $today['B-001']['quantity'], 'matches the batch count');
        $this->assertSame(11.0, $today['G-01']['quantity'], 'matches the group count');
        $this->assertSame(45.0, $today['Tomatoes']['quantity']);

        // Any earlier day: what was in stock at the end of that day.
        $past = $stock->onHand(today()->subDays(7))->keyBy('item');
        $this->assertSame(100.0, $past['B-001']['quantity']);
        $this->assertSame(10.0, $past['G-01']['quantity']);
        $this->assertArrayNotHasKey('Tomatoes', $past->all(), 'not listed before its first harvest');

        // Filters: unit, search, hide empty.
        $this->assertSame(['G-01'], $stock->onHand(today(), ['unit' => 'goats'])->pluck('item')->all());
        $this->assertSame(['Tomatoes'], $stock->onHand(today(), ['q' => 'toma'])->pluck('item')->all());
        $this->assertNotContains('Tomatoes', $stock->onHand(today()->subDays(7), ['hide_empty' => true])->pluck('item')->all());

        // Daily movement: opening, added, lost and closing per day.
        $goatDays = $stock->movements(today()->subDays(5), today(), ['unit' => 'goats'])->keyBy('date');
        $this->assertSame([10.0, 2.0, 12.0], [(float) $goatDays[$d(4)]['opening'], (float) $goatDays[$d(4)]['added'], (float) $goatDays[$d(4)]['closing']]);
        $this->assertSame([12.0, 1.0, 11.0], [(float) $goatDays[$d(1)]['opening'], (float) $goatDays[$d(1)]['lost'], (float) $goatDays[$d(1)]['closing']]);

        // The Sales screen shows both views.
        $this->actingAs($user)->get(route('sales.index', ['screen' => 'inventory']))->assertOk()->assertSee('Stock on hand')->assertSee('B-001')->assertSee('45 kg');
        $this->actingAs($user)->get(route('sales.index', ['screen' => 'inventory', 'stock_view' => 'movement', 'unit' => 'goats']))->assertOk()->assertSee('Opening')->assertSee('births');
    }
}
