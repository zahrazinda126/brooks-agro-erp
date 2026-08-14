<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    protected $table = 'executive_communications';

    protected $fillable = ['kind', 'subject', 'body', 'audience', 'recipient_id', 'created_by'];

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }

    public function scopeVisibleTo($query, User $user)
    {
        $roles = $user->roles->pluck('code')->push($user->role?->code)->filter()->unique();
        $departments = $user->departments->pluck('code')->push($user->department?->code)->filter()->unique();
        $audiences = collect(['all_staff']);
        if ($roles->intersect(['director', 'ceo', 'personal-assistant', 'production-manager', 'finance-manager', 'hr-manager', 'sales-manager', 'department-manager'])->isNotEmpty()) {
            $audiences->push('management');
        }
        if ($departments->intersect(['finance', 'div-finance', 'sales'])->isNotEmpty() || $roles->contains('finance-manager')) $audiences->push('finance');
        if ($departments->intersect(['hr', 'div-hr'])->isNotEmpty() || $roles->contains('hr-manager')) $audiences->push('hr');
        if ($departments->intersect(['production', 'div-production', 'poultry', 'goats', 'rabbits', 'dogs', 'garden', 'veterinary', 'inventory'])->isNotEmpty() || $roles->contains('production-manager')) $audiences->push('production');
        if ($departments->intersect(['sales'])->isNotEmpty() || $roles->contains('sales-manager')) $audiences->push('sales');

        return $query->where(function ($visible) use ($user, $audiences) {
            $visible->where('recipient_id', $user->id)
                ->orWhere(fn ($broadcast) => $broadcast->whereNull('recipient_id')->whereIn('audience', $audiences->unique()));
        });
    }
}
