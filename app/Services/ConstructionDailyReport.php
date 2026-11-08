<?php

namespace App\Services;

use App\Models\PaReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConstructionDailyReport
{
    public function rows(string $date): Collection
    {
        $paReport = PaReport::whereDate('report_date', $date)
            ->orderByRaw("case when status = 'submitted' then 0 else 1 end")
            ->latest('updated_at')->first();
        $updates = collect($paReport?->construction_updates ?? [])->keyBy('project_id');
        $savedProjectIds = $updates->keys()->map(fn ($id) => (int) $id)->all();

        $projects = DB::table('construction_projects')
            ->where(function ($query) use ($savedProjectIds) {
                $query->where('status', 'active');
                if ($savedProjectIds) $query->orWhereIn('id', $savedProjectIds);
            })
            ->whereDate('starts_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('expected_end_on')->orWhereDate('expected_end_on', '>=', $date))
            ->orderBy('name')->get();

        return $projects->map(function ($project) use ($date, $updates, $paReport) {
            $update = $updates->get($project->id, []);
            $projectActivities = collect($paReport?->activities ?? [])->filter(fn ($activity) =>
                mb_strtolower(trim((string) ($activity['project'] ?? ''))) === mb_strtolower(trim($project->name))
            );
            $statuses = $projectActivities->pluck('status')->map(fn ($status) => mb_strtolower(trim((string) $status)));
            $hasStatuses = $statuses->isNotEmpty() && $statuses->every(fn ($status) => $status !== '');
            $completedCount = $statuses->filter(fn ($status) => in_array($status, ['done', 'complete', 'completed'], true))->count();
            $completed = $hasStatuses ? (int) round($completedCount / $statuses->count() * 100) : null;
            $people = DB::table('construction_project_workers')->where('construction_project_id', $project->id)
                ->where(fn ($query) => $query->whereNull('from_on')->orWhereDate('from_on', '<=', $date))
                ->where(fn ($query) => $query->whereNull('to_on')->orWhereDate('to_on', '>=', $date))->count();
            $contact = DB::table('construction_project_workers')->join('employees', 'employees.id', '=', 'construction_project_workers.employee_id')
                ->where('construction_project_workers.construction_project_id', $project->id)->where('construction_project_workers.role', 'lead')
                ->where(fn ($query) => $query->whereNull('from_on')->orWhereDate('from_on', '<=', $date))
                ->where(fn ($query) => $query->whereNull('to_on')->orWhereDate('to_on', '>=', $date))
                ->orderBy('construction_project_workers.id')
                ->value(DB::raw("concat(employees.first_name, ' ', employees.last_name)"));

            return [
                'deliverable' => $project->name,
                'project_id' => $project->id,
                'timeline' => $project->starts_on
                    ? \Illuminate\Support\Carbon::parse($project->starts_on)->format('d M Y').($project->expected_end_on ? ' – '.\Illuminate\Support\Carbon::parse($project->expected_end_on)->format('d M Y') : ' – Ongoing')
                    : '—',
                'people' => $people,
                'contact' => $contact ?: '—',
                'activity_count' => $paReport ? $projectActivities->count() : '—',
                'tasks' => filled($update['tasks'] ?? null) ? $update['tasks'] : ($paReport ? 'Not provided in PA daily report' : 'Awaiting PA daily report'),
                'completed' => $completed,
                'pending' => $completed === null ? null : 100 - $completed,
                'challenges' => filled($update['challenges'] ?? null) ? $update['challenges'] : '—',
                'ppe' => filled($update['ppe'] ?? null) ? $update['ppe'] : '—',
                'remarks' => filled($update['remarks'] ?? null) ? $update['remarks'] : '—',
            ];
        });
    }
}
