<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Poultry\HouseReportController;
use App\Models\AuditLog;
use App\Models\PoultryHouse;
use App\Models\User;
use App\Services\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Farm registers added after the Brooks Farm Hub survey (25 Oct 2026): house preparation
 * before a new flock, incidents and near misses, and faults and maintenance jobs.
 * Entered by whoever did the work or saw it; closed by a manager with a note.
 */
class FarmRegisterController extends Controller
{
    public const REGISTERS = [
        'house_preparation' => [
            'label' => 'House preparation',
            'about' => 'Checks before a new flock goes in. The house is ready only when every check is done.',
            'checks' => [
                'cleared' => 'Manure, litter, feathers and feed left-overs removed',
                'disinfected' => 'Washed and disinfected (approved product, as on the label)',
                'dried' => 'House left to dry',
                'structure' => 'Roof, doors, mesh, drains and ventilation checked',
                'equipment' => 'Drinkers, feeders, heating, lights and backup power checked',
                'water' => 'Water available and clean',
                'litter' => 'Clean, dry litter in place',
                'brooding' => 'Brooding area set; draughts and predators kept out',
                'supplies' => 'Starter feed and emergency supplies available',
            ],
        ],
        'incident' => [
            'label' => 'Incidents & near misses',
            'about' => 'Injuries, near misses and anything unsafe, with the action taken.',
        ],
        'fault' => [
            'label' => 'Faults & maintenance',
            'about' => 'Something broken or not working. Critical faults go straight to the Production Manager.',
            'categories' => ['water' => 'Water or drinkers', 'electricity' => 'Electricity or lights', 'building' => 'Building: roof, walls, doors, mesh',
                'equipment' => 'Equipment or machines', 'vehicle' => 'Vehicle', 'other' => 'Something else'],
        ],
    ];

    public const SEVERITY = ['minor' => 'Minor', 'serious' => 'Serious', 'critical' => 'Critical'];

    public static function mayView(User $user): bool
    {
        return ! $user->isDataEntryOnly();
    }

    public static function mayClose(User $user): bool
    {
        return $user->isSuperuser() || $user->roles->pluck('code')->push($user->role?->code)
            ->intersect(['production-manager', 'ceo', 'director', 'system-admin'])->isNotEmpty();
    }

    public function index(Request $request): View
    {
        abort_unless(self::mayView($request->user()), 403);
        $register = array_key_exists($request->query('register'), self::REGISTERS) ? $request->query('register') : 'house_preparation';
        $entries = DB::table('farm_register_entries as e')->leftJoin('users as u', 'u.id', '=', 'e.recorded_by')
            ->leftJoin('users as c', 'c.id', '=', 'e.closed_by')->leftJoin('poultry_houses as h', 'h.id', '=', 'e.poultry_house_id')
            ->where('e.register', $register)->orderByRaw("case when e.status in ('open','not_ready') then 0 else 1 end")
            ->orderByDesc('e.entry_date')->orderByDesc('e.id')->limit(100)
            ->get(['e.*', 'u.name as recorder', 'c.name as closer', 'h.house_number']);
        $workerProblems = $register === 'fault'
            ? DB::table('poultry_house_reports as r')->leftJoin('users as u', 'u.id', '=', 'r.recorded_by')->leftJoin('poultry_houses as h', 'h.id', '=', 'r.poultry_house_id')
                ->where('r.report_type', 'problem')->orderByDesc('r.reported_on')->orderByDesc('r.id')->limit(50)
                ->get(['r.*', 'u.name as recorder', 'h.house_number'])
            : collect();

        return view('operations.farm-registers.index', [
            'register' => $register,
            'registers' => self::REGISTERS,
            'entries' => $entries,
            'workerProblems' => $workerProblems,
            'problemLabels' => HouseReportController::PROBLEMS,
            'severities' => self::SEVERITY,
            'houses' => PoultryHouse::where('is_active', true)->orderBy('house_number')->get(['id', 'house_number']),
            'mayClose' => self::mayClose($request->user()),
            'openCounts' => DB::table('farm_register_entries')->whereIn('status', ['open', 'not_ready'])->groupBy('register')
                ->selectRaw('register, count(*) as n')->pluck('n', 'register'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(self::mayView($user), 403);
        $register = $request->input('register');
        abort_unless(isset(self::REGISTERS[$register]), 404);
        $checks = array_keys(self::REGISTERS['house_preparation']['checks']);

        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'poultry_house_id' => [$register === 'house_preparation' ? 'required' : 'nullable', 'exists:poultry_houses,id'],
            'location' => [$register === 'house_preparation' ? 'nullable' : 'required', 'string', 'max:160'],
            'answers' => [$register === 'house_preparation' ? 'required' : 'nullable', 'array'],
            'answers.*' => ['nullable', Rule::in(['yes', 'no'])],
            'category' => [$register === 'fault' ? 'required' : 'nullable', Rule::in(array_keys(self::REGISTERS['fault']['categories']))],
            'severity' => [$register === 'house_preparation' ? 'nullable' : 'required', Rule::in(array_keys(self::SEVERITY))],
            'details' => [$register === 'house_preparation' ? 'nullable' : 'required', 'string', 'max:2000'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            'products_used' => ['nullable', 'string', 'max:500'],
            'done_by' => ['nullable', 'string', 'max:160'],
        ], ['location.required' => 'Say where it happened.', 'details.required' => 'Say what happened.', 'severity.required' => 'Choose how serious it is.',
            'category.required' => 'Choose what kind of fault it is.']);

        $answers = null;
        $status = 'open';
        if ($register === 'house_preparation') {
            $answers = collect($checks)->mapWithKeys(fn ($key) => [$key => ($data['answers'][$key] ?? 'no')])->all();
            $answers['products_used'] = $data['products_used'] ?? null;
            $answers['done_by'] = $data['done_by'] ?? null;
            $status = collect($checks)->every(fn ($key) => $answers[$key] === 'yes') ? 'ready' : 'not_ready';
        } elseif ($register === 'fault') {
            $answers = ['category' => $data['category']];
        }

        $id = DB::table('farm_register_entries')->insertGetId([
            'register' => $register, 'entry_date' => $data['entry_date'], 'poultry_house_id' => $data['poultry_house_id'] ?? null,
            'location' => $data['location'] ?? null, 'answers' => $answers ? json_encode($answers) : null, 'severity' => $data['severity'] ?? null,
            'details' => $data['details'] ?? null, 'action_taken' => $data['action_taken'] ?? null, 'status' => $status,
            'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLog::record('farm_register_'.$register, null, self::REGISTERS[$register]['label'].' entry recorded.', null, ['id' => $id], 'production');

        if (($data['severity'] ?? null) === 'critical' || $status === 'not_ready') {
            app(CommunicationService::class)->toRole('production-manager',
                $status === 'not_ready' ? 'House not ready for a new flock' : 'Critical '.strtolower(self::REGISTERS[$register]['label']).' reported',
                $status === 'not_ready' ? 'A house preparation check was recorded with checks not done.' : $user->displayName().': '.$data['details'],
                $user->id, route('farm-registers.index', ['register' => $register], false));
        }

        return redirect()->route('farm-registers.index', ['register' => $register])->with('status', match ($status) {
            'ready' => 'House preparation recorded: the house is ready.',
            'not_ready' => 'House preparation recorded: the house is NOT ready until every check is done.',
            default => self::REGISTERS[$register]['label'].' recorded.',
        });
    }

    public function close(Request $request, int $entry): RedirectResponse
    {
        abort_unless(self::mayClose($request->user()), 403, 'Only a manager closes register entries.');
        $row = DB::table('farm_register_entries')->where('id', $entry)->first();
        abort_unless($row && $row->status === 'open', 404);
        $data = $request->validate(['closing_note' => ['required', 'string', 'max:1000']], ['closing_note.required' => 'Say what was done.']);
        DB::table('farm_register_entries')->where('id', $entry)->update(['status' => 'resolved', 'closed_by' => $request->user()->id,
            'closed_at' => now(), 'closing_note' => $data['closing_note'], 'updated_at' => now()]);
        AuditLog::record('farm_register_closed', null, 'Register entry #'.$entry.' closed.', ['status' => 'open'], ['status' => 'resolved'], 'production');

        return back()->with('status', 'Closed.');
    }
}
