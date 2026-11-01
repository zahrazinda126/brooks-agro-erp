<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\FeedStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedDeliveryUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_delivery_for_goats_is_verified_and_added_to_goat_feed_stock(): void
    {
        config(['farm.feed_delivery_weekday' => null]);
        $manager = User::create(['username' => 'pm-test', 'name' => 'Production', 'password' => 'password', 'is_active' => true, 'must_change_password' => false,
            'role_id' => Role::create(['code' => 'production-manager', 'name' => 'Production Manager', 'rank' => 3, 'is_active' => true])->id]);
        $store = User::create(['username' => 'store-test', 'name' => 'Store', 'password' => 'password', 'is_active' => true, 'must_change_password' => false,
            'role_id' => Role::create(['code' => 'store-keeper', 'name' => 'Farm Store Manager', 'rank' => 4, 'is_active' => true])->id]);

        $this->actingAs($manager)->post(route('operations.feed-stock.store'), [
            'unit_code' => 'goats', 'delivery_date' => today()->toDateString(), 'quantity_ordered_kg' => 100, 'supplier' => 'Mill Ltd',
            'bags' => [['feed_name' => 'Bran', 'supplier_weight_kg' => 50], ['feed_name' => 'Bran', 'supplier_weight_kg' => 50]],
        ])->assertSessionHasNoErrors();

        $delivery = DB::table('feed_delivery_verifications')->first();
        $this->assertSame('goats', $delivery->unit_code);

        $bags = DB::table('feed_delivery_bags')->pluck('id');
        $this->actingAs($store)->post(route('operations.feed-stock.receive', $delivery->id), [
            'farm_weights' => [$bags[0] => 49.5, $bags[1] => 50],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $stock = app(FeedStockService::class);
        $this->assertSame(99.5, $stock->balance('goats', 'Bran'), 'farm weight goes to goat stock');
        $this->assertSame(0.0, $stock->balance('poultry', 'Bran'), 'poultry stock untouched');
    }

    public function test_a_feed_not_on_the_list_can_be_typed_and_then_joins_the_list(): void
    {
        config(['farm.feed_delivery_weekday' => null]);
        $manager = User::create(['username' => 'pm-test', 'name' => 'Production', 'password' => 'password', 'is_active' => true, 'must_change_password' => false,
            'role_id' => Role::create(['code' => 'production-manager', 'name' => 'Production Manager', 'rank' => 3, 'is_active' => true])->id]);

        $this->actingAs($manager)->post(route('operations.feed-stock.store'), [
            'unit_code' => 'poultry', 'delivery_date' => today()->toDateString(), 'quantity_ordered_kg' => 150, 'supplier' => 'Mill Ltd',
            'bags' => [['feed_name' => '  cotton  seed cake ', 'supplier_weight_kg' => 50], ['feed_name' => 'bran', 'supplier_weight_kg' => 50],
                ['feed_name' => 'Cotton seed cake', 'supplier_weight_kg' => 50]],
        ])->assertSessionHasNoErrors();

        $names = DB::table('feed_delivery_bags')->distinct()->orderBy('feed_name')->pluck('feed_name')->all();
        $this->assertSame(['Bran', 'Cotton seed cake'], $names, 'typed names are tidied and matched to the list spelling');
        $this->assertContains('Cotton seed cake', \App\Http\Controllers\Operations\FeedStockController::feedNames(), 'a new feed joins the list');
        $this->assertTrue(\App\Support\FeedBillMatch::isFeedItem('cotton seed cake'), 'and is recognised on supplier bills');
    }
}
