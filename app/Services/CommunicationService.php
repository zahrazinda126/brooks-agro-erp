<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\User;

class CommunicationService
{
    public function toUser(User|int $recipient, string $subject, string $body, string $kind = 'workflow', ?int $senderId = null): Communication
    {
        $recipientId = $recipient instanceof User ? $recipient->id : $recipient;

        return Communication::create([
            'kind' => $kind, 'subject' => $subject, 'body' => $body,
            'audience' => 'individual', 'recipient_id' => $recipientId,
            'created_by' => $senderId ?: $recipientId,
        ]);
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
}
