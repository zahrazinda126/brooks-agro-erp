<?php

namespace App\Services;

use App\Models\{ApprovedFundFollowUp, ExecutiveDirective, ExecutiveMeeting, Visitor};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The facts of the PA's daily report, taken from what was recorded that day:
 * visitors, directives, the Director's meetings, approved money and construction.
 * Plain arrays, so a submitted report can keep them exactly as they were.
 */
class PaDailyReport
{
    private const CLOSED = ['done', 'completed', 'cancelled'];

    public function facts(Carbon $date, array $includeConstructionProjectIds = []): array
    {
        $day = $date->toDateString();

        $visitors = Visitor::whereDate('visit_date', $day)->orderBy('checked_in_at')->get()->map(fn ($v) => [
            'name' => $v->visitor_name, 'organisation' => $v->organization, 'visiting' => $v->person_to_visit, 'purpose' => $v->purpose,
            'in' => $v->checked_in_at?->format('H:i'), 'out' => $v->checked_out_at?->format('H:i'),
        ])->all();

        $directive = fn ($d) => ['title' => $d->title, 'team' => $d->responsible_team, 'status' => str_replace('_', ' ', (string) $d->status),
            'due' => $d->due_on?->format('d M'), 'feedback' => $d->latest_feedback];
        $directives = [
            'issued' => ExecutiveDirective::whereDate('issued_on', $day)->get()->map($directive)->all(),
            'feedback' => ExecutiveDirective::whereDate('feedback_at', $day)->whereNotIn('status', self::CLOSED)->get()->map($directive)->all(),
            'completed' => ExecutiveDirective::whereDate('completed_at', $day)->get()->map($directive)->all(),
            'overdue' => ExecutiveDirective::whereDate('due_on', '<', $day)->whereNotIn('status', self::CLOSED)->orderBy('due_on')->get()->map($directive)->all(),
        ];

        $meeting = fn ($m) => ['title' => $m->title, 'when' => $m->starts_at->format('d M, H:i'), 'venue' => $m->venue, 'status' => $m->status, 'outcome' => $m->outcome];
        $meetings = [
            'today' => ExecutiveMeeting::whereDate('starts_at', $day)->orderBy('starts_at')->get()->map($meeting)->all(),
            'coming' => ExecutiveMeeting::where('status', 'scheduled')->whereDate('starts_at', '>', $day)
                ->whereDate('starts_at', '<=', $date->copy()->addDays(7)->toDateString())->orderBy('starts_at')->get()->map($meeting)->all(),
        ];

        $funds = ApprovedFundFollowUp::get();
        $money = [
            'approved' => (float) $funds->sum('approved_amount'),
            'accounted' => (float) $funds->sum('amount_accounted_for'),
            'awaiting' => $funds->where('status', '!=', 'verified')->sortBy('follow_up_due_on')->take(10)->map(fn ($f) => [
                'purpose' => $f->intended_purpose, 'approved' => (float) $f->approved_amount, 'accounted' => (float) $f->amount_accounted_for,
                'status' => str_replace('_', ' ', (string) $f->status),
            ])->values()->all(),
        ];

        $construction = DB::table('construction_projects')->where(function ($query) use ($includeConstructionProjectIds) {
            $query->where('status', 'active');
            if ($includeConstructionProjectIds) $query->orWhereIn('id', $includeConstructionProjectIds);
        })->whereDate('starts_on', '<=', $day)->where(fn ($query) => $query->whereNull('expected_end_on')->orWhereDate('expected_end_on', '>=', $day))->orderBy('name')->get()->map(fn ($p) => [
            'project_id' => $p->id,
            'name' => $p->name,
            'timeline' => $p->starts_on ? Carbon::parse($p->starts_on)->format('d M Y').($p->expected_end_on ? ' – '.Carbon::parse($p->expected_end_on)->format('d M Y') : ' – Ongoing') : '—',
            'builders' => DB::table('construction_project_workers')->where('construction_project_id', $p->id)
                ->where(fn ($q) => $q->whereNull('from_on')->orWhereDate('from_on', '<=', $day))
                ->where(fn ($q) => $q->whereNull('to_on')->orWhereDate('to_on', '>=', $day))->count(),
            'contact' => DB::table('construction_project_workers')->join('employees', 'employees.id', '=', 'construction_project_workers.employee_id')
                ->where('construction_project_workers.construction_project_id', $p->id)->where('construction_project_workers.role', 'lead')
                ->where(fn ($q) => $q->whereNull('from_on')->orWhereDate('from_on', '<=', $day))
                ->where(fn ($q) => $q->whereNull('to_on')->orWhereDate('to_on', '>=', $day))
                ->orderBy('construction_project_workers.id')->value(DB::raw("concat(employees.first_name, ' ', employees.last_name)")),
            'materials' => (float) DB::table('inventory_movements')->where('construction_project_id', $p->id)
                ->whereIn('movement_type', ['issued', 'farm_use'])->whereDate('movement_date', $day)->sum('total_cost'),
            'paid' => (float) DB::table('financial_transactions')->where('construction_project_id', $p->id)->whereNull('reversed_at')
                ->whereDate('transaction_date', $day)->sum('amount'),
            'owed' => (float) DB::table('payables')->where('construction_project_id', $p->id)->selectRaw('coalesce(sum(amount - amount_paid), 0) as owed')->value('owed'),
        ])->all();

        // As in the PA's Word report: every open directive (and any finished in the last week), grouped by the officer responsible.
        $byOfficer = ExecutiveDirective::where(fn ($q) => $q->whereNotIn('status', self::CLOSED)->orWhereDate('completed_at', '>=', $date->copy()->subDays(7)->toDateString()))
            ->whereDate('issued_on', '<=', $day)->orderBy('issued_on')->orderBy('id')->get()
            ->groupBy(fn ($d) => trim((string) $d->responsible_team) ?: 'Not assigned')
            ->map(fn ($rows) => $rows->map(fn ($d) => [
                'date' => $d->issued_on?->format('d M'), 'directive' => trim($d->directive ?: $d->title),
                'status' => $d->latest_feedback ?: ucfirst(str_replace('_', ' ', (string) $d->status)),
            ])->values()->all())
            // Same order as the PA's Word report: CEO, HR, Manager Production, Manager Finance, then anyone else.
            ->sortBy(fn ($rows, $team) => collect(['/ceo|chief exec/i', '/hr|human/i', '/production/i', '/finance/i'])->search(fn ($re) => preg_match($re, $team)) === false ? 9 : collect(['/ceo|chief exec/i', '/hr|human/i', '/production/i', '/finance/i'])->search(fn ($re) => preg_match($re, $team)))
            ->all();

        // Poultry production per batch and house: trays, extra (loose) eggs and damaged eggs.
        $poultry = [];
        $collections = DB::table('egg_collections')->join('animal_groups', 'animal_groups.id', '=', 'egg_collections.animal_group_id')
            ->leftJoin('poultry_houses', 'poultry_houses.id', '=', 'egg_collections.poultry_house_id')
            ->whereDate('egg_collections.collected_on', $day)->where('egg_collections.status', '!=', 'rejected')
            ->get(['egg_collections.id', 'animal_groups.code as batch', 'poultry_houses.house_number as house']);
        $lines = DB::table('egg_collection_lines')->join('egg_grades', 'egg_grades.id', '=', 'egg_collection_lines.egg_grade_id')
            ->whereIn('egg_collection_lines.egg_collection_id', $collections->pluck('id'))->get(['egg_collection_lines.egg_collection_id', 'egg_collection_lines.total_eggs', 'egg_grades.is_defect'])
            ->groupBy('egg_collection_id');
        foreach ($collections->groupBy('batch') as $batch => $rows) {
            $houses = [];
            foreach ($rows->groupBy(fn ($r) => (string) ($r->house ?? '—')) as $house => $houseRows) {
                $good = 0; $damaged = 0;
                foreach ($houseRows as $r) foreach ($lines->get($r->id, collect()) as $l) { if ($l->is_defect) $damaged += (int) $l->total_eggs; else $good += (int) $l->total_eggs; }
                $houses[] = ['house' => preg_replace('/^house\s*/i', '', $house), 'trays' => intdiv($good, 30), 'extra' => $good % 30, 'damage' => $damaged];
            }
            usort($houses, fn ($a, $b) => strnatcasecmp($a['house'], $b['house']));
            $poultry[] = ['batch' => $batch, 'houses' => $houses];
        }

        // The Chairman's engagements: the last week and the next two weeks.
        $engagements = ExecutiveMeeting::where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$date->copy()->subDays(7)->startOfDay(), $date->copy()->addDays(14)->endOfDay()])->orderBy('starts_at')->get()
            ->map(fn ($m) => ['who' => $m->host ?: $m->participants, 'event' => $m->title, 'reason' => $m->reason, 'location' => $m->venue,
                'date' => $m->starts_at->format('d-M'), 'contact' => $m->contact, 'time' => $m->starts_at->format('g:i a'), 'status' => $m->status])->all();

        return compact('visitors', 'directives', 'meetings', 'money', 'construction', 'byOfficer', 'poultry', 'engagements');
    }
}
