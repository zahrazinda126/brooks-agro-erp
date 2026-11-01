<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\User;

class CommunicationService
{
    public function toUser(User|int $recipient, string $subject, string $body, string $kind = 'workflow', ?int $senderId = null, ?string $actionUrl = null): Communication
    {
        $recipientId = $recipient instanceof User ? $recipient->id : $recipient;

        $message = Communication::create([
            'kind' => $kind, 'subject' => $subject, 'body' => $body,
            'audience' => 'individual', 'recipient_id' => $recipientId,
            'created_by' => $senderId ?: $recipientId,
            'action_url' => $actionUrl,
        ]);

        $user = $recipient instanceof User ? $recipient : User::find($recipientId);
        if ($user?->email && config('mail.default') !== 'log') {
            try { \Illuminate\Support\Facades\Mail::raw($body."\n\n".($actionUrl ? url($actionUrl) : ''), fn ($mail) => $mail->to($user->email)->subject($subject)); } catch (\Throwable $e) { report($e); }
        }

        return $message;
    }

    public function toRole(string $roleCode, string $subject, string $body, ?int $senderId = null, ?string $actionUrl = null): int
    {
        $recipients = User::where(function ($query) use ($roleCode) {
            $query->whereHas('roles', fn ($roles) => $roles->where('code', $roleCode))
                ->orWhereHas('role', fn ($role) => $role->where('code', $roleCode));
        })->where('is_active', true)->get();

        $recipients->each(fn (User $recipient) => $this->toUser(
            $recipient,
            $subject,
            $body,
            'workflow',
            $senderId,
            $actionUrl,
        ));

        return $recipients->count();
    }

    /**
     * A daily record is waiting for a supervisor's check: tell the supervisor appointed for that
     * unit and day, or the usual reviewer when nobody is appointed (Sales Manager for eggs,
     * Production Manager for everything else).
     */
    public function recordAwaitingReview(string $unit, $date, string $what, ?int $senderId = null, bool $eggs = false): int
    {
        $day = \Illuminate\Support\Carbon::parse($date)->toDateString();
        $supervisors = \App\Models\SupervisorAssignment::where('unit_code', $unit)->where('is_active', true)
            ->whereDate('starts_on', '<=', $day)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day))
            ->pluck('supervisor_id')->unique()->reject(fn ($id) => $id === $senderId);
        $subject = 'Record waiting for your check';
        $body = $what.' ('.ucfirst($unit).', '.\Illuminate\Support\Carbon::parse($day)->format('d M Y').') was entered and needs checking.';
        $url = route('supervision.index', [], false);
        if ($supervisors->isNotEmpty()) {
            $supervisors->each(fn ($id) => $this->toUser($id, $subject, $body, 'workflow', $senderId, $url));
            return $supervisors->count();
        }
        return $this->toRole($eggs ? 'sales-manager' : 'production-manager', $subject, $body, $senderId, $url);
    }

    /** People covering a role's sales approvals (approval cover) are told too. */
    public function toSalesCover(string $roleCode, string $subject, string $body, ?int $senderId = null, ?string $actionUrl = null): int
    {
        $ids = \App\Models\ApprovalDelegation::current()->where('role_code', $roleCode)->pluck('delegate_id')->unique();
        $ids->each(fn ($id) => $this->toUser($id, $subject.' (covering)', $body, 'workflow', $senderId, $actionUrl));

        return $ids->count();
    }

    /** Tell everyone who may approve a module (e.g. HR penalties, bird consumption). */
    public function toApprovers(string $moduleKey, string $subject, string $body, ?int $senderId = null, ?string $actionUrl = null): int
    {
        $recipients = User::with(['role', 'roles'])->where('is_active', true)->get()
            ->filter(fn (User $user) => $user->id !== $senderId && $user->canDo($moduleKey, 'approve'));
        $recipients->each(fn (User $user) => $this->toUser($user, $subject, $body, 'workflow', $senderId, $actionUrl));

        return $recipients->count();
    }

    public function toAudience(string $audience, string $subject, string $body, ?int $senderId = null, ?string $actionUrl = null): int
    {
        $recipients = User::with(['role', 'roles', 'department', 'departments'])
            ->where('is_active', true)->get()
            ->filter(fn (User $user) => Communication::audiencesFor($user)->contains($audience));

        $recipients->each(fn (User $recipient) => $this->toUser(
            $recipient, $subject, $body, 'directive', $senderId, $actionUrl
        ));

        return $recipients->count();
    }
}
