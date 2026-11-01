<?php

namespace App\Console\Commands;

use App\Models\{AnimalGroup, BatchOrder, Employee, InventoryMovement, MortalityRecord, Payable, PoultryHouse, PoultryHouseAssignment, Role, SaleProduct, SalesCustomer, User};
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use App\Support\Demo\HttpReplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo data: one ISA Brown layer batch from order to lay.
 *
 * The batch's life is replayed day by day through the system's own forms, signed in as the person who
 * does each job (Finance pays, the Production Manager records arrival, health and feed deliveries, the
 * store manager receives and issues, attendants record feed, deaths and eggs, supervisors approve, Sales
 * sells), with the clock set to that day. The system's own rules keep bird counts, stock and money right.
 *
 * Everything runs in one database transaction: nothing is kept unless --commit is given and every step
 * succeeds. The ids before the run are saved to storage/app/demo-poultry-batch.json so the demo can be
 * told apart from real records later.
 */
class DemoPoultryBatch extends Command
{
    protected $signature = 'demo:poultry-batch {--commit : Keep the records (otherwise everything is rolled back)} {--until= : Stop on this date (Y-m-d) instead of today}';

    protected $description = 'Enter demo records for a 12,000-bird ISA Brown layer batch, from order to lay';

    private HttpReplay $http;

    private Carbon $arrival;
    private Carbon $today;
    private array $u = [];          // people by job
    private array $attendants = []; // employee id => user
    private AnimalGroup $batch;
    private array $houses = [];     // house id => [attendant employee ids]
    private array $assignments = [];
    private array $stock = [];      // feed name => kg (mirror of the feed store, for ordering)
    private array $pendingDeaths = [];
    private int $requests = 0;
    private array $customers = [];

    // Money: the owners' capital lands in Operations; Poultry has its own account for every poultry cost;
    // customers pay through the farm's MTN and Airtel merchant codes; cash is petty cash for the farm's own small buys.
    private const POULTRY = 'Centenary Bank - Poultry';
    private const MERCHANT = ['MTN Mobile Money' => '402871', 'Airtel Mobile Money' => '6650921'];

    // Ingredients the farm mixes (feed type id => feed store name).
    private const FEEDS = [23 => 'Broken Maize', 22 => 'Bran', 24 => 'Sunflower', 25 => 'Soya', 26 => 'Lime'];
    private const PRICE_PER_KG = ['Broken Maize' => 1150, 'Bran' => 650, 'Sunflower' => 1350, 'Soya' => 2700, 'Lime' => 350];
    private const MIX = [ // share of each ingredient in 100 kg of feed
        'chick' => ['Broken Maize' => 55, 'Soya' => 20, 'Sunflower' => 12, 'Bran' => 10, 'Lime' => 3],
        'grower' => ['Broken Maize' => 50, 'Bran' => 22, 'Sunflower' => 15, 'Soya' => 10, 'Lime' => 3],
        'layer' => ['Broken Maize' => 50, 'Bran' => 15, 'Sunflower' => 13, 'Soya' => 12, 'Lime' => 10],
    ];
    // Grams of feed per bird per day, by week of age (ISA Brown guide, rounded).
    private const GRAMS = [1 => 11, 17, 23, 29, 34, 39, 43, 47, 51, 54, 57, 60, 63, 66, 69, 72, 76, 82, 90, 98, 104, 108, 111, 113, 114, 115, 115];
    // Vaccination programme: age (days) => [medication id, finance sub-account, price per 1,000-dose vial, dose, route note].
    private const VACCINES = [
        7 => [2, 'Newcastle LaSota', 18000, 'Eye drop, 1 dose per bird'],
        14 => [4, 'Gumboro Vaccine', 26000, 'In drinking water'],
        21 => [4, 'Gumboro Booster', 26000, 'In drinking water'],
        28 => [2, 'Newcastle LaSota', 18000, 'In drinking water'],
        42 => [5, 'Fowl Pox', 32000, 'Wing web stab'],
        56 => [2, 'Newcastle LaSota', 18000, 'In drinking water'],
        70 => [6, 'Fowl Typhoid', 45000, 'Injection, 0.5 ml'],
        84 => [7, 'Newcastle IB', 24000, 'In drinking water'],
        112 => [3, 'Newcastle Plain', 65000, 'Injection, 0.5 ml (killed vaccine before lay)'],
        140 => [2, 'Newcastle LaSota', 18000, 'In drinking water'],
    ];

    public function handle(): int
    {
        $this->http = new HttpReplay(app());
        config(['session.driver' => 'array', 'mail.default' => 'array']);
        $this->http->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, ValidateCsrfToken::class]);
        mt_srand(2604);

        $this->arrival = Carbon::parse('2026-04-11');
        $this->today = Carbon::parse($this->option('until') ?: now()->toDateString());
        abort_if(AnimalGroup::where('code', 'B001/26')->exists(), 1, 'The demo batch B001/26 is already in the system.');

        $snapshot = collect(['animal_groups', 'egg_collections', 'egg_collection_lines', 'feed_consumptions', 'feed_stock_movements', 'feed_delivery_verifications', 'feed_delivery_bags', 'mortality_records', 'health_events', 'financial_transactions', 'payables', 'payable_lines', 'payable_payments', 'batch_orders', 'batch_order_payments', 'inventory_items', 'inventory_movements', 'sales', 'sale_lines', 'sales_payments', 'sales_customers', 'poultry_houses', 'poultry_house_movements', 'poultry_house_assignments', 'employees', 'users', 'executive_communications', 'audit_logs', 'approvals', 'approval_steps', 'employee_records', 'sick_bays', 'sick_bay_admissions', 'sick_bay_checks'])
            ->mapWithKeys(fn ($t) => [$t => (int) DB::table($t)->max('id')])->all();

        DB::beginTransaction();
        try {
            $this->people();
            $this->beforeArrival();
            for ($day = $this->arrival->copy(); $day->lte($this->today); $day->addDay()) {
                $this->live($day->copy());
            }
            $this->tidyMessages();
            $this->summary();
        } catch (\Throwable $e) {
            foreach (DB::table('inventory_items')->get() as $item) {
                $rows = DB::table('inventory_movements')->where('inventory_item_id', $item->id)->get(['movement_type', 'quantity', 'location', 'receipt_status', 'movement_date']);
                $this->line("  item {$item->id} {$item->name} ({$item->unit}): ".$rows->map(fn ($r) => "{$r->movement_type} {$r->quantity} @{$r->location} [{$r->receipt_status}] {$r->movement_date}")->implode('; '));
            }
            DB::rollBack();
            Carbon::setTestNow();
            $this->error('Stopped: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());
            return self::FAILURE;
        }
        Carbon::setTestNow();

        if (! $this->option('commit')) {
            DB::rollBack();
            $this->warn('Dry run finished: everything was rolled back. Run again with --commit to keep the records.');
            return self::SUCCESS;
        }
        DB::commit();
        file_put_contents(storage_path('app/demo-poultry-batch.json'), json_encode(['created_at' => now()->toDateTimeString(), 'batch' => 'B001/26', 'ids_before' => $snapshot], JSON_PRETTY_PRINT));
        $this->info('Demo records saved.');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ people

    private function people(): void
    {
        $by = fn (string $username) => User::where('username', $username)->firstOrFail();
        $this->u = [
            'finance' => $by('BAF 049'), 'pm' => $by('BAF 003'), 'store' => $by('BAF 007'),
            'sales' => $by('BAF 004'), 'hr' => $by('BAF 001'),
        ];
        $role = Role::where('code', 'poultry-attendant')->firstOrFail();
        $dept = (int) DB::table('departments')->where('code', 'poultry')->value('id');
        // The Production Manager runs the poultry section, so he is posted to Production and Poultry.
        $this->u['pm']->departments()->syncWithoutDetaching(DB::table('departments')->whereIn('code', ['production', 'poultry'])->pluck('id')->all());
        $this->u['pm'] = $this->u['pm']->fresh();

        $armon = $by('BAF 006');
        $this->attendants[$armon->employee_id] = $armon;
        $names = [['Brian', 'Ssekandi', 'male'], ['Grace', 'Nansubuga', 'female'], ['Peter', 'Okello', 'male'], ['Joan', 'Atim', 'female'], ['Moses', 'Kato', 'male']];
        foreach ($names as $i => [$first, $last, $gender]) {
            $staffNo = 'BAF '.str_pad((string) (8 + $i), 3, '0', STR_PAD_LEFT);
            abort_if(Employee::where('staff_no', $staffNo)->exists(), 1, "Staff number {$staffNo} is already used.");
            $employee = Employee::create([
                'staff_no' => $staffNo, 'first_name' => $first, 'last_name' => $last, 'gender' => $gender,
                'phone' => '07'.mt_rand(50, 89).mt_rand(1000000, 9999999), 'address' => 'Kituntu, Mpigi',
                'department_id' => $dept, 'role_id' => $role->id, 'job_title' => 'Poultry attendant',
                'employment_type' => 'casual', 'hire_date' => '2026-03-25', 'status' => 'active',
                'base_salary' => 300000, 'pay_frequency' => 'monthly', 'payment_method' => 'mobile_money', 'on_payroll' => true,
                'notes' => 'Demo data (poultry batch B001/26).', 'created_by' => $this->u['hr']->id,
            ]);
            $user = new User();
            $user->forceFill([
                'name' => "{$first} {$last}", 'username' => $staffNo, 'email' => Str::lower($first.'.'.$last).'@demo.brooksagro.test',
                'password' => bcrypt(Str::random(40)), 'role_id' => $role->id, 'department_id' => $dept, 'employee_id' => $employee->id,
                'must_change_password' => false, // random password nobody knows: the account cannot be signed into
            ])->save();
            $user->roles()->syncWithoutDetaching([$role->id]);
            $this->attendants[$employee->id] = $user->fresh();
        }
        foreach ($this->attendants as $user) {
            abort_unless($user->canDo('feed-consumption', 'create') && $user->canDo('egg-entry', 'create') && $user->canDo('mortality-entry', 'create'), 1, $user->displayName().' cannot record poultry work.');
        }

        // Customers (the farm records every customer).
        foreach ([
            ['Lule Steven', '0772 418 330', 'Ntinda, Kampala'],
            ['Nsubunga Abdnul', '0701 552 914', 'Kabalagala, Kampala'],
            ['Nkwanga Eriab', '0752 300 118', 'Kisubi, Wakiso'],
            ['Ssenabulya Vincent', '0783 116 402', 'Nakawa Market stall 41'],
            ['Kyagaba Ibrahim', '0758 921 006', 'Kalerwe Market'],
            ['Birigwa Charles', '0774 663 250', 'Mukono Town'],
            ['Walakira Gerald', '0705 288 417', 'Mpigi Road, Kyengera'],
            ['Wansaso Godfrey', '0789 450 771', 'Gayaza Trading Centre'],
        ] as [$name, $phone, $address]) {
            $this->post('sales', 'sales.customers.store', [], ['name' => $name, 'phone' => $phone, 'address' => $address], '2026-08-10 09:00');
            $this->customers[] = SalesCustomer::where('name', $name)->value('id');
        }
    }

    // ---------------------------------------------------------- before arrival

    private function beforeArrival(): void
    {
        $f = 'finance';
        // The owners put in the start-up capital; Finance moves what the batch needs into the Poultry account.
        $this->post($f, 'finance.transactions.store', [], [
            'transaction_date' => '2026-03-02', 'transaction_category' => 'capital_and_equity', 'account_group' => 'Capital', 'entry_account' => 'Capital Introduced',
            'department_id' => DB::table('departments')->where('code', 'administration')->value('id'), 'cost_centre' => 'Executive Office',
            'counterparty' => 'Owners (Counsel Bugaya and Shamira Nakyanzi)', 'account' => 'Centenary Bank - Operations', 'amount' => 300000000,
            'payment_method' => 'bank', 'reference' => 'CAP-2026-001', 'description' => 'Start-up capital for the ISA Brown layer project',
        ], '2026-03-02 10:00');
        $this->transfer('Centenary Bank - Operations', self::POULTRY, 230000000, '2026-03-03', 'Funding for the new layer batch (chicks, brooding, feed to point of lay)');
        // Chicks ordered from the hatchery, paid in two stages.
        $this->post($f, 'finance.batch-orders.store', [], [
            'batch_type' => 'poultry', 'supplier_name' => 'Kiryowa Hatchery Ltd', 'breed' => 'ISA Brown', 'quantity_ordered' => 12000,
            'order_total' => 24000000, 'ordered_on' => '2026-03-16', 'expected_on' => '2026-04-11',
            'notes' => '12,000 day-old ISA Brown layer chicks at UGX 2,000 each. Hatchery adds 2% extra chicks free to cover transport losses.',
        ], '2026-03-16 10:00');
        $order = BatchOrder::latest('id')->firstOrFail();
        $this->post($f, 'finance.batch-orders.payments.store', ['batchOrder' => $order->id], ['paid_on' => '2026-03-17', 'amount' => 12000000, 'payment_method' => 'bank', 'farm_account' => self::POULTRY, 'reference' => 'TT-0317-KH'], '2026-03-17 11:00');
        $this->post($f, 'finance.batch-orders.payments.store', ['batchOrder' => $order->id], ['paid_on' => '2026-04-09', 'amount' => 12000000, 'payment_method' => 'bank', 'farm_account' => self::POULTRY, 'reference' => 'TT-0409-KH'], '2026-04-09 11:30');
        $this->orderId = $order->id;

        // Brooding preparation.
        $this->purchase('2026-04-02', 'Husks', null, 'V One-time Vendor', 'Cash', 'cash', 320, 'bag', 4500, 'Wood shavings for brooder litter');
        $this->purchase('2026-04-03', 'Biosecurity', 'Vilukill', 'V Ultra Vet', self::POULTRY, 'bank', 40, 'litre', 38000, 'Disinfectant for brooder clean-out and foot baths', ['store' => 'farm', 'unit' => 'litre']);
        $this->purchase('2026-04-04', 'Other Direct Purchases', 'Charcoal / Firewood', 'V Kaffika', 'Cash', 'cash', 60, 'bag', 38000, 'Charcoal for brooder heating (first 4 weeks)');
        $this->purchase('2026-04-04', 'Other Direct Purchases', 'Thermometers', 'V Ultra Vet', 'MTN Mobile Money', 'mobile_money', 8, 'piece', 25000, 'Brooder thermometers', ['store' => 'farm', 'unit' => 'piece']);
        $this->purchase('2026-04-06', 'Vaccination', 'Chick Booster', 'V Ultra Vet', self::POULTRY, 'bank', 24, 'sachet', 15000, 'Glucose and vitamin booster for arrival stress', ['store' => 'veterinary', 'unit' => 'sachet']);
        $this->confirmPending('2026-04-07');
        $this->confirmPending('2026-04-08');

        // First ingredients, delivered on the Tuesday before arrival.
        $this->feedDelivery(Carbon::parse('2026-04-07'), 12222, 0);
    }

    private ?int $orderId = null;

    // -------------------------------------------------------------- every day

    private function live(Carbon $day): void
    {
        $age = (int) $this->arrival->diffInDays($day);
        $isToday = $day->isSameDay($this->today);

        if ($age === 0) $this->arrive($day);
        if ($day->dayOfWeekIso === 2 && $age > 0) $this->feedDelivery($day, (int) $this->batch->fresh()->current_quantity, $age);
        if ($age === 49) $this->moveToLayingHouses($day);
        // Vaccines are bought two days before they are due and checked into the store the day before.
        if (isset(self::VACCINES[$age + 2])) $this->buyVaccine($day, self::VACCINES[$age + 2]);
        if (isset(self::VACCINES[$age + 1])) $this->confirmPending($day->toDateString());
        if (isset(self::VACCINES[$age])) $this->vaccinate($day, $age, self::VACCINES[$age]);
        $this->healthEvents($day, $age);
        $this->assessDeaths($day);

        // Attendants: deaths first thing, feed in the morning, eggs at 10:00 and 16:00.
        foreach ($this->occupiedHouses() as $house) {
            $died = $this->deathsFor($house, $age);
            [$deaths, $culled, $note] = $died;
            if ($deaths + $culled > 0) {
                $this->post($this->attendantFor($house), 'daily.deaths.store', ['batch' => $this->batch->id], array_filter([
                    'poultry_house_id' => $house->id, 'quantity_died' => $deaths, 'quantity_culled' => $culled ?: null, 'worker_note' => $note,
                ], fn ($v) => $v !== null), $day->format('Y-m-d').($age === 0 ? ' 17:' : ' 07:').str_pad((string) mt_rand(10, 50), 2, '0', STR_PAD_LEFT));
                $this->pendingDeaths[] = [MortalityRecord::latest('id')->value('id'), $day->toDateString(), $age, $house->id];
            }
        }
        foreach ($this->occupiedHouses() as $house) {
            $this->feed($house, $day, $age, $isToday);
        }
        if ($age >= 126) {
            foreach ($this->occupiedHouses() as $house) {
                $this->eggs($house, $day, $age, $isToday);
            }
        }
        if ($age >= 127) $this->sell($day, $age);
        if ($age >= 128) $this->approveSales($day);
        if ($day->dayOfWeekIso === 1) $this->sweepMobileMoney($day);
        if ($age === 160) $this->sellCulls($day);
        $this->monthEnd($day);
        $this->traysAndSupplies($day, $age);

        if ($day->day === 1 || $isToday) {
            $b = $this->batch->fresh();
            $this->line(sprintf('  %s  day %3d  birds %s  requests %d', $day->toDateString(), $age, number_format($b->current_quantity), $this->requests));
        }
    }

    private function arrive(Carbon $day): void
    {
        $this->post('pm', 'batches.store', [], [
            'code' => 'B001/26', 'name' => 'ISA Brown layers — April 2026', 'department_id' => DB::table('departments')->where('code', 'poultry')->value('id'),
            'breed_id' => 1, 'purpose' => 'layer', 'batch_order_id' => $this->orderId,
            'received_date' => $day->toDateString(), 'age_days_on_arrival' => 0, 'quantity_received' => 12000, 'quantity_free' => 240, 'transit_deaths' => 18,
            'new_houses' => [
                ['house_number' => 'Brooder A', 'house_type' => 'brooder', 'bird_quantity' => 6111],
                ['house_number' => 'Brooder B', 'house_type' => 'brooder', 'bird_quantity' => 6111],
            ],
        ], $day->format('Y-m-d').' 09:30');
        $this->batch = AnimalGroup::where('code', 'B001/26')->firstOrFail();
        $brooders = PoultryHouse::where('animal_group_id', $this->batch->id)->orderBy('house_number')->get();
        $emp = array_keys($this->attendants);
        $this->assign($brooders[0], [$emp[0], $emp[1]], $day);
        $this->assign($brooders[1], [$emp[2], $emp[3]], $day);

        // Marek's was given at the hatchery; arrival booster in the water.
        $this->health($day, 'vaccination', 1, null, 'Given at the hatchery (certificate KH-2604-118)', 1, 'Day-old vaccination done at hatchery before dispatch.', 0, 'Kiryowa Hatchery');
        $this->health($day, 'treatment', 18, null, 'Glucose + vitamins in water', 3, 'Arrival stress after 4-hour transport. 18 chicks dead on arrival.', 360000, 'Henry Tebandeke');
        $this->issueToHouses($day, 'Chick Booster', 24);
    }

    private function assign(PoultryHouse $house, array $employeeIds, Carbon $day): void
    {
        foreach ($employeeIds as $id) {
            $this->post('pm', 'poultry-houses.assign', [], ['poultry_house_id' => $house->id, 'employee_id' => $id, 'assigned_from' => $day->toDateString()], $day->format('Y-m-d').' 10:00');
            $this->assignments[] = PoultryHouseAssignment::latest('id')->value('id');
        }
        $this->houses[$house->id] = $employeeIds;
    }

    private function moveToLayingHouses(Carbon $day): void
    {
        // Houses 1–5 and house 6 become the laying houses; each brooder fills three.
        $targets = PoultryHouse::whereIn('house_number', ['House 1', 'House 2', 'House 3', 'House 4', 'House 5', 'house 6'])->get()
            ->sortBy(fn ($h) => (int) preg_replace('/\D/', '', $h->house_number))->values();
        $brooders = PoultryHouse::where('animal_group_id', $this->batch->id)->where('house_type', 'brooder')->orderBy('house_number')->get();
        foreach ($brooders as $b => $brooder) {
            $left = (int) $brooder->bird_quantity;
            foreach ([0, 1, 2] as $k) {
                $to = $targets[$b * 3 + $k];
                $qty = $k === 2 ? $left : intdiv((int) $brooder->bird_quantity, 3);
                $left -= $qty;
                $this->post('pm', 'poultry-houses.transfer', [], ['from_house_id' => $brooder->id, 'to_house_id' => $to->id, 'bird_quantity' => $qty, 'moved_on' => $day->toDateString(), 'reason' => 'Week 7: out of brooding into laying house'], $day->format('Y-m-d').' 08:00');
            }
        }
        foreach ($this->assignments as $id) {
            $this->post('pm', 'poultry-houses.assignments.end', ['assignment' => $id], [], $day->format('Y-m-d').' 08:40');
        }
        $this->assignments = [];
        $this->houses = [];
        foreach ($targets as $i => $house) {
            $this->assign($house, [array_keys($this->attendants)[$i]], $day);
        }
        $this->health($day, 'treatment', 18, null, 'Electrolytes in water for 3 days', 3, 'Move stress after transfer to laying houses.', 420000, 'Henry Tebandeke');
    }

    // --------------------------------------------------------------- deaths

    private function deathsFor(PoultryHouse $house, int $age): array
    {
        $birds = (int) $house->bird_quantity;
        $rate = match (true) { $age <= 3 => 0.0012, $age <= 7 => 0.0008, $age <= 14 => 0.0004, $age <= 21 => 0.00025, default => 0.00011 };
        $n = $this->poisson($birds * $rate);
        $name = $house->house_number;
        $note = null; $culled = 0;
        // Events.
        if ($name === 'Brooder B' && $age >= 24 && $age <= 28) { $n += [24 => 35, 25 => 52, 26 => 41, 27 => 22, 28 => 9][$age]; $note = 'Bloody droppings, chicks huddling.'; }
        if ($name === 'Brooder A' && $age >= 25 && $age <= 27) { $n += [25 => 8, 26 => 12, 27 => 5][$age]; $note = 'Some chicks with bloody droppings.'; }
        if ($name === 'House 2' && $age === 58) { $n += 14; $note = 'Very hot afternoon, birds panting.'; }
        if ($name === 'House 4' && $age === 78) { $n += 23; $note = 'Stray dogs got in through a torn net at night.'; }
        if ($name === 'House 3' && $age >= 96 && $age <= 100) { $n += [96 => 6, 97 => 11, 98 => 9, 99 => 5, 100 => 3][$age]; $note = 'Birds sneezing, wet eyes.'; }
        if ($name === 'House 1' && $age === 135) { $n += 7; $note = 'Pecked vents found in the morning.'; }
        if ($name === 'House 5' && $age >= 150 && $age <= 152) { $n += [150 => 4, 151 => 6, 152 => 3][$age]; $note = 'Coughing and reduced laying.'; }
        if ($name === 'House 2' && $age === 170) { $n += 9; $note = 'Heat — water tank ran low in the afternoon.'; }

        return [min($n, $birds), $culled, $note];
    }

    private function assessDeaths(Carbon $day): void
    {
        $keep = [];
        foreach ($this->pendingDeaths as [$id, $date, $age, $houseId]) {
            // Assessed the next morning; the last two days stay waiting for the Production Manager.
            if (Carbon::parse($date)->addDay()->gt($day) || $day->diffInDays($this->today) < 2) { $keep[] = [$id, $date, $age, $houseId]; continue; }
            $house = PoultryHouse::find($houseId)?->house_number;
            [$cause, $note, $action, $vet] = match (true) {
                $age <= 7 => ['Cold', 'Weak chicks in the first week; some piled near the walls at night.', 'Brooder temperature checked every 2 hours; extra charcoal at night.', false],
                $age >= 24 && $age <= 28 => ['Coccidiosis', 'Bloody droppings and huddling. Post-mortem: swollen caeca with blood.', 'Amprolium in water for 5 days; litter turned and wet spots removed.', true],
                $age === 58 => ['Heat stress', 'Deaths after a hot afternoon.', 'Side curtains rolled up by 11:00; extra drinkers.', false],
                $age === 78 => ['Predator', 'Dogs entered through a torn net.', 'Net repaired; night watchman told to check the poultry block.', false],
                $age >= 96 && $age <= 100 => ['Respiratory infection', 'Sneezing and wet eyes in House 3.', 'Tylosin for 5 days; ventilation improved.', true],
                $age === 135 => ['Pecking / cannibalism', 'Vent pecking at start of lay.', 'Light hours reduced; injured birds separated.', false],
                $age >= 150 && $age <= 152 => ['Respiratory infection', 'Coughing in House 5 with a drop in lay.', 'Oxytetracycline 5 days; eggs held during withdrawal.', true],
                $age === 170 => ['Water ran out', 'Tank ran low on a hot afternoon.', 'Second tank connected; attendant checks water at 13:00.', false],
                default => ['Cause not established', 'Normal losses. No signs of disease on inspection.', 'Continue normal management.', false],
            };
            $data = ['cause_name' => $cause, 'assessment_note' => $note, 'action_taken' => $action];
            if ($vet) $data += ['vet_called' => 1, 'vet_name' => 'Dr. Ruth Namutebi (Marvis Veterinary Services)'];
            $this->put('pm', 'mortality.assess.update', ['record' => $id], $data, $day->format('Y-m-d').' 08:15');
        }
        $this->pendingDeaths = $keep;
    }

    // ----------------------------------------------------------------- feed

    private function stage(int $age): string
    {
        return $age < 56 ? 'chick' : ($age < 119 ? 'grower' : 'layer');
    }

    private function gramsPerBird(int $age): int
    {
        return self::GRAMS[min(27, intdiv($age, 7) + 1)];
    }

    private function feed(PoultryHouse $house, Carbon $day, int $age, bool $isToday): void
    {
        $kgTotal = $house->bird_quantity * $this->gramsPerBird($age) / 1000 * (mt_rand(97, 103) / 100);
        $data = ['poultry_house_id' => $house->id, 'use' => [], 'loose' => [], 'loose_kg' => []];
        foreach (self::MIX[$this->stage($age)] as $feed => $share) {
            $id = array_search($feed, self::FEEDS, true);
            $kg = round($kgTotal * $share / 100 * 2) / 2;
            if ($kg < 0.5) continue;
            $data['use'][$id] = 1; $data['loose'][$id] = 1; $data['loose_kg'][$id] = $kg;
            $this->stock[$feed] = ($this->stock[$feed] ?? 0) - $kg;
        }
        $this->post($this->attendantFor($house), 'daily.feed.store', ['batch' => $this->batch->id], $data, $day->format('Y-m-d').($age === 0 ? ' 11:' : ' 08:').str_pad((string) mt_rand(5, 55), 2, '0', STR_PAD_LEFT));
        if (! $isToday) {
            foreach (DB::table('feed_consumptions')->where('poultry_house_id', $house->id)->whereDate('used_on', $day)->where('status', 'submitted')->pluck('id') as $id) {
                $this->post('pm', 'supervision.records.review', ['type' => 'poultry_feed', 'id' => $id], ['decision' => 'approved'], $day->format('Y-m-d').' 18:20');
            }
        }
    }

    /** Weekly Tuesday delivery: the Production Manager records supplier weights, the store manager weighs on arrival, Finance pays. */
    private function feedDelivery(Carbon $day, int $birds, int $age): void
    {
        $need = [];
        for ($d = 0; $d < 8; $d++) {
            $a = $age + $d;
            $kg = $birds * $this->gramsPerBird($a) / 1000;
            foreach (self::MIX[$this->stage($a)] as $feed => $share) $need[$feed] = ($need[$feed] ?? 0) + $kg * $share / 100;
        }
        $bags = [];
        $lines = [];
        foreach (self::FEEDS as $feed) {
            $order = max(0, ($need[$feed] ?? 0) * 1.12 - max(0, $this->stock[$feed] ?? 0));
            $count = (int) ceil($order / 100);
            if ($count === 0) continue;
            for ($i = 0; $i < $count; $i++) $bags[] = ['feed_name' => $feed, 'supplier_weight_kg' => 100];
            $lines[$feed] = $count * 100;
        }
        if (! $bags) return;
        $ref = 'FD-'.$day->format('ymd');
        $this->post('pm', 'operations.feed-stock.store', [], [
            'delivery_date' => $day->toDateString(), 'unit_code' => 'poultry', 'quantity_ordered_kg' => array_sum($lines),
            'vehicle_number' => ['UBK 412P', 'UAX 908T', 'UBH 117K'][mt_rand(0, 2)], 'driver_name' => ['Isaac Mugisha', 'Ronald Kiggundu', 'Fred Waiswa'][mt_rand(0, 2)],
            'farm_representative' => 'Henry Tebandeke', 'supplier' => 'V Mazima', 'reference' => $ref, 'bags' => $bags,
        ], $day->format('Y-m-d').' 07:30');
        $delivery = DB::table('feed_delivery_verifications')->latest('id')->first();
        $weights = [];
        foreach (DB::table('feed_delivery_bags')->where('feed_delivery_verification_id', $delivery->id)->pluck('id') as $bagId) {
            $weights[$bagId] = mt_rand(1, 20) === 1 ? mt_rand(960, 985) / 10 : mt_rand(992, 1002) / 10;
        }
        $this->post('store', 'operations.feed-stock.receive', ['delivery' => $delivery->id], ['farm_weights' => $weights, 'farm_notes' => mt_rand(1, 4) === 1 ? 'A few bags torn at the corners; weighed as received.' : null], $day->format('Y-m-d').' 11:15');
        foreach ($lines as $feed => $kg) $this->stock[$feed] = ($this->stock[$feed] ?? 0) + (float) DB::table('feed_stock_movements')->where('reference', 'like', '%'.$ref.'%')->where('feed_name', $feed)->sum('quantity_kg') ?: $kg;

        // Money: maize and bran on a 30-day bill from the miller; soya, sunflower and lime paid by bank the same day.
        $credit = array_intersect_key($lines, array_flip(['Broken Maize', 'Bran']));
        if ($credit) {
            $this->post('finance', 'finance.payables.store', [], [
                'supplier_name' => 'V Mazima', 'invoice_number' => 'MFM-'.$day->format('ymd'), 'invoice_date' => $day->toDateString(),
                'due_date' => $day->copy()->addDays(30)->toDateString(), 'description' => 'Feed ingredients delivered '.$day->format('d M Y').' (delivery '.$ref.')',
                'feed_delivery_ids' => [$delivery->id],
                'lines' => collect($credit)->map(fn ($kg, $feed) => ['item_name' => $feed, 'quantity' => $kg, 'unit' => 'kg', 'amount' => $kg * self::PRICE_PER_KG[$feed]])->values()->all(),
            ], $day->format('Y-m-d').' 14:00');
        }
        foreach (array_diff_key($lines, $credit) as $feed => $kg) {
            $sub = ['Sunflower' => 'Sunflower Meal', 'Soya' => 'Soya Meal', 'Lime' => 'Lime'][$feed];
            $this->purchase($day->toDateString(), 'Feeds', $sub, 'V Mazima', self::POULTRY, 'bank', $kg, 'kg', self::PRICE_PER_KG[$feed], "{$feed} for poultry feed ({$ref})");
        }
        $this->purchase($day->toDateString(), 'Feeds', 'Transport', 'V Kigozi Transportation Services', 'Cash', 'cash', 1, 'trip', 180000, "Transport of feed ingredients ({$ref})");
        $this->payOldBills($day);
    }

    private function payOldBills(Carbon $day): void
    {
        // Bills are paid about three weeks after delivery; the latest ones are still owed.
        foreach (Payable::where('status', 'open')->where('supplier_name', 'V Mazima')->whereDate('invoice_date', '<=', $day->copy()->subDays(20))->get() as $bill) {
            $balance = (float) $bill->amount - (float) $bill->amount_paid;
            $this->fund(self::POULTRY, $balance, $day->toDateString());
            $this->post('finance', 'finance.transactions.store', [], [
                'transaction_date' => $day->toDateString(), 'transaction_category' => 'purchase', 'account_group' => 'Cost of Sales', 'entry_account' => 'Feeds', 'sub_account' => 'Broken Maize',
                'department_id' => $this->productionDept(), 'cost_centre' => 'Poultry', 'counterparty' => 'V Mazima', 'account' => self::POULTRY,
                'amount' => $balance, 'payment_method' => 'bank', 'reference' => 'PAY-'.$bill->invoice_number, 'description' => 'Payment of bill '.$bill->invoice_number,
                'payment_kind' => 'supplier_bill', 'payable_id' => $bill->id,
            ], $day->format('Y-m-d').' 15:00');
        }
    }

    // ----------------------------------------------------------------- eggs

    private function layRate(int $age): float
    {
        $curve = [126 => 0.02, 133 => 0.12, 140 => 0.38, 147 => 0.65, 154 => 0.82, 161 => 0.89, 168 => 0.92, 175 => 0.93, 182 => 0.94, 400 => 0.94];
        $prev = null;
        foreach ($curve as $a => $r) {
            if ($age <= $a) {
                if ($prev === null) return $r;
                [$pa, $pr] = $prev;
                return $pr + ($r - $pr) * ($age - $pa) / ($a - $pa);
            }
            $prev = [$a, $r];
        }
        return 0.94;
    }

    /** Share of eggs per grade id by age: starter eggs (2), medium (3), large (4), damaged (6), dirty (7), broken (8), soft shell (9). */
    private function gradeShares(int $age): array
    {
        return match (true) {
            $age < 140 => [2 => 86, 3 => 4, 6 => 4, 7 => 3, 8 => 2, 9 => 1],
            $age < 154 => [2 => 70, 3 => 22, 6 => 3, 7 => 3, 8 => 1, 9 => 1],
            $age < 168 => [2 => 48, 3 => 45, 6 => 3, 7 => 2, 8 => 1, 9 => 1],
            default => [2 => 33, 3 => 55, 4 => 5, 6 => 3, 7 => 2, 8 => 1, 9 => 1],
        };
    }

    private function eggs(PoultryHouse $house, Carbon $day, int $age, bool $isToday): void
    {
        $rate = $this->layRate($age);
        if ($house->house_number === 'House 5' && $age >= 150 && $age <= 156) $rate *= 0.86; // sick birds lay less
        $total = (int) round($house->bird_quantity * $rate * mt_rand(97, 103) / 100);
        if ($total < 1) return;
        foreach ([['10:', 0.64], ['16:', 0.36]] as $s => [$hour, $part]) {
            $eggs = (int) round($total * $part);
            if ($eggs < 1) continue;
            $data = ['poultry_house_id' => $house->id, 'trays' => [], 'loose' => []];
            $left = $eggs;
            $shares = $this->gradeShares($age);
            $last = array_key_last($shares);
            foreach ($shares as $grade => $share) {
                $n = $grade === $last ? $left : (int) round($eggs * $share / 100);
                $n = min($n, $left);
                $left -= $n;
                if ($n <= 0) continue;
                $data['trays'][$grade] = intdiv($n, 30);
                $data['loose'][$grade] = $n % 30;
            }
            if ($age === 126 && $s === 0) $data['worker_note'] = 'First eggs from this house!';
            $this->post($this->attendantFor($house), 'daily.eggs.store', ['batch' => $this->batch->id], $data, $day->format('Y-m-d').' '.$hour.str_pad((string) mt_rand(0, 40), 2, '0', STR_PAD_LEFT));
            $id = DB::table('egg_collections')->latest('id')->value('id');
            if ($isToday) continue; // today's collections wait for the evening check
            $returned = $house->house_number === 'House 4' && $age === 171 && $s === 1;
            $this->post('sales', 'supervision.records.review', ['type' => 'eggs', 'id' => $id], $returned
                ? ['decision' => 'returned', 'review_note' => 'Tray count does not match what reached the egg store (2 trays short). Please count again.']
                : ['decision' => 'approved'], $day->format('Y-m-d').' 18:'.str_pad((string) mt_rand(0, 45), 2, '0', STR_PAD_LEFT));
        }
    }

    // ---------------------------------------------------------------- sales

    private function availableEggs(SaleProduct $product): int
    {
        $controller = app(\App\Http\Controllers\SalesController::class);
        return (int) (fn () => $this->availableEggCount($product))->call($controller);
    }

    private function sell(Carbon $day, int $age): void
    {
        $products = SaleProduct::whereIn('code', ['EGG-SMALL', 'EGG-MEDIUM', 'EGG-LARGE', 'EGG-DIRTY', 'EGG-DAMAGED'])->get()->keyBy('code');
        Carbon::setTestNow($day->copy()->setTime(11, 0));
        $trays = [];
        foreach ($products as $code => $p) {
            $keep = in_array($code, ['EGG-DIRTY', 'EGG-DAMAGED'], true) ? 0 : 6; // a few trays kept back for walk-in buyers
            $trays[$code] = max(0, intdiv($this->availableEggs($p), 30) - $keep);
        }
        if (array_sum($trays) < 1) return;
        // Regular buyers on their days, the rest to market traders.
        $buyers = collect($this->customers)->shuffle()->take(mt_rand(3, 6))->values();
        foreach ($buyers as $i => $customerId) {
            $lines = [];
            $share = $i === $buyers->count() - 1 ? 1 : (mt_rand(15, 35) / 100);
            foreach (['EGG-MEDIUM', 'EGG-SMALL', 'EGG-LARGE'] as $code) {
                $qty = (int) floor($trays[$code] * $share);
                if ($qty < 1) continue;
                $trays[$code] -= $qty;
                $lines[] = ['product_id' => $products[$code]->id, 'quantity' => $qty, 'unit' => 'tray', 'unit_price' => $products[$code]->unit_price];
            }
            if ($i === 0 && $day->dayOfWeekIso % 3 === 0) {
                foreach (['EGG-DIRTY', 'EGG-DAMAGED'] as $code) {
                    if ($trays[$code] > 0) { $lines[] = ['product_id' => $products[$code]->id, 'quantity' => $trays[$code], 'unit' => 'tray', 'unit_price' => $products[$code]->unit_price]; $trays[$code] = 0; }
                }
            }
            if (! $lines) continue;
            // Customers pay through the farm's merchant codes (MTN or Airtel); no cash is taken.
            $wallet = mt_rand(1, 100) <= 65 ? 'MTN Mobile Money' : 'Airtel Mobile Money';
            $this->post('sales', 'sales.store', [], [
                'sale_date' => $day->toDateString(), 'customer_id' => $customerId, 'payment_type' => 'cash',
                'payment_method' => 'mobile_money', 'farm_account' => $wallet, 'merchant_code' => self::MERCHANT[$wallet], 'lines' => $lines,
            ], $day->format('Y-m-d').' '.(11 + $i).':'.str_pad((string) mt_rand(0, 55), 2, '0', STR_PAD_LEFT));
        }
    }

    private function sellCulls(Carbon $day): void
    {
        $house = PoultryHouse::where('house_number', 'house 6')->first();
        $this->post('sales', 'sales.store', [], [
            'sale_date' => $day->toDateString(), 'customer_id' => $this->customers[4], 'payment_type' => 'cash', 'payment_method' => 'mobile_money', 'farm_account' => 'MTN Mobile Money', 'merchant_code' => self::MERCHANT['MTN Mobile Money'],
            'notes' => '60 poor layers picked out of house 6 (pale combs, not laying) and sold as off-layers.',
            'lines' => [['product_id' => SaleProduct::where('code', 'BIRD-LAYER')->value('id'), 'quantity' => 60, 'unit' => 'bird', 'unit_price' => 17000, 'poultry_house_id' => $house->id]],
        ], $day->format('Y-m-d').' 10:30');
    }

    // --------------------------------------------------------------- health

    private function buyVaccine(Carbon $day, array $vaccine): void
    {
        [, $sub, $price] = $vaccine;
        $vials = (int) ceil($this->batch->fresh()->current_quantity / 1000);
        $this->purchase($day->toDateString(), 'Vaccination', $sub, 'V Ultra Vet', self::POULTRY, 'bank', $vials, 'vial', $price, "{$sub} vaccine for B001/26 ({$vials} vials of 1,000 doses)", ['store' => 'veterinary', 'unit' => 'vial']);
    }

    private function vaccinate(Carbon $day, int $age, array $vaccine): void
    {
        [$medication, $sub, $price, $dose] = $vaccine;
        $vials = (int) ceil($this->batch->fresh()->current_quantity / 1000);
        $this->issueToHouses($day, $sub, $vials);
        $this->health($day, 'vaccination', $medication, null, $dose, 1, "Scheduled vaccination, day {$age}.", $vials * $price, 'Henry Tebandeke');
    }

    private function healthEvents(Carbon $day, int $age): void
    {
        $events = [
            23 => ['Medication', 'Other Drugs', 'Amprolium 20% (1 kg)', 6, 'kg', 55000, 9, 'treatment', 'Amprolium 1 g per litre of water', 5, 'Coccidiosis in both brooders (Brooder B worst).', 'Bloody droppings, swollen caeca at post-mortem.', true],
            95 => ['Medication', 'Other Drugs', 'Tylosin (100 g)', 10, 'sachet', 28000, 10, 'treatment', 'Tylosin 1 g per 2 litres', 5, 'Respiratory signs in House 3.', 'Sneezing, wet eyes; no deaths in other houses.', true],
            118 => ['Vaccination', 'Dewormer', 'Levamisole (1 kg)', 4, 'kg', 42000, 14, 'deworming', 'Levamisole in water for 1 day', 1, 'Deworming before lay.', 'Routine. Eggs not yet in production.', false],
            149 => ['Medication', 'Oxytetracycline', 'Oxytetracycline 20% (1 kg)', 3, 'kg', 48000, 8, 'treatment', 'Oxytetracycline 1 g per litre', 5, 'Coughing and drop in lay in House 5.', 'Eggs from the flock held during the 7-day withdrawal.', true],
        ];
        // Buy the medicine the day before; the store confirms; give it on the day.
        if (isset($events[$age])) {
            [$account, $sub, $item, $qty, $unit, $price] = $events[$age];
            $this->purchase($day->toDateString(), $account, $sub, 'V Ultra Vet', self::POULTRY, 'bank', $qty, $unit, $price, $item, ['store' => 'veterinary', 'unit' => $unit]);
            $this->confirmPending($day->toDateString());
        }
        if (isset($events[$age - 1])) {
            [, $sub, $item, $qty, , $price, $medication, $type, $dose, $days, $reason, $findings, $vet] = $events[$age - 1];
            $this->issueToHouses($day, $sub, $qty);
            $this->health($day, $type, $medication, null, $dose, $days, $reason, $qty * $price, $vet ? 'Dr. Ruth Namutebi' : 'Henry Tebandeke', $findings, $vet);
        }
        if ($age === 126) $this->health($day, 'inspection', null, null, null, 1, 'Point-of-lay check.', 0, 'Henry Tebandeke', 'Average weight 1.52 kg; combs reddening; first eggs today.');
    }

    private function health(Carbon $day, string $type, ?int $medication, ?int $birds, ?string $dose, int $days, string $reason, float $cost, string $by, ?string $findings = null, bool $vet = false): void
    {
        $this->post('pm', 'health.store', [], array_filter([
            'animal_group_id' => $this->batch->id, 'event_type' => $type, 'medication_id' => $medication, 'occurred_on' => $day->toDateString(),
            'birds_treated' => $birds ?? $this->batch->fresh()->current_quantity, 'dose_given' => $dose, 'days_of_course' => $days,
            'reason' => $reason, 'findings' => $findings, 'cost' => $cost ?: null, 'administered_name' => $by, 'vet_involved' => $vet ? 1 : null,
        ], fn ($v) => $v !== null) + ['medication_id' => ''], $day->format('Y-m-d').' 09:00'); // the form always sends the medicine field
    }

    // ---------------------------------------------------------------- store

    /** Finance purchase; with $stock it goes to that store as a delivery to confirm. */
    private function purchase(string $date, string $entry, ?string $sub, string $supplier, string $account, string $method, float $qty, string $unit, float $price, string $description, ?array $stock = null): void
    {
        if ($account === 'MTN Mobile Money') { $account = self::POULTRY; $method = 'bank'; } // suppliers are paid from the bank
        $this->fund($account, round($qty * $price), $date);
        $group = in_array($entry, ['Electricity', 'Water', 'Salary Expense', 'Fuel', 'Security', 'Repairs & General Maintenance'], true) ? 'Operating Costs' : 'Cost of Sales';
        $data = array_filter([
            'transaction_date' => $date, 'transaction_category' => 'purchase', 'account_group' => $group, 'entry_account' => $entry, 'sub_account' => $sub,
            'department_id' => $this->productionDept(), 'cost_centre' => 'Poultry', 'counterparty' => $supplier, 'account' => $account,
            'quantity' => $qty, 'unit_price' => $price, 'amount' => round($qty * $price), 'payment_method' => $method,
            'reference' => strtoupper(Str::random(3)).'-'.Carbon::parse($date)->format('md').'-'.mt_rand(100, 999), 'description' => $description,
        ], fn ($v) => $v !== null);
        if ($stock) {
            $storeId = DB::table('inventory_stores')->where('code', $stock['store'])->value('id');
            $data += ['track_inventory' => 1, 'inventory_store_id' => $storeId, 'inventory_unit' => $stock['unit'], 'inventory_location' => DB::table('inventory_stores')->where('id', $storeId)->value('name')];
        }
        $this->post('finance', 'finance.transactions.store', [], $data, $date.' '.(mt_rand(9, 15)).':'.str_pad((string) mt_rand(0, 59), 2, '0', STR_PAD_LEFT));
    }

    private function confirmPending(string $date): void
    {
        foreach (InventoryMovement::where('receipt_status', 'pending')->whereDate('movement_date', '<=', $date)->get() as $m) {
            $this->post('store', 'inventory.receipts.confirm', ['movement' => $m->id], ['quantity_received' => $m->quantity, 'received_on' => $date, 'receipt_note' => 'Checked against delivery note; seals intact.'], $date.' 10:20');
        }
    }

    /** The store manager issues an item to the occupied houses, shared by birds. */
    private function issueToHouses(Carbon $day, string $itemName, float $qty): void
    {
        $item = DB::table('inventory_items')->where('name', $itemName)->latest('id')->first();
        if (! $item) return;
        $location = DB::table('inventory_stores')->where('id', $item->inventory_store_id)->value('name');
        $houses = $this->occupiedHouses()->values();
        $birds = max(1, $houses->sum('bird_quantity'));
        $whole = floor($qty) == $qty && $qty >= $houses->count();
        $left = $qty;
        foreach ($houses as $i => $house) {
            $share = $qty * $house->bird_quantity / $birds;
            $part = $i === $houses->count() - 1 ? round($left, 2) : ($whole ? floor($share) : round($share, 1));
            $left = round($left - $part, 2);
            if ($part <= 0) continue;
            $this->post('store', 'inventory.movements.store', [], [
                'inventory_item_id' => $item->id, 'poultry_house_id' => $house->id, 'movement_date' => $day->toDateString(), 'movement_type' => 'issued',
                'location' => $location, 'quantity' => $part, 'supplier_or_recipient' => $this->attendantFor($house)->displayName(), 'reference' => 'ISS-'.$day->format('md').'-'.$house->id,
            ], $day->format('Y-m-d').' 08:30');
        }
    }

    private function traysAndSupplies(Carbon $day, int $age): void
    {
        // Egg trays from just before lay, every three weeks.
        if ($age >= 120 && ($age - 120) % 21 === 0) {
            $this->purchase($day->toDateString(), 'Other Direct Purchases', 'Paper Trays', 'V One-time Vendor', self::POULTRY, 'bank', $age === 120 ? 6000 : 10000, 'piece', 320, 'Paper egg trays (30 eggs)', ['store' => 'farm', 'unit' => 'piece']);
            $this->confirmPending($day->toDateString());
        }
        // Trays go out to the houses every Monday once laying starts.
        if ($age >= 125 && $day->dayOfWeekIso === 1) {
            $eggsPerWeek = $this->occupiedHouses()->sum('bird_quantity') * $this->layRate($age + 3) * 7;
            $this->issueToHouses($day, 'Paper Trays', max(60, (int) ceil($eggsPerWeek / 30 * 1.05)));
        }
        // Disinfectant for foot baths on the 1st of each month; restocked when it runs low.
        if ($day->day === 1) {
            $item = DB::table('inventory_items')->where('name', 'Vilukill')->value('id');
            $left = (float) DB::table('inventory_movements')->where('inventory_item_id', $item)->where('receipt_status', 'confirmed')
                ->selectRaw("coalesce(sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end), 0) as b")->value('b');
            if ($left < 12) {
                $this->purchase($day->copy()->subDays(2)->toDateString(), 'Biosecurity', 'Vilukill', 'V Ultra Vet', self::POULTRY, 'bank', 40, 'litre', 38000, 'Disinfectant restock for foot baths', ['store' => 'farm', 'unit' => 'litre']);
                $this->confirmPending($day->copy()->subDay()->toDateString());
            }
            $this->issueToHouses($day, 'Vilukill', 6);
        }
    }

    private function monthEnd(Carbon $day): void
    {
        if (! $day->isLastOfMonth() || $day->isSameDay($this->today)) return;
        $month = $day->format('F');
        $brooding = $day->month <= 5;
        $this->purchase($day->toDateString(), 'Electricity', null, 'UMEME / Yaka', self::POULTRY, 'bank', 1, 'month', $brooding ? 1450000 : 780000, "Poultry block electricity, {$month}".($brooding ? ' (brooder lights)' : ''));
        $this->purchase($day->toDateString(), 'Water', null, 'NWSC', self::POULTRY, 'bank', 1, 'month', 320000, "Water for the poultry block, {$month}");
        $this->purchase($day->toDateString(), 'Salary Expense', null, 'Poultry attendants (6)', self::POULTRY, 'bank', 6, 'person', 300000, "Poultry attendants' wages, {$month}");
        if ($day->month >= 8) $this->purchase($day->toDateString(), 'Fuel', null, 'V One-time Vendor', 'Cash', 'cash', 1, 'month', 420000, "Generator fuel for egg store lights, {$month}");
    }

    // ---------------------------------------------------------------- money

    /** Balance of a farm account from the ledger (received + transfers in - paid - transfers out). */
    private function balance(string $account): float
    {
        $in = (float) DB::table('financial_transactions')->whereNull('reversed_at')->where(fn ($q) => $q->where(fn ($x) => $x->where('type', 'money_received')->where('account', $account))
            ->orWhere(fn ($x) => $x->where('type', 'transfer')->where('counterparty', $account)))->sum('amount');
        $out = (float) DB::table('financial_transactions')->whereNull('reversed_at')->where(fn ($q) => $q->where(fn ($x) => $x->where('type', 'money_paid')->where('account', $account))
            ->orWhere(fn ($x) => $x->where('type', 'transfer')->where('account', $account)))->sum('amount');
        return $in - $out;
    }

    /** Make sure an account can pay: petty cash is topped up from the Poultry account; the Poultry account from Sales, then Operations. */
    private function fund(string $account, float $amount, string $date): void
    {
        $short = $amount - $this->balance($account);
        if ($short <= 0) return;
        if ($account === 'Cash') {
            $topUp = (float) (ceil(($short + 1000000) / 500000) * 500000);
            $this->fund(self::POULTRY, $topUp, $date);
            $this->transfer(self::POULTRY, 'Cash', $topUp, $date, 'Petty cash top-up for small farm purchases');
            return;
        }
        abort_unless($account === self::POULTRY, 1, "{$account} cannot pay UGX ".number_format($amount).' on '.$date.'.');
        $topUp = (float) (ceil(($short + 15000000) / 5000000) * 5000000);
        foreach (['Centenary Bank - Sales', 'Centenary Bank - Operations'] as $source) {
            $available = floor($this->balance($source));
            if ($available <= 0 || $topUp <= 0) continue;
            $move = min($topUp, $available);
            $this->transfer($source, self::POULTRY, $move, $date, $source === 'Centenary Bank - Sales' ? 'Egg sales money to run the poultry unit' : 'Capital released to the poultry unit');
            $topUp -= $move;
        }
        abort_if($this->balance(self::POULTRY) < $amount, 1, 'Not enough money for UGX '.number_format($amount).' on '.$date.'.');
    }

    private function transfer(string $from, string $to, float $amount, string $date, string $why): void
    {
        $this->post('finance', 'finance.transactions.store', [], [
            'transaction_date' => $date, 'transaction_category' => 'banking', 'account_group' => $to === 'Cash' ? 'Cash in Hand' : 'Bank Accounts', 'entry_account' => $to === 'Cash' ? 'Cash on Hand' : $to,
            'department_id' => $this->productionDept(), 'cost_centre' => 'Poultry', 'account' => $from, 'counterparty' => $to, 'amount' => $amount,
            'reference' => 'TRF-'.Carbon::parse($date)->format('ymd').'-'.mt_rand(100, 999), 'description' => $why,
        ], $date.' 08:'.str_pad((string) mt_rand(0, 50), 2, '0', STR_PAD_LEFT));
    }

    /** Every Monday the week's mobile money is moved to the Sales bank account. */
    private function sweepMobileMoney(Carbon $day): void
    {
        foreach (array_keys(self::MERCHANT) as $wallet) {
            $amount = floor($this->balance($wallet));
            if ($amount > 0) $this->transfer($wallet, 'Centenary Bank - Sales', $amount, $day->toDateString(), "Weekly sweep of {$wallet} (merchant ".self::MERCHANT[$wallet].') to the bank');
        }
    }

    /** Finance approves the previous days' sales; the CEO gives final approval a day later. The last day's sales still wait. */
    private function approveSales(Carbon $day): void
    {
        if ($day->isSameDay($this->today)) return;
        $ceo = User::where('username', 'BAF 002')->firstOrFail();
        $pending = DB::table('approvals')->join('sales', 'sales.id', '=', 'approvals.approvable_id')->where('approvals.module_key', 'sales')->where('approvals.status', 'pending')
            ->whereDate('sales.sale_date', '<', $day->toDateString())->orderBy('sales.sale_date')->get(['approvals.id', 'sales.sale_date']);
        foreach ($pending as $a) {
            $step = DB::table('approval_steps')->where('approval_id', $a->id)->where('status', 'pending')->orderBy('stage')->first();
            if (! $step) continue;
            if ($step->role_code === 'finance-manager') {
                $this->post('finance', 'approvals.decide', ['approval' => $a->id], ['decision' => 'approved', 'decision_note' => 'Matches the merchant statement.'], $day->format('Y-m-d').' 09:30');
            } elseif ($step->role_code === 'ceo' && Carbon::parse($a->sale_date)->lt($day->copy()->subDay())) {
                $this->post($ceo, 'approvals.decide', ['approval' => $a->id], ['decision' => 'approved'], $day->format('Y-m-d').' 17:10');
            }
        }
    }

    // -------------------------------------------------------------- helpers

    private function occupiedHouses()
    {
        return PoultryHouse::where('animal_group_id', $this->batch->id ?? 0)->where('bird_quantity', '>', 0)->orderBy('id')->get()
            ->sortBy(fn ($h) => [$h->house_type === 'brooder' ? 0 : 1, (int) preg_replace('/\D/', '', $h->house_number) ?: 0, $h->house_number])->values();
    }

    private function attendantFor(PoultryHouse $house): User
    {
        $ids = $this->houses[$house->id] ?? array_keys($this->attendants);
        return $this->attendants[$ids[mt_rand(0, count($ids) - 1)]];
    }

    private function productionDept(): int
    {
        return (int) DB::table('departments')->where('code', 'production')->value('id');
    }

    private function poisson(float $mean): int
    {
        if ($mean <= 0) return 0;
        $l = exp(-$mean); $k = 0; $p = 1.0;
        do { $k++; $p *= mt_rand() / mt_getrandmax(); } while ($p > $l);
        return $k - 1;
    }

    private function post($who, string $route, array $params, array $data, string $at)
    {
        return $this->send('post', $who, $route, $params, $data, $at);
    }

    private function put($who, string $route, array $params, array $data, string $at)
    {
        return $this->send('put', $who, $route, $params, $data, $at);
    }

    private function send(string $method, $who, string $route, array $params, array $data, string $at)
    {
        $user = $who instanceof User ? $who : $this->u[$who];
        Carbon::setTestNow(Carbon::parse($at));
        app('session.store')->forget('errors');
        $this->http->actingAs($user->fresh());
        $response = $this->http->{$method}(route($route, $params, false), $data);
        $this->requests++;
        $errors = app('session.store')->get('errors');
        $status = $response->getStatusCode();
        $location = $response->isRedirect() ? (string) $response->headers->get('Location') : '';
        if (preg_match('#/(login|password/change)$#', parse_url($location, PHP_URL_PATH) ?? '')) {
            throw new \RuntimeException("{$route} as {$user->displayName()} on {$at}: sent to {$location} instead of saving.");
        }
        if ($status >= 400 || ($errors && $errors->any())) {
            $message = $errors && $errors->any() ? implode(' | ', $errors->all()) : ($response->exception?->getMessage() ?: 'HTTP '.$status);
            throw new \RuntimeException("{$route} as {$user->displayName()} on {$at}: {$message}");
        }

        return $response;
    }

    private function tidyMessages(): void
    {
        // Messages older than two days have been seen.
        DB::table('executive_communications')->whereNull('read_at')->where('created_at', '<', $this->today->copy()->subDays(2))->update(['read_at' => DB::raw('created_at')]);
    }

    private function summary(): void
    {
        $b = $this->batch->fresh();
        $this->newLine();
        $this->info("Batch {$b->code}: {$b->quantity_received} received + {$b->quantity_free} free − {$b->transit_deaths} dead on arrival; now ".number_format($b->current_quantity).' birds; '.number_format($b->total_mortality).' deaths, '.number_format($b->total_culled).' culled, '.number_format($b->total_sold).' sold.');
        $rows = [
            ['Egg collections', DB::table('egg_collections')->count(), number_format(DB::table('egg_collections')->sum('total_eggs')).' eggs'],
            ['Feed records', DB::table('feed_consumptions')->count(), number_format(DB::table('feed_consumptions')->sum('quantity_kg')).' kg'],
            ['Feed deliveries', DB::table('feed_delivery_verifications')->count(), number_format(DB::table('feed_stock_movements')->where('movement_type', 'received')->sum('quantity_kg')).' kg received'],
            ['Deaths records', DB::table('mortality_records')->count(), ''],
            ['Health records', DB::table('health_events')->count(), ''],
            ['Sales', DB::table('sales')->count(), 'UGX '.number_format(DB::table('sales')->sum('total'))],
            ['Money paid out', DB::table('financial_transactions')->where('type', 'money_paid')->count(), 'UGX '.number_format(DB::table('financial_transactions')->where('type', 'money_paid')->sum('amount'))],
            ['Supplier bills owed', Payable::where('status', 'open')->count(), 'UGX '.number_format(Payable::where('status', 'open')->get()->sum(fn ($p) => $p->amount - $p->amount_paid))],
            ['Store movements', DB::table('inventory_movements')->count(), ''],
            ['Requests replayed', $this->requests, ''],
        ];
        $this->table(['Record', 'Count', 'Total'], $rows);
    }
}
