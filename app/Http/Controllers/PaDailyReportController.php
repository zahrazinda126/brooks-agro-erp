<?php

namespace App\Http\Controllers;

use App\Models\{AuditLog, PaReport, User};
use App\Services\{CommunicationService, PaDailyReport};
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * The PA's daily report to the Director: the day's facts come from the system,
 * the PA writes three short parts and submits; the Director reads it and can comment.
 */
class PaDailyReportController extends Controller
{
    /** The PA writes the report for a day (today by default). */
    public function write(Request $request, PaDailyReport $facts): View
    {
        $user = $request->user();
        abort_unless($user->isPersonalAssistant(), 403);
        $date = $this->day($request->input('date'));
        $report = PaReport::firstOrNew(['user_id' => $user->id, 'report_date' => $date->toDateString()]);

        return view('pa.daily-report.write', [
            'date' => $date, 'report' => $report,
            'facts' => $report->isSubmitted() ? $report->facts : $facts->facts($date),
        ]);
    }

    public function save(Request $request, PaDailyReport $facts, CommunicationService $communications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isPersonalAssistant(), 403);
        $submit = $request->input('action') === 'submit';
        $data = $request->validate([
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'activities' => ['nullable', 'array', 'max:60'],
            'activities.*.project' => ['nullable', 'string', 'max:120'],
            'activities.*.activity' => ['nullable', 'string', 'max:500'],
            'activities.*.status' => ['nullable', 'string', 'max:120'],
            'issues' => ['nullable', 'string', 'max:5000'],
            'plan_tomorrow' => ['nullable', 'string', 'max:5000'],
        ]);
        $activities = collect($data['activities'] ?? [])->map(fn ($row) => array_map(fn ($v) => trim((string) $v), $row + ['project' => '', 'activity' => '', 'status' => '']))
            ->filter(fn ($row) => $row['activity'] !== '')->values()->all();
        $date = $this->day($data['report_date']);
        $report = PaReport::firstOrNew(['user_id' => $user->id, 'report_date' => $date->toDateString()]);
        abort_if($report->isSubmitted(), 422, 'This report has already been sent to the Director.');

        $report->fill([
            'activities' => $activities, 'issues' => $data['issues'] ?? null, 'plan_tomorrow' => $data['plan_tomorrow'] ?? null,
        ] + ($submit ? ['status' => 'submitted', 'submitted_at' => now(), 'facts' => $facts->facts($date)] : ['status' => 'draft']))->save();

        if (! $submit) {
            return redirect()->route('pa.daily-report', ['date' => $date->toDateString()])->with('status', 'Draft saved.');
        }

        AuditLog::record('pa_daily_report_submitted', $report, "Daily report for {$date->format('d M Y')} sent to the Director.", null, ['report_date' => $date->toDateString()], 'directives');
        $communications->toRole('director', 'Daily report: '.$date->format('d M Y'),
            "{$user->displayName()} sent the daily report for {$date->format('l, d M Y')}.", $user->id, route('pa.reports.show', $report, false));

        return redirect()->route('pa.reports.show', $report)->with('status', 'Report sent to the Director.');
    }

    /** Past reports: the PA sees their own; the Director sees every submitted report. */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isPersonalAssistant() || $this->isReader($user), 403);
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $reports = PaReport::with('author')
            ->when($user->isPersonalAssistant() && ! $this->isReader($user), fn ($q) => $q->where('user_id', $user->id), fn ($q) => $q->where('status', 'submitted'))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('report_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('report_date', '<=', $d))
            ->latest('report_date')->paginate(30)->withQueryString();

        return view('pa.daily-report.index', ['reports' => $reports, 'isReader' => $this->isReader($user)]);
    }

    public function show(Request $request, PaReport $report): View|RedirectResponse
    {
        $user = $request->user();
        $isAuthor = $report->user_id === $user->id;
        abort_unless($isAuthor || ($this->isReader($user) && $report->isSubmitted()), 403);
        if ($isAuthor && ! $report->isSubmitted()) return redirect()->route('pa.daily-report', ['date' => $report->report_date->toDateString()]);
        // The PA can see when the Director has read it.
        if (! $isAuthor && $this->isDirector($user) && ! $report->read_at) $report->forceFill(['read_at' => now()])->save();

        return view('pa.daily-report.show', ['report' => $report->load('author'), 'facts' => $report->facts ?? [], 'canComment' => $this->isReader($user) && ! $isAuthor]);
    }

    public function comment(Request $request, PaReport $report, CommunicationService $communications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->isReader($user) && $report->isSubmitted() && $report->user_id !== $user->id, 403);
        $data = $request->validate(['director_comment' => ['required', 'string', 'max:3000']]);
        $report->forceFill(['director_comment' => $data['director_comment'], 'commented_by' => $user->id, 'commented_at' => now(), 'read_at' => $report->read_at ?? now()])->save();
        $communications->toUser($report->user_id, 'Comment on your daily report ('.$report->report_date->format('d M').')',
            $data['director_comment'], 'workflow', $user->id, route('pa.reports.show', $report, false));

        return back()->with('status', 'Comment sent.');
    }

    /** The Director reads the PA's reports. */
    private function isReader(User $user): bool
    {
        return $this->isDirector($user) || $user->isSuperuser();
    }

    private function isDirector(User $user): bool
    {
        return $user->role?->code === 'director' || $user->roles->contains('code', 'director');
    }

    private function day(?string $value): Carbon
    {
        $date = $value ? Carbon::parse($value)->startOfDay() : today();
        return $date->isFuture() ? today() : $date;
    }
}
