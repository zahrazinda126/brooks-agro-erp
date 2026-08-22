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

    public function toRole(string $roleCode, string $subject, string $body, ?int $senderId = null): int
    {
        $recipients = User::where(function ($query) use ($roleCode) {
            $query->whereHas('roles', fn ($roles) => $roles->where('code', $roleCode))
                ->orWhereHas('role', fn ($role) => $role->where('code', $roleCode));
        })->where('is_active', true)->get();

        $recipients->each(fn (User $recipient) => $this->toUser($recipient, $subject, $body, 'workflow', $senderId));

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
