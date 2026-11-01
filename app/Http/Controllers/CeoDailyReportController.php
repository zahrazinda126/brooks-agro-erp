<?php

namespace App\Http\Controllers;

use App\Models\{AuditLog, CeoReport, User};
use App\Services\{CeoDailyReport, CommunicationService};
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * The CEO's daily report to the Director, in the format of her Word report: production, feed, sales and
 * payables come from the system; she writes the highlights, projects and notes, then sends it.
 */
class CeoDailyReportController extends Controller
{
    public function write(Request $request, CeoDailyReport $facts): View
    {
        $user = $request->user();
        abort_unless($this->isCeo($user), 403);
        $date = $this->day($request->input('date'));
        $report = CeoReport::firstOrNew(['user_id' => $user->id, 'report_date' => $date->toDateString()]);

        return view('ceo.daily-report.write', [
            'date' => $date, 'report' => $report,
            'facts' => $report->isSubmitted() ? $report->facts : $facts->facts($date),
        ]);
    }

    public function save(Request $request, CeoDailyReport $facts, CommunicationService $communications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->isCeo($user), 403);
        $submit = $request->input('action') === 'submit';
        $data = $request->validate([
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'highlights' => [$submit ? 'required' : 'nullable', 'string', 'max:5000'],
            'projects' => ['nullable', 'string', 'max:5000'],
            'hr_notes' => ['nullable', 'string', 'max:5000'],
            'enterprise_notes' => ['nullable', 'string', 'max:5000'],
        ], ['highlights.required' => 'Write the operational highlights before sending.']);
        $date = $this->day($data['report_date']);
        $report = CeoReport::firstOrNew(['user_id' => $user->id, 'report_date' => $date->toDateString()]);
        abort_if($report->isSubmitted(), 422, 'This report has already been sent to the Director.');

        $report->fill(collect($data)->only(['highlights', 'projects', 'hr_notes', 'enterprise_notes'])->all()
            + ($submit ? ['status' => 'submitted', 'submitted_at' => now(), 'facts' => $facts->facts($date)] : ['status' => 'draft']))->save();

        if (! $submit) {
            return redirect()->route('ceo.daily-report', ['date' => $date->toDateString()])->with('status', 'Draft saved.');
        }

        AuditLog::record('ceo_daily_report_submitted', $report, "CEO's daily report for {$date->format('d M Y')} sent to the Director.", null, ['report_date' => $date->toDateString()], 'approvals');
        $communications->toRole('director', "CEO's daily report: ".$date->format('d M Y'),
            "{$user->displayName()} sent the CEO's daily report for {$date->format('l, d M Y')}.", $user->id, route('ceo.reports.show', $report, false));

        return redirect()->route('ceo.reports.show', $report)->with('status', 'Report sent to the Director.');
    }

    /** Past reports: the CEO sees her own; the Director sees every sent report. */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->isCeo($user) || $this->isReader($user), 403);
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $reports = CeoReport::with('author')
            ->when($this->isCeo($user) && ! $this->isReader($user), fn ($q) => $q->where('user_id', $user->id), fn ($q) => $q->where('status', 'submitted'))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('report_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('report_date', '<=', $d))
            ->latest('report_date')->paginate(30)->withQueryString();

        return view('ceo.daily-report.index', ['reports' => $reports, 'isReader' => $this->isReader($user)]);
    }

    public function show(Request $request, CeoReport $report): View|RedirectResponse
    {
        $user = $request->user();
        $isAuthor = $report->user_id === $user->id;
        abort_unless($isAuthor || ($this->isReader($user) && $report->isSubmitted()), 403);
        if ($isAuthor && ! $report->isSubmitted()) return redirect()->route('ceo.daily-report', ['date' => $report->report_date->toDateString()]);
        if (! $isAuthor && $this->isDirector($user) && ! $report->read_at) $report->forceFill(['read_at' => now()])->save();

        return view('ceo.daily-report.show', ['report' => $report->load('author'), 'facts' => $report->facts ?? [], 'canComment' => $this->isReader($user) && ! $isAuthor]);
    }

    public function comment(Request $request, CeoReport $report, CommunicationService $communications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->isReader($user) && $report->isSubmitted() && $report->user_id !== $user->id, 403);
        $data = $request->validate(['director_comment' => ['required', 'string', 'max:3000']]);
        $report->forceFill(['director_comment' => $data['director_comment'], 'commented_by' => $user->id, 'commented_at' => now(), 'read_at' => $report->read_at ?? now()])->save();
        $communications->toUser($report->user_id, 'Comment on your daily report ('.$report->report_date->format('d M').')',
            $data['director_comment'], 'workflow', $user->id, route('ceo.reports.show', $report, false));

        return back()->with('status', 'Comment sent.');
    }

    private function isCeo(User $user): bool
    {
        return $user->role?->code === 'ceo' || $user->roles->contains('code', 'ceo');
    }

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
