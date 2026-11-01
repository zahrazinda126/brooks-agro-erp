<?php

namespace App\Http\Controllers\Poultry;

use App\Models\AnimalGroup;
use App\Models\AuditLog;
use App\Services\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Worker reports for their house, added after the Brooks Farm Hub survey (25 Oct 2026):
 * water given, sick birds seen, and problems. Each goes to the supervisor's Daily Collection
 * Approvals like eggs, deaths and feed; sick birds and problems also tell the Production Manager.
 * Same house rules as the other worker screens (DailyEntryController).
 */
class HouseReportController extends DailyEntryController
{
    public const TYPES = [
        'water' => ['English' => 'Water', 'Luganda' => 'Amazzi', 'route' => 'daily.water'],
        'sick_birds' => ['English' => 'Sick birds', 'Luganda' => 'Enkoko endwadde', 'route' => 'daily.sick'],
        'problem' => ['English' => 'Problem', 'Luganda' => 'Ekizibu', 'route' => 'daily.problem'],
    ];

    public const PROBLEMS = [
        'drinkers' => 'Drinkers or water',
        'feeders' => 'Feeders or feed',
        'building' => 'House: roof, walls, doors, mesh',
        'electricity' => 'Electricity or lights',
        'predators' => 'Predators, rats or pests',
        'other' => 'Something else',
    ];

    public static function mayReport($user): bool
    {
        return collect(['egg-entry', 'mortality-entry', 'feed-consumption'])->contains(fn ($key) => $user->canAccess($key));
    }

    public function form(Request $request, AnimalGroup $batch): View
    {
        $type = $request->route('type');
        abort_unless(isset(self::TYPES[$type]) && self::mayReport($request->user()), 403);
        $this->assertVisible($request, $batch);
        $houses = $this->feedHousesFor($request, $batch);
        abort_if($houses->isEmpty(), 422, 'No poultry house is assigned to this batch.');

        return view('poultry.daily.report', [
            'type' => $type,
            'meta' => self::TYPES[$type],
            'problems' => self::PROBLEMS,
            'batch' => $batch,
            'houseChoices' => $this->houseChoicesFor($request, $batch),
            'selectedHouse' => $houses->firstWhere('id', (int) $request->query('house')) ?? $houses->first(),
        ]);
    }

    public function store(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $type = $request->route('type');
        abort_unless(isset(self::TYPES[$type]) && self::mayReport($request->user()), 403);
        $this->assertVisible($request, $batch);

        $data = $request->validate([
            'poultry_house_id' => ['required', 'exists:poultry_houses,id'],
            'quantity' => [$type === 'problem' ? 'nullable' : 'required', 'numeric', $type === 'water' ? 'min:0' : 'min:1', 'max:1000000'],
            'category' => [$type === 'problem' ? 'required' : 'nullable', Rule::in(array_keys(self::PROBLEMS))],
            'details' => [$type === 'water' ? 'nullable' : 'required', 'string', 'max:1000'],
        ], [
            'quantity.required' => $type === 'water' ? 'Enter how many litres of water you gave.' : 'Enter how many birds look sick.',
            'category.required' => 'Choose what the problem is about.',
            'details.required' => $type === 'problem' ? 'Say what is wrong.' : 'Say what you saw.',
        ]);
        $house = $this->feedHousesFor($request, $batch)->firstWhere('id', (int) $data['poultry_house_id']);
        abort_unless($house, 403, 'This poultry house is not assigned to you or does not contain this batch.');
        if ($type === 'sick_birds' && (float) $data['quantity'] > $house->bird_quantity) {
            return back()->withInput()->withErrors(['quantity' => 'There are only '.number_format($house->bird_quantity).' birds in '.$house->house_number.'.']);
        }

        $id = DB::table('poultry_house_reports')->insertGetId([
            'animal_group_id' => $batch->id, 'poultry_house_id' => $house->id, 'unit_code' => 'poultry',
            'report_type' => $type, 'reported_on' => today(), 'quantity' => $data['quantity'] ?? null,
            'category' => $data['category'] ?? null, 'details' => $data['details'] ?? null,
            'recorded_by' => $request->user()->id, 'verification_status' => 'submitted',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLog::record('poultry_house_report', null, self::TYPES[$type]['English'].' reported for '.$house->house_number.'.', null, ['id' => $id] + $data, 'poultry');

        if ($type !== 'water') {
            $what = $type === 'sick_birds' ? (int) $data['quantity'].' sick bird(s)' : 'a problem ('.self::PROBLEMS[$data['category']].')';
            app(CommunicationService::class)->toRole('production-manager',
                ucfirst($what).' in '.$house->house_number,
                $request->user()->displayName().' reported '.$what.' in '.$house->house_number.': '.$data['details'],
                $request->user()->id, route('supervision.index', absolute: false));
        }

        return redirect()->route('daily.index', ['house' => $house->id])->with('done', [
            'what' => $type === 'water' ? number_format((float) $data['quantity']).' litres of water' : ($type === 'sick_birds' ? (int) $data['quantity'].' sick '.((int) $data['quantity'] === 1 ? 'bird' : 'birds') : 'Problem reported'),
            'where' => $house->house_number,
            'note' => $type === 'water' ? null : 'The manager has been told.',
        ]);
    }
}
