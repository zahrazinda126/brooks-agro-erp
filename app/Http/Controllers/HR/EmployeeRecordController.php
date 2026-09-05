<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeRecord;
use App\Models\Communication;
use App\Models\Attachment;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeRecordController extends Controller
{
    private const SCREENS = [
        'attendance' => ['title' => 'Attendance', 'singular' => 'attendance record'],
        'leave' => ['title' => 'Leave', 'singular' => 'leave record'],
        'overtime' => ['title' => 'Overtime', 'singular' => 'overtime record'],
        'contracts' => ['title' => 'Contracts', 'singular' => 'contract record'],
        'advances' => ['title' => 'Salary Advances', 'singular' => 'salary advance'],
        'welfare' => ['title' => 'Welfare', 'singular' => 'welfare record'],
        'employee-medical' => ['title' => 'Medical Records', 'singular' => 'medical record'],
        'discipline' => ['title' => 'Disciplinary', 'singular' => 'disciplinary record'],
        'training' => ['title' => 'Training', 'singular' => 'training record'],
        'staff-performance' => ['title' => 'Performance Reviews', 'singular' => 'performance review'],
    ];

    public function index(Request $request, string $module): View
    {
        $screen = $this->screen($request, $module);

        return view('hr.records.index', [
            'screen' => $screen,
            'module' => $module,
            'records' => EmployeeRecord::with(['employee.department', 'approver', 'attachments'])
                ->where('type', $module)->latest('recorded_on')->paginate(25),
        ]);
    }

    public function create(Request $request, string $module): View
    {
        $screen = $this->screen($request, $module, 'create');

        return view('hr.records.create', [
            'screen' => $screen,
            'module' => $module,
            'employees' => Employee::active()->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $screen = $this->screen($request, $module, 'create');
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'recorded_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:recorded_on'],
            'probation_ends_on' => ['nullable', 'date', 'after_or_equal:recorded_on'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'signed_on' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'title' => [in_array($module, ['training', 'staff-performance'], true) ? 'required' : 'prohibited', 'string', 'max:160'],
            'provider' => [$module === 'training' ? 'nullable' : 'prohibited', 'string', 'max:160'],
            'score' => [$module === 'staff-performance' ? 'nullable' : 'prohibited', 'numeric', 'min:0', 'max:100'],
            'category' => [$module === 'welfare' ? 'required' : 'prohibited', 'in:financial-assistance,emergency,bereavement,medical,transport,other'],
            'details' => [$module === 'contracts' ? 'nullable' : 'required', 'string', 'max:5000'],
            'financial_penalty' => ['nullable', 'boolean'],
            'document' => [$module === 'contracts' ? 'nullable' : 'prohibited', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $isPenalty = $module === 'discipline' && $request->boolean('financial_penalty');

        if ($isPenalty && empty($data['amount'])) {
            return back()->withErrors(['amount' => 'Enter the amount to deduct.'])->withInput();
        }

        $record = EmployeeRecord::create([
            ...$data,
            'type' => $module,
            'status' => $isPenalty ? 'pending' : 'recorded',
            'is_payroll_deduction' => $isPenalty,
            'payroll_deduction_status' => $isPenalty ? 'pending' : null,
            'created_by' => $request->user()->id,
        ]);

        if ($module === 'contracts' && $request->hasFile('document')) {
            $file = $request->file('document');
            $path = $file->store('contracts', 'local');

            $record->attachments()->create([
                'collection' => 'signed-contract',
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        AuditLog::record('created', $record,
            "Created {$screen['singular']} for {$record->employee_id}.", null, null, $module);

        return redirect()->route('hr.records.index', $module)
            ->with('status', ucfirst($screen['singular']).' saved.');
    }

    public function approve(Request $request, string $module, EmployeeRecord $record): RedirectResponse
    {
        $this->screen($request, $module, 'approve');
        abort_unless($record->type === $module && $record->is_payroll_deduction, 404);
        abort_unless($record->status === 'pending', 422, 'This penalty has already been decided.');

        $record->update([
            'status' => 'approved',
            'payroll_deduction_status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $employee = $record->employee()->with('user')->first();
        if ($employee?->user) {
            Communication::create([
                'kind' => 'notification',
                'subject' => 'A payroll penalty was approved',
                'body' => 'A penalty of UGX '.number_format((float) $record->amount)
                    .' was approved for '.$record->recorded_on->format('d M Y').'. '
                    .($record->details ?: 'Open your salary details for more information.'),
                'audience' => 'individual',
                'recipient_id' => $employee->user->id,
                'created_by' => $request->user()->id,
                'action_url' => route('payroll.my-balance', absolute: false),
            ]);
        }

        return redirect()->route('hr.records.index', $module)
            ->with('status', 'Penalty approved and queued for the next payroll calculation.');
    }

    public function download(Request $request, string $module, EmployeeRecord $record, Attachment $attachment)
    {
        $this->screen($request, $module);
        abort_unless($record->type === 'contracts'
            && $attachment->attachable_type === EmployeeRecord::class
            && $attachment->attachable_id === $record->id, 404);

        return \Illuminate\Support\Facades\Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }

    private function screen(Request $request, string $module, string $ability = 'view'): array
    {
        abort_unless(isset(self::SCREENS[$module]), 404);
        abort_if($module === 'staff-performance', 404);
        abort_unless($ability === 'view'
            ? $request->user()->canAccess($module)
            : $request->user()->canDo($module, $ability), 403);

        return self::SCREENS[$module];
    }
}
