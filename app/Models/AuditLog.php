<?php

namespace App\Models;

use App\Services\AttendanceRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'username', 'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'module_key', 'description',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record something that happened. Used by the login flow now and by
     * a model observer later, so every table gets a trail for free.
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        ?string $description = null,
        ?array $old = null,
        ?array $new = null,
        ?string $moduleKey = null,
    ): void {
        $user = auth()->user();

        static::create([
            'user_id'        => $user?->id,
            'username'       => $user?->username,
            'event'          => $event,
            'auditable_type' => $subject ? $subject::class : 'system',
            'auditable_id'   => $subject?->getKey(),
            'old_values'     => $old,
            'new_values'     => $new,
            'module_key'     => $moduleKey,
            'description'    => $description,
            'ip_address'     => request()->ip(),
            'user_agent'     => substr((string) request()->userAgent(), 0, 255),
            'created_at'     => now(),
        ]);

        if ($user) {
            AttendanceRecorder::markPresent($user, $event === 'login' ? 'sign-in' : 'saved system activity');
        }
    }
}
