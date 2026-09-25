<?php

namespace App\Http\Controllers;

use App\Models\DailyManagementReportComment;
use App\Models\DailyManagementReportSection;
use App\Models\User;
use App\Services\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyManagementReportController extends Controller
{
    public const DEPARTMENTS = [
        'production' => ['label' => 'Production', 'units' => ['poultry' => 'Poultry', 'goats' => 'Goat Unit', 'rabbits' => 'Rabbit Unit', 'dogs' => 'Dog Unit', 'garden' => 'Gardens', 'feed_store' => 'Feed & Stock']],
        'hr' => ['label' => 'HR & Administration', 'units' => ['attendance' => 'Staff Attendance', 'security' => 'Security', 'welfare' => 'Staff Welfare', 'fleet' => 'Fleet', 'construction' => 'Construction', 'ad_hoc' => 'Ad Hoc Activities']],
        'finance' => ['label' => 'Finance', 'units' => ['cash_banking' => 'Cash & Banking', 'expenses_payables' => 'Expenses & Payables', 'sales_collections' => 'Sales & Collections']],
    ];

    public function index(Request $request): View
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $roles = $this->roles($request->user());
        $this->seedDailyUnitReports($date);
        $rows = DailyManagementReportSection::with('preparer')->whereDate('report_date', $date)->get()->groupBy('department_code')->map->keyBy('section_code');
        $canViewAll = $roles->intersect(['ceo', 'director', 'personal-assistant'])->isNotEmpty();
        $editable = $this->editableUnits($roles);
        $comments = DailyManagementReportComment::with(['author', 'unitReport'])->whereDate('report_date', $date)->oldest()->get();
        $closures = DB::table('daily_department_report_closures')->whereDate('report_date',$date)->get()->keyBy('department_code');
        if (! $canViewAll) {
            $comments = $comments->filter(function (DailyManagementReportComment $comment) use ($editable, $request) {
                if ($comment->unit_report_id) return $comment->unitReport?->prepared_by === $request->user()->id;
                return isset($editable[$comment->department_code]);
            });
        }
        if ($roles->contains('director')) {
            $readyDepartments = collect(self::DEPARTMENTS)
                ->keys()
                ->filter(fn (string $department) => $this->departmentReady($date, $department));

            return view('daily-reports.director', [
                'date' => $date,
                'departments' => self::DEPARTMENTS,
                'rows' => $rows,
                'comments' => $comments->whereIn('department_code', $readyDepartments)->groupBy('department_code'),
                'snapshots' => $this->directorSnapshots($date, $readyDepartments),
                'readyDepartments' => $readyDepartments,
            ]);
        }

        $summaryMode = $roles->contains('finance-manager') ? 'finance' : ($roles->contains('sales-manager') ? 'sales' : ($roles->contains('production-manager') ? 'production' : ($roles->contains('hr-manager') ? 'hr' : ($roles->contains('ceo') && $closures->has('finance') ? 'finance' : null))));
        $dailySummary = match ($summaryMode) {
            'production' => $this->productionSnapshot($date),
            'hr' => $this->hrSnapshot($date),
            'finance', 'sales' => $this->financeSnapshot($date),
            default => null,
        };
        $productionSummary = $roles->contains('ceo') && $closures->has('production') ? $this->productionSnapshot($date) : null;
        $hrSummary = $roles->contains('ceo') && $closures->has('hr') ? $this->hrSnapshot($date) : null;
        return view('daily-reports.index', ['date' => $date, 'departments' => self::DEPARTMENTS, 'rows' => $rows, 'comments' => $comments->groupBy('department_code'), 'editable' => $editable, 'closures'=>$closures, 'closable'=>$this->closableDepartments($roles), 'isCeo' => $roles->contains('ceo'), 'isFinanceManager'=>$roles->contains('finance-manager'), 'isProductionManager'=>$roles->contains('production-manager'), 'isHrManager'=>$roles->contains('hr-manager'), 'isDirector' => false, 'canViewAll' => $canViewAll, 'dailySummary'=>$dailySummary, 'summaryMode'=>$summaryMode, 'productionSummary'=>$productionSummary, 'hrSummary'=>$hrSummary]);
    }

    public function save(Request $request, CommunicationService $communications): RedirectResponse
    {
        $data = $request->validate(['report_date' => ['required', 'date'], 'department_code' => ['required', 'string'], 'unit_code' => ['required', 'string'], 'summary' => ['required', 'string', 'max:10000'], 'activities' => ['nullable', 'string', 'max:15000'], 'challenges' => ['nullable', 'string', 'max:10000'], 'recommendations' => ['nullable', 'string', 'max:10000'], 'details' => ['nullable', 'array'], 'details.*' => ['nullable', 'array'], 'details.*.*' => ['nullable', 'array'], 'details.*.*.*' => ['nullable', 'string', 'max:2000'], 'action' => ['required', 'in:draft,share']]);
        abort_unless($this->mayEdit($request->user(), $data['department_code'], $data['unit_code']), 403);
        $row = DailyManagementReportSection::firstOrNew(['report_date' => $data['report_date'], 'department_code' => $data['department_code'], 'section_code' => $data['unit_code']]);
        abort_if(!$row->exists && DB::table('daily_department_report_closures')->whereDate('report_date',$data['report_date'])->where('department_code',$data['department_code'])->exists(),422,'The department manager already closed reporting for this date. Ask them to reopen it before adding another activity.');
        abort_if($row->exists && $row->status === 'ceo_reviewed', 422, 'A CEO-reviewed unit report is locked.');
        $details = collect($data['details'] ?? [])->map(fn ($rows) => collect($rows)->filter(fn ($item) => collect($item)->filter(fn ($value) => filled($value))->isNotEmpty())->values()->all())->all();
        if(data_get($row->details,'system_source') && empty($details)) $details=$row->details;
        $row->fill(['summary' => $data['summary'], 'activities' => $data['activities'] ?? null, 'challenges' => $data['challenges'] ?? null, 'recommendations' => $data['recommendations'] ?? null, 'details' => $details, 'prepared_by' => $request->user()->id, 'status' => $data['action'] === 'share' ? 'manager_shared' : 'draft', 'shared_at' => $data['action'] === 'share' ? now() : null, 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null])->save();
        if ($data['action'] === 'share') {
            $label = self::DEPARTMENTS[$data['department_code']]['units'][$data['unit_code']];
            if ($data['department_code'] === 'finance' && $data['unit_code'] === 'sales_collections') {
                $communications->toRole('finance-manager', 'Sales & Collections report is ready', 'Combine the confirmed sales figures with the other Finance unit reports, then close and send the Finance Department report to the CEO.', $request->user()->id, route('daily-reports.index', ['date' => $data['report_date']], false));
            } elseif ($data['department_code'] !== 'finance') {
                $communications->toRole('ceo', "$label daily report needs review", 'Review the unit report and add your comment.', $request->user()->id, route('daily-reports.index', ['date' => $data['report_date']], false));
            }
        }
        $sharedMessage = $data['department_code'] === 'finance' && $data['unit_code'] === 'sales_collections'
            ? 'Sales report submitted to Finance for combination.'
            : ($data['department_code'] === 'finance' ? 'Finance section confirmed. Complete all three sections, then send the combined department report to the CEO.' : 'Unit report shared with the CEO.');
        return back()->with('status', $data['action'] === 'share' ? $sharedMessage : 'Draft saved.');
    }

    public function review(Request $request, DailyManagementReportSection $section, CommunicationService $communications): RedirectResponse
    {
        abort_unless($this->roles($request->user())->contains('ceo'), 403);
        abort_unless(in_array($section->status, ['manager_shared', 'changes_requested'], true), 422);
        $data = $request->validate(['decision' => ['required', 'in:review,changes'], 'review_note' => ['required', 'string', 'max:5000']]);
        $section->update(['status' => $data['decision'] === 'review' ? 'ceo_reviewed' : 'changes_requested', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note']]);
        $communications->toUser($section->prepared_by, $data['decision'] === 'review' ? 'CEO reviewed your daily report' : 'Daily report returned for correction', $data['review_note'], 'workflow', $request->user()->id, route('daily-reports.index', ['date' => $section->report_date->toDateString()], false));
        if ($data['decision'] === 'review' && $this->departmentReady($section->report_date->toDateString(), $section->department_code)) {
            $label = self::DEPARTMENTS[$section->department_code]['label'];
            $communications->toRole('director', "$label daily report is ready", 'All unit reports have been reviewed by the CEO. The departmental report is ready.', $request->user()->id, route('daily-reports.index', ['date' => $section->report_date->toDateString()], false));
        }
        return back()->with('status', $data['decision'] === 'review' ? 'Unit reviewed and included in the departmental report.' : 'Report returned with your comment.');
    }

    public function reviewDepartment(Request $request, CommunicationService $communications): RedirectResponse
    {
        abort_unless($this->roles($request->user())->contains('ceo'), 403);
        $data = $request->validate([
            'report_date' => ['required', 'date'],
            'department_code' => ['required', 'string', 'in:production,hr,finance'],
            'decision' => ['required', 'in:approve,changes'],
            'review_note' => ['nullable', 'required_if:decision,changes', 'string', 'max:5000'],
        ]);
        abort_unless(DB::table('daily_department_report_closures')->whereDate('report_date', $data['report_date'])->where('department_code', $data['department_code'])->exists(), 422, 'This department has not submitted its complete daily report yet.');

        $reports = DailyManagementReportSection::whereDate('report_date', $data['report_date'])
            ->where('department_code', $data['department_code'])
            ->whereIn('status', ['manager_shared', 'changes_requested', 'ceo_reviewed'])
            ->get();
        abort_if($reports->isEmpty(), 422, 'There is no submitted report to review.');

        $approved = $data['decision'] === 'approve';
        DailyManagementReportSection::whereIn('id', $reports->pluck('id'))->update([
            'status' => $approved ? 'ceo_reviewed' : 'changes_requested',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
            'updated_at' => now(),
        ]);

        $label = self::DEPARTMENTS[$data['department_code']]['label'];
        $reports->pluck('prepared_by')->filter()->unique()->each(fn ($ownerId) => $communications->toUser(
            $ownerId,
            $approved ? "CEO approved the {$label} daily report" : "{$label} daily report returned for correction",
            $approved ? (($data['review_note'] ?? null) ?: 'The complete daily report was reviewed and approved.') : $data['review_note'],
            'workflow',
            $request->user()->id,
            route('daily-reports.index', ['date' => $data['report_date']], false)
        ));

        if ($approved) {
            $communications->toRole('director', "{$label} daily report approved by CEO", 'The completed departmental template is available for viewing.', $request->user()->id, route('daily-reports.index', ['date' => $data['report_date']], false));
        }

        return back()->with('status', $approved ? "{$label} daily report approved." : "{$label} daily report returned for correction.");
    }

    public function saveHrObservations(Request $request): RedirectResponse
    {
        abort_unless($this->roles($request->user())->contains('hr-manager'), 403);
        $data = $request->validate([
            'report_date' => ['required', 'date'],
            'challenges' => ['nullable', 'string', 'max:10000'],
            'emerging_issues' => ['nullable', 'string', 'max:10000'],
            'recommendations' => ['nullable', 'string', 'max:10000'],
            'achievements' => ['nullable', 'string', 'max:10000'],
        ]);
        abort_if(DB::table('daily_department_report_closures')->whereDate('report_date', $data['report_date'])->where('department_code', 'hr')->exists(), 422, 'The HR report is already closed for this date.');

        $row = DailyManagementReportSection::firstOrNew([
            'report_date' => $data['report_date'],
            'department_code' => 'hr',
            'section_code' => 'ad_hoc',
        ]);
        abort_if($row->exists && $row->status === 'ceo_reviewed', 422, 'This HR report has already been reviewed by the CEO.');
        $details = $row->details ?? [];
        $details['management_observations'] = collect($data)->except('report_date')->all();
        $row->fill([
            'summary' => $row->summary ?: 'HR daily management observations',
            'details' => $details,
            'prepared_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'HR challenges, emerging issues, recommendations and achievements saved.');
    }

    public function closeDay(Request $request, CommunicationService $communications): RedirectResponse
    {
        $data=$request->validate(['report_date'=>['required','date'],'department_code'=>['required','string']]);
        abort_unless(in_array($data['department_code'],$this->closableDepartments($this->roles($request->user())),true),403);
        $reports=DailyManagementReportSection::whereDate('report_date',$data['report_date'])->where('department_code',$data['department_code'])->get();
        abort_if($reports->isEmpty(),422,'Record and share at least one activity before closing the department report.');
        if ($data['department_code'] === 'finance') {
            $sales = $reports->firstWhere('section_code', 'sales_collections');
            abort_if($sales?->status === 'draft', 422, 'The Sales Manager has not submitted the Sales & Collections report to Finance yet.');
        }
        abort_if($reports->contains(fn($row)=>$row->status==='changes_requested'),422,'A report returned by the CEO still needs attention before the department can close the day.');
        DailyManagementReportSection::whereDate('report_date',$data['report_date'])
            ->where('department_code',$data['department_code'])
            ->where('status','draft')
            ->when($data['department_code']==='finance',fn($query)=>$query->where('section_code','!=','sales_collections'))
            ->update(['status'=>'manager_shared','prepared_by'=>$request->user()->id,'shared_at'=>now(),'updated_at'=>now()]);
        DB::table('daily_department_report_closures')->updateOrInsert(['report_date'=>$data['report_date'],'department_code'=>$data['department_code']],['closed_by'=>$request->user()->id,'closed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $label=self::DEPARTMENTS[$data['department_code']]['label'];
        $communications->toRole('ceo',"$label daily activity report is complete",'The department has confirmed that all activities recorded for this date have been shared.',$request->user()->id,route('daily-reports.index',['date'=>$data['report_date']],false));
        if($this->departmentReady($data['report_date'],$data['department_code'])) $communications->toRole('director',"$label daily report is ready",'All activities submitted for the day have already been reviewed by the CEO.',$request->user()->id,route('daily-reports.index',['date'=>$data['report_date']],false));
        return back()->with('status','Today’s activity reporting is closed and ready for CEO review.');
    }

    public function comment(Request $request, CommunicationService $communications): RedirectResponse
    {
        abort_unless($this->roles($request->user())->contains('director'), 403);
        $data = $request->validate(['report_date' => ['required', 'date'], 'department_code' => ['required', 'string'], 'unit_report_id' => ['nullable', 'integer', 'exists:daily_management_report_sections,id'], 'body' => ['required', 'string', 'max:5000']]);
        abort_unless(isset(self::DEPARTMENTS[$data['department_code']]), 422);
        abort_unless($this->departmentReady($data['report_date'], $data['department_code']), 403, 'The CEO has not approved the complete departmental report yet.');
        if (! empty($data['unit_report_id'])) {
            $target = DailyManagementReportSection::findOrFail($data['unit_report_id']);
            abort_unless($target->department_code === $data['department_code'] && $target->report_date->toDateString() === $data['report_date'] && $target->status === 'ceo_reviewed', 422);
        }
        $comment = DailyManagementReportComment::create($data + ['comment_type' => 'director', 'author_id' => $request->user()->id]);
        $recipients = $comment->unit_report_id ? collect([DailyManagementReportSection::find($comment->unit_report_id)?->prepared_by]) : DailyManagementReportSection::whereDate('report_date', $data['report_date'])->where('department_code', $data['department_code'])->pluck('prepared_by');
        $recipients = $recipients->filter()->unique();
        $recipients->each(fn ($id) => $communications->toUser($id, 'Director commented on your daily report', $data['body'], 'workflow', $request->user()->id, route('daily-reports.index', ['date' => $data['report_date']], false)));
        if ($recipients->isEmpty()) {
            $role = ['production' => 'production-manager', 'hr' => 'hr-manager', 'finance' => 'finance-manager'][$data['department_code']];
            $communications->toRole($role, 'Director commented on your departmental report', $data['body'], $request->user()->id, route('daily-reports.index', ['date' => $data['report_date']], false));
        }
        return back()->with('status', 'Comment sent to the concerned report owner.');
    }

    private function departmentReady(string $date, string $department): bool
    {
        if(!DB::table('daily_department_report_closures')->whereDate('report_date',$date)->where('department_code',$department)->exists()) return false;
        $reports=DailyManagementReportSection::whereDate('report_date',$date)->where('department_code',$department)->get();
        return $reports->isNotEmpty() && $reports->every(fn($report)=>$report->status==='ceo_reviewed');
    }

    private function directorSnapshots(string $date, Collection $readyDepartments): array
    {
        $snapshots = [];

        if ($readyDepartments->contains('production')) {
            $snapshots['production'] = $this->productionSnapshot($date);
        }

        if ($readyDepartments->contains('hr')) {
            $snapshots['hr'] = $this->hrSnapshot($date);
        }

        if ($readyDepartments->contains('finance')) {
            $snapshots['finance'] = $this->financeSnapshot($date);
        }

        return $snapshots;
    }

    private function hrSnapshot(string $date): array
    {
        return [
            'records' => DB::table('employee_records')->leftJoin('employees', 'employees.id', '=', 'employee_records.employee_id')->whereDate('recorded_on', $date)->select('employee_records.*', 'employees.first_name', 'employees.last_name', 'employees.staff_no')->orderBy('type')->get(),
            'active_leave' => DB::table('employee_records')->where('type', 'leave')->whereDate('recorded_on', '<=', $date)->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))->orderByDesc('recorded_on')->get()->keyBy('employee_id'),
            'fleet' => DB::table('fleet_records')->whereDate('recorded_on', $date)->orderBy('set_off_at')->get(),
            'active_staff' => DB::table('employees')->where('status', 'active')->count(),
            'employees' => DB::table('employees')->leftJoin('departments', 'departments.id', '=', 'employees.department_id')->leftJoin('roles', 'roles.id', '=', 'employees.role_id')->where('employees.status', 'active')->select('employees.*', 'departments.name as department_name', 'roles.name as role_name')->orderBy('departments.name')->orderBy('employees.first_name')->get(),
        ];
    }

    private function financeSnapshot(string $date): array
    {
        $transactions = DB::table('financial_transactions')->whereDate('transaction_date', $date)->orderBy('created_at')->get();
        $dayRecords = DB::table('sales')->whereDate('sale_date', $date)->where('status', '!=', 'void')->where('approval_status','approved')->get();
        $sales = $dayRecords->where('is_staff_consumption', false)->values();
        $internalUses = $dayRecords->where('is_staff_consumption', true)->values();
        $saleIds = $sales->pluck('id');
        $saleLines = $saleIds->isEmpty() ? collect() : DB::table('sale_lines')->leftJoin('sale_products', 'sale_products.id', '=', 'sale_lines.product_id')->whereIn('sale_id', $saleIds)->select('sale_lines.*', 'sale_products.category', 'sale_products.egg_grade_id')->get();
        $internalUseIds = $internalUses->pluck('id');
        $internalUseLines = $internalUseIds->isEmpty() ? collect() : DB::table('sale_lines')->whereIn('sale_id', $internalUseIds)->get();
        // Collections belong to the day they were actually received, including
        // part-payments against sales raised on an earlier date.
        $payments = DB::table('sales_payments')
            ->leftJoin('sales', 'sales.id', '=', 'sales_payments.sale_id')
            ->whereDate('sales_payments.paid_on', $date)
            ->where('sales.status', '!=', 'void')
            ->select('sales_payments.*', 'sales.invoice_number', 'sales.customer_name')
            ->orderBy('sales_payments.created_at')
            ->get();

        $eggInventory = DB::table('egg_grades')->where('is_active', true)->orderBy('sort_order')->get()->map(function ($grade) use ($date) {
            $received = fn ($operator) => (int) DB::table('egg_collection_lines')->join('egg_collections', 'egg_collections.id', '=', 'egg_collection_lines.egg_collection_id')->where('egg_collection_lines.egg_grade_id', $grade->id)->where('egg_collections.status', 'approved')->whereDate('egg_collections.collected_on', $operator, $date)->sum('egg_collection_lines.total_eggs');
            $sold = fn ($operator) => (float) DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->join('sale_products', 'sale_products.id', '=', 'sale_lines.product_id')->where('sale_products.egg_grade_id', $grade->id)->where('sales.status', '!=', 'void')->whereDate('sales.sale_date', $operator, $date)->selectRaw("coalesce(sum(case when lower(sale_lines.unit) in ('egg','eggs','loose egg','loose eggs') then sale_lines.quantity else sale_lines.quantity * ? end),0) as eggs", [$grade->eggs_per_tray ?: 30])->value('eggs');
            $opening = max(0, $received('<') - $sold('<'));
            $receivedToday = $received('='); $soldToday = $sold('=');
            return (object) ['name'=>$grade->name, 'opening'=>$opening, 'received'=>$receivedToday, 'sold'=>$soldToday, 'closing'=>max(0, $opening+$receivedToday-$soldToday), 'eggs_per_tray'=>(int)($grade->eggs_per_tray ?: 30)];
        });

        $allTransactions = DB::table('financial_transactions')->whereDate('transaction_date', '<=', $date)->orderBy('transaction_date')->orderBy('id')->get();
        $accountBalances = $allTransactions->groupBy('account')->map(fn ($rows, $account) => (object) [
            'account'=>$account,
            'balance'=>$rows->sum(fn ($row) => $row->type === 'money_received' ? (float)$row->amount : ($row->type === 'money_paid' ? -(float)$row->amount : 0)),
        ])->values();

        return [
            'transactions'=>$transactions, 'all_transactions'=>$allTransactions, 'sales'=>$sales, 'sale_lines'=>$saleLines, 'payments'=>$payments, 'internal_uses'=>$internalUses, 'internal_use_lines'=>$internalUseLines,
            'egg_inventory'=>$eggInventory, 'account_balances'=>$accountBalances,
            'prepayments_received'=>DB::table('customer_prepayments')->leftJoin('sales_customers', 'sales_customers.id', '=', 'customer_prepayments.customer_id')->whereDate('received_date', $date)->select('customer_prepayments.*', DB::raw('coalesce(sales_customers.name, customer_prepayments.customer_name) as account_name'))->orderBy('account_name')->get(),
            'prepayments'=>DB::table('customer_prepayments')->leftJoin('sales_customers', 'sales_customers.id', '=', 'customer_prepayments.customer_id')->whereDate('received_date', '<=', $date)->whereRaw('amount > amount_used')->select('customer_prepayments.*', DB::raw('coalesce(sales_customers.name, customer_prepayments.customer_name) as account_name'))->orderBy('account_name')->get(),
            'payables'=>DB::table('payables')->whereDate('invoice_date', '<=', $date)->whereRaw('amount > amount_paid')->orderBy('supplier_name')->get(),
        ];
    }

    /** Build the production sheet data for one day or a reporting period. */
    public function productionSnapshot(string $date, ?string $toDate = null): array
    {
        $toDate ??= $date;
        $daysInPeriod = max(1, \Carbon\Carbon::parse($date)->diffInDays(\Carbon\Carbon::parse($toDate)) + 1);
        $collections = DB::table('egg_collections')
            ->leftJoin('users', 'users.id', '=', 'egg_collections.recorded_by')
            ->whereBetween('egg_collections.collected_on', [$date, $toDate])
            ->where('egg_collections.status', 'approved')
            ->select('egg_collections.*', 'users.name as recorder_name')->get();
        $collectionIds = $collections->pluck('id');
        $lines = $collectionIds->isEmpty() ? collect() : DB::table('egg_collection_lines')
            ->join('egg_grades', 'egg_grades.id', '=', 'egg_collection_lines.egg_grade_id')
            ->whereIn('egg_collection_id', $collectionIds)
            ->select('egg_collection_lines.*', 'egg_grades.is_defect')->get()->groupBy('egg_collection_id');
        $mortality = DB::table('mortality_records')
            ->leftJoin('mortality_causes', 'mortality_causes.id', '=', 'mortality_records.mortality_cause_id')
            ->whereBetween('mortality_records.occurred_on', [$date, $toDate])
            ->select('mortality_records.*', 'mortality_causes.name as cause_name')->get();

        $attendants = DB::table('poultry_house_assignments')
            ->join('employees', 'employees.id', '=', 'poultry_house_assignments.employee_id')
            ->whereDate('assigned_from', '<=', $toDate)
            ->where(fn ($query) => $query->whereNull('assigned_until')->orWhereDate('assigned_until', '>=', $toDate))
            ->select('poultry_house_id', 'employees.first_name', 'employees.last_name')->get()
            ->groupBy('poultry_house_id')->map(fn ($rows) => $rows->map(fn ($row) => trim($row->first_name.' '.$row->last_name))->join(', '));

        $houses = DB::table('poultry_houses')
            ->join('animal_groups', 'animal_groups.id', '=', 'poultry_houses.animal_group_id')
            ->leftJoin('breeds', 'breeds.id', '=', 'animal_groups.breed_id')
            ->where('poultry_houses.is_active', true)->where('poultry_houses.bird_quantity', '>', 0)
            ->select('poultry_houses.*', 'animal_groups.code as batch_code', 'animal_groups.expected_lay_date', 'animal_groups.actual_lay_date', 'breeds.peak_lay_percent')
            ->orderBy('animal_groups.code')->orderBy('poultry_houses.house_number')->get();

        $poultryRows = $houses->map(function ($house) use ($collections, $lines, $mortality, $attendants, $toDate, $daysInPeriod) {
            $entries = $collections->where('poultry_house_id', $house->id);
            $normalEggs = 0; $normalTrays = 0; $normalLoose = 0; $damaged = 0;
            foreach ($entries as $entry) {
                foreach ($lines->get($entry->id, collect()) as $line) {
                    if ($line->is_defect) $damaged += (int) $line->total_eggs;
                    else { $normalEggs += (int) $line->total_eggs; $normalTrays += (int) $line->trays; $normalLoose += (int) $line->loose_eggs; }
                }
            }
            $deaths = (int) $mortality->where('poultry_house_id', $house->id)->sum(fn ($row) => $row->quantity_died + $row->quantity_culled);
            $birds = (int) $house->bird_quantity;
            $total = (int) $entries->sum('total_eggs');
            $laying = ($house->actual_lay_date && $house->actual_lay_date <= $toDate)
                || ($house->expected_lay_date && $house->expected_lay_date <= $toDate);
            $expectedRate = $laying ? (float) ($house->peak_lay_percent ?? 0) : 0;
            $expected = (int) round($birds * $expectedRate / 100 * $daysInPeriod);
            return (object) [
                'batch_code' => $house->batch_code, 'attendant' => $attendants->get($house->id, 'Unassigned'),
                'house' => trim((string) preg_replace('/\s*[-–—]\s*B-\d+\/\d+\s*$/i', '', $house->house_number)),
                'birds' => $birds, 'deaths' => $deaths, 'trays' => $normalTrays,
                'extra_eggs' => $normalLoose, 'damage' => $damaged, 'total_eggs' => $total,
                'tray_equivalent' => $total / 30, 'performance' => $birds > 0 ? $total / $birds * 100 : 0,
                'expected_performance' => $expectedRate, 'expected_eggs' => $expected,
                'deficit' => max(0, $expected - $total),
                'recorded_by' => $entries->pluck('recorder_name')->filter()->unique()->join(', '),
            ];
        });

        $livestock = collect(['goats', 'rabbits', 'dogs', 'sheep'])->mapWithKeys(function ($unit) use ($date, $toDate) {
            $groups = DB::table('livestock_groups')->where('unit_code', $unit)->where('status', 'active')->orderBy('group_code')->get();
            $ids = $groups->pluck('id');
            return [$unit => [
                'groups' => $groups,
                'feed' => $ids->isEmpty() ? collect() : DB::table('livestock_feed_records')->whereIn('livestock_group_id', $ids)->whereBetween('recorded_on', [$date, $toDate])->get(),
                'mortality' => $ids->isEmpty() ? collect() : DB::table('livestock_mortality_records')->whereIn('livestock_group_id', $ids)->whereBetween('occurred_on', [$date, $toDate])->get(),
                'production' => $ids->isEmpty() ? collect() : DB::table('livestock_production_records')->whereIn('livestock_group_id', $ids)->whereBetween('recorded_on', [$date, $toDate])->get(),
            ]];
        })->all();

        return [
            'poultry_rows' => $poultryRows, 'poultry_batches' => $poultryRows->groupBy('batch_code'),
            'mortality' => $mortality, 'feed' => DB::table('feed_consumptions')->whereBetween('used_on', [$date, $toDate])->where('status', 'approved')->get(),
            'units' => DB::table('farm_unit_records')->whereBetween('record_date', [$date, $toDate])->orderBy('unit_code')->orderBy('id')->get(),
            'livestock' => $livestock, 'sales' => DB::table('sales')->whereBetween('sale_date', [$date, $toDate])->where('status', '!=', 'void')->get(),
        ];
    }

    private function editableUnits(Collection $roles): array
    {
        $result = [];
        if ($roles->contains('production-manager')) $result['production'] = array_keys(self::DEPARTMENTS['production']['units']);
        if ($roles->contains('hr-manager')) $result['hr'] = array_keys(self::DEPARTMENTS['hr']['units']);
        if ($roles->contains('finance-manager')) $result['finance'] = ['cash_banking', 'expenses_payables'];
        if ($roles->contains('sales-manager')) $result['finance'][] = 'sales_collections';
        return $result;
    }

    private function closableDepartments(Collection $roles):array
    {
        return collect(['production-manager'=>'production','hr-manager'=>'hr','finance-manager'=>'finance'])->filter(fn($department,$role)=>$roles->contains($role))->values()->all();
    }

    /** Generate every unit's daily sheet before its owner opens the reporting page. */
    private function seedDailyUnitReports(string $date): void
    {
        $owners = [
            'production' => $this->userForRole('production-manager'),
            'hr' => $this->userForRole('hr-manager'),
            'finance' => $this->userForRole('finance-manager'),
            'sales' => $this->userForRole('sales-manager'),
        ];

        foreach (self::DEPARTMENTS as $departmentCode => $department) {
            if (DB::table('daily_department_report_closures')->whereDate('report_date', $date)->where('department_code', $departmentCode)->exists()) continue;
            foreach ($department['units'] as $unitCode => $unitLabel) {
                [$summary, $activities] = $this->automaticUnitContent($date, $departmentCode, $unitCode, $unitLabel);
                $row = DailyManagementReportSection::whereDate('report_date', $date)->where('department_code', $departmentCode)->where('section_code', $unitCode)->first();
                if ($row && data_get($row->details, 'system_source') !== 'auto_daily') continue;
                $owner = $unitCode === 'sales_collections' ? ($owners['sales'] ?: $owners['finance']) : $owners[$departmentCode];
                $values = ['summary'=>$summary, 'activities'=>$activities, 'details'=>['system_source'=>'auto_daily', 'generated_for'=>$date], 'prepared_by'=>$owner?->id];
                if ($row) {
                    if (in_array($row->status, ['draft', 'changes_requested'], true)) $row->update($values);
                } else {
                    DailyManagementReportSection::create($values + ['report_date'=>$date, 'department_code'=>$departmentCode, 'section_code'=>$unitCode, 'status'=>'draft']);
                }
            }
        }
    }

    private function userForRole(string $roleCode): ?User
    {
        return User::query()->where('is_active', true)->where(function ($query) use ($roleCode) {
            $query->whereHas('role', fn ($role) => $role->where('code', $roleCode))
                ->orWhereHas('roles', fn ($role) => $role->where('code', $roleCode));
        })->orderBy('id')->first();
    }

    private function automaticUnitContent(string $date, string $department, string $unit, string $label): array
    {
        $rows = collect(); $activities = '';
        if ($department === 'production' && in_array($unit, ['goats','rabbits','dogs','garden'], true)) {
            $rows = DB::table('farm_unit_records')->whereDate('record_date', $date)->where('unit_code', $unit)->orderBy('created_at')->get();
            $activities = $rows->map(fn ($row) => $row->subject.($row->quantity !== null ? ' - '.number_format((float)$row->quantity, 2).' '.($row->measure_unit ?: 'units') : '').($row->notes ? ' - '.$row->notes : ''))->join("\n");
        } elseif ($department === 'production' && $unit === 'poultry') {
            $eggs = DB::table('egg_collections')->whereDate('collected_on', $date)->get();
            $deaths = DB::table('mortality_records')->whereDate('occurred_on', $date)->get();
            $activities = $eggs->count().' egg collection record(s); '.$deaths->sum(fn ($row) => (int)$row->quantity_died + (int)$row->quantity_culled).' bird loss(es) recorded.';
            $rows = $eggs->concat($deaths);
        } elseif ($department === 'production' && $unit === 'feed_store') {
            $rows = DB::table('feed_consumptions')->whereDate('used_on', $date)->orderBy('created_at')->get();
            $activities = $rows->map(fn ($row) => number_format((float)$row->quantity_kg, 2).' kg '.str_replace('_', ' ', $row->feed_type).' recorded'.($row->worker_note ? ' - '.$row->worker_note : ''))->join("\n");
        } elseif ($department === 'hr' && $unit === 'fleet') {
            $rows = DB::table('fleet_records')->whereDate('recorded_on', $date)->orderBy('set_off_at')->get();
            $activities = $rows->map(fn ($row) => $row->vehicle_id.' - '.$row->purpose)->join("\n");
        } elseif ($department === 'hr') {
            $types = ['attendance'=>'attendance','security'=>'security','welfare'=>'welfare','construction'=>'construction','ad_hoc'=>'ad_hoc'];
            $rows = DB::table('employee_records')->whereDate('recorded_on', $date)->where('type', $types[$unit])->orderBy('created_at')->get();
            $activities = $rows->map(fn ($row) => ($row->details ?: ucfirst(str_replace('_', ' ', $row->type))).' - '.ucfirst($row->status))->join("\n");
        } elseif ($unit === 'sales_collections') {
            $sales = DB::table('sales')->whereDate('sale_date', $date)->where('status', '!=', 'void')->orderBy('created_at')->get();
            $lines = $sales->isEmpty() ? collect() : DB::table('sale_lines')->whereIn('sale_id', $sales->pluck('id'))->orderBy('sale_id')->orderBy('id')->get()->groupBy('sale_id');
            $activities = $sales->map(function ($sale) use ($lines) { $products=$lines->get($sale->id,collect())->map(fn($line)=>rtrim(rtrim(number_format((float)$line->quantity,2),'0'),'.').' '.$line->unit.' '.$line->description)->join(', '); return $sale->invoice_number.' - '.$sale->customer_name.' - '.($products ?: 'Sale').' - UGX '.number_format((float)$sale->total); })->join("\n");
            $rows = $sales;
        } else {
            $query = DB::table('financial_transactions')->whereDate('transaction_date', $date);
            $query = $unit === 'expenses_payables' ? $query->where('type', 'money_paid') : $query->where('type', '!=', 'money_paid');
            $rows = $query->orderBy('created_at')->get();
            $activities = $rows->map(fn ($row) => ($row->reference ? $row->reference.' - ' : '').$row->description.' - UGX '.number_format((float)$row->amount))->join("\n");
        }
        $summary = $rows->isEmpty() ? "No {$label} activity has been recorded for {$date}." : $rows->count()." {$label} record(s) captured for {$date}.";
        return [$summary, $activities ?: 'No entries recorded in this unit register for the day.'];
    }

    private function mayEdit(User $user, string $department, string $unit): bool { return in_array($unit, $this->editableUnits($this->roles($user))[$department] ?? [], true); }
    private function roles(User $user): Collection { return $user->roles->pluck('code')->push($user->role?->code)->filter()->unique(); }
}
