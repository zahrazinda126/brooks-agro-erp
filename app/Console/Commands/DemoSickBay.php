<?php

namespace App\Console\Commands;

use App\Models\{MortalityRecord, PoultryHouse, SickBay, SickBayAdmission, User};
use App\Support\Demo\HttpReplay;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demo data: sick bays for every unit and sick bay records for the demo poultry batch (B001/26),
 * entered through the sick bay screens as the Production Manager on the day each happened.
 * Runs in one transaction; nothing is kept without --commit.
 */
class DemoSickBay extends Command
{
    protected $signature = 'demo:sick-bay {--commit : Keep the records (otherwise everything is rolled back)}';

    protected $description = 'Enter demo sick bays and sick bay records for the demo poultry batch';

    private HttpReplay $http;
    private User $pm;

    public function handle(): int
    {
        $this->http = new HttpReplay(app());
        $this->http->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
        config(['session.driver' => 'array']);
        $this->pm = User::where('username', 'BAF 003')->firstOrFail();
        abort_if(SickBay::exists(), 1, 'Sick bays already exist.');

        DB::beginTransaction();
        try {
            foreach ([
                ['poultry', 'Sick bay 1 (Houses 1–3)', 'Wire pen at the end of House 2, with its own feeder and drinker.'],
                ['poultry', 'Sick bay 2 (Houses 4–6)', 'Small room beside House 5.'],
                ['goats', 'Goat sick pen', 'Raised pen behind the goat shed.'],
                ['rabbits', 'Rabbit isolation hutches', 'Four hutches away from the main rabbitry.'],
                ['dogs', 'Dog isolation kennel', 'Kennel by the security office.'],
            ] as [$unit, $name, $notes]) {
                $this->post('sick-bay.bays.store', [], ['unit_code' => $unit, 'name' => $name, 'notes' => $notes], '2026-04-08 10:00');
            }
            $bay1 = SickBay::where('name', 'like', 'Sick bay 1%')->value('id');
            $bay2 = SickBay::where('name', 'like', 'Sick bay 2%')->value('id');
            $vet = 'Marvis Veterinary Services examined the birds.';

            $this->episode($bay1, 'House 3', '2026-07-16', 24, ['Coughing / sneezing', 'Swollen eyes or face'], 'Respiratory infection', 'Picked out during the morning round. '.$vet, [
                ['2026-07-17', 'worse', 0, 2, 0, 'Tylosin 1 g per 2 litres of water', 'Two birds gasping.'],
                ['2026-07-18', 'same', 0, 1, 0, 'Tylosin day 3', null],
                ['2026-07-19', 'improving', 6, 0, 0, 'Tylosin day 4', 'Six eating well and back to House 3.'],
                ['2026-07-21', 'improving', 10, 0, 2, 'Course finished', 'Two with permanent eye damage culled.'],
                ['2026-07-23', 'improving', 3, 0, 0, null, 'Last birds back in House 3.'],
            ]);
            $this->episode($bay1, 'House 1', '2026-08-24', 12, ['Pecked / wounds'], 'Vent pecking at start of lay', 'Birds with pecked vents separated so the wounds can heal.', [
                ['2026-08-25', 'improving', 0, 0, 0, 'Wound spray (purple) twice a day', null],
                ['2026-08-27', 'improving', 9, 1, 0, 'Wound spray', 'One badly injured bird died overnight.'],
                ['2026-08-29', 'improving', 2, 0, 0, null, 'Healed; back to House 1.'],
            ]);
            $this->episode($bay2, 'House 5', '2026-09-08', 30, ['Coughing / sneezing', 'Weak / droopy'], 'Respiratory infection', 'Worst-affected birds from House 5. '.$vet, [
                ['2026-09-09', 'same', 0, 2, 0, 'Oxytetracycline 1 g per litre (7-day egg withdrawal)', null],
                ['2026-09-11', 'improving', 12, 0, 0, 'Oxytetracycline day 3', null],
                ['2026-09-14', 'improving', 13, 0, 3, 'Course finished', 'Three stopped laying and were culled.'],
            ]);
            $this->episode($bay1, 'House 2', '2026-10-04', 9, ['Limping / injury'], 'Leg injury', 'Limping after the feeders were moved; one bird trapped under a feeder.', [
                ['2026-10-05', 'same', 0, 0, 0, 'Rest; feed and water within reach', null],
                ['2026-10-07', 'improving', 4, 0, 0, null, 'Four walking normally, back to House 2.'],
            ]);
            $this->episode($bay2, 'house 6', '2026-10-08', 14, ['Not eating', 'Diarrhoea'], 'Enteritis (suspected)', 'Droppings watery; samples taken for the vet.', [
                ['2026-10-09', 'worse', 0, 1, 0, 'Amprolium + electrolytes in water', 'Waiting for lab results.'],
                ['2026-10-10', 'same', 0, 0, 0, 'Amprolium day 2', null],
            ]);

            // Deaths from the sick bays are examined like any other (all but the last two days).
            foreach (MortalityRecord::where('status', 'pending_assessment')->where('worker_note', 'like', 'Sick bay:%')->whereDate('occurred_on', '<', '2026-10-09')->get() as $death) {
                $admission = SickBayAdmission::where('poultry_house_id', $death->poultry_house_id)->whereDate('admitted_on', '<=', $death->occurred_on)->latest('admitted_on')->first();
                $cause = match ($admission?->condition) { 'Respiratory infection' => 'Respiratory infection', 'Vent pecking at start of lay' => 'Pecking / cannibalism', default => 'Cause not established' };
                $this->put('mortality.assess.update', ['record' => $death->id], ['cause_name' => $cause, 'assessment_note' => 'Died in the sick bay during treatment.', 'action_taken' => 'Sick bay care continued for the rest of the group.'], Carbon::parse($death->occurred_on)->addDay()->format('Y-m-d').' 08:30');
            }

            $this->table(['Sick bay', 'Admissions', 'In', 'Recovered', 'Died', 'Culled', 'In care'], SickBay::withCount('admissions')->get()->map(fn ($b) => [
                $b->name, $b->admissions_count, $b->admissions()->sum('quantity'), $b->admissions()->sum('recovered'), $b->admissions()->sum('died'), $b->admissions()->sum('culled'), $b->admissions()->sum('in_care'),
            ])->all());
        } catch (\Throwable $e) {
            DB::rollBack();
            Carbon::setTestNow();
            $this->error('Stopped: '.$e->getMessage());
            return self::FAILURE;
        }
        Carbon::setTestNow();
        if (! $this->option('commit')) {
            DB::rollBack();
            $this->warn('Dry run finished: everything was rolled back. Run again with --commit to keep the records.');
            return self::SUCCESS;
        }
        DB::commit();
        $this->info('Sick bay demo records saved.');
        return self::SUCCESS;
    }

    private function episode(int $bay, string $house, string $date, int $quantity, array $signs, string $condition, string $notes, array $checks): void
    {
        $houseId = PoultryHouse::where('house_number', $house)->value('id');
        $this->post('sick-bay.admit', [], ['sick_bay_id' => $bay, 'source' => 'house:'.$houseId, 'quantity' => $quantity, 'admitted_on' => $date, 'signs' => $signs, 'condition' => $condition, 'notes' => $notes], $date.' 09:40');
        $admission = SickBayAdmission::latest('id')->firstOrFail();
        foreach ($checks as [$on, $progress, $recovered, $died, $culled, $treatment, $note]) {
            $this->post('sick-bay.check', ['admission' => $admission->id], array_filter(['checked_on' => $on, 'progress' => $progress, 'recovered' => $recovered, 'died' => $died, 'culled' => $culled, 'treatment' => $treatment, 'notes' => $note], fn ($v) => $v !== null), $on.' 16:30');
        }
    }

    private function post(string $route, array $params, array $data, string $at) { return $this->send('post', $route, $params, $data, $at); }
    private function put(string $route, array $params, array $data, string $at) { return $this->send('put', $route, $params, $data, $at); }

    private function send(string $method, string $route, array $params, array $data, string $at)
    {
        Carbon::setTestNow(Carbon::parse($at));
        app('session.store')->forget('errors');
        $this->http->actingAs($this->pm->fresh());
        $response = $this->http->{$method}(route($route, $params, false), $data);
        $errors = app('session.store')->get('errors');
        if ($response->getStatusCode() >= 400 || ($errors && $errors->any())) {
            throw new \RuntimeException("{$route} on {$at}: ".($errors && $errors->any() ? implode(' | ', $errors->all()) : ($response->exception?->getMessage() ?: 'HTTP '.$response->getStatusCode())));
        }
        return $response;
    }
}
