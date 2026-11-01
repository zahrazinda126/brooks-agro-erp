<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request for stock from a store: Request → Approval → Prepared → Dispatched → Received → Acknowledged.
 * The head of the requester's department approves; the store prepares and dispatches (stock leaves
 * the store at dispatch); the requester confirms what arrived; the store acknowledges and closes it.
 */
class InventoryRequest extends Model
{
    public const STEPS = [
        'requested' => 'Waiting for approval',
        'approved' => 'Approved · store to prepare',
        'prepared' => 'Prepared · ready to dispatch',
        'dispatched' => 'Dispatched · requester to confirm',
        'received' => 'Received · store to acknowledge',
        'acknowledged' => 'Closed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    public const OPEN = ['requested', 'approved', 'prepared', 'dispatched', 'received'];

    /**
     * Who approves, by the requester's role (department records are not reliable: managers sit
     * under "administration"). A department head's own request goes one level up.
     */
    private const APPROVER_BY_ROLE = [
        'veterinary-officer' => 'production-manager',
        'daily-supervisor' => 'production-manager',
        'poultry-attendant' => 'production-manager',
        'casual-worker' => 'production-manager',
        'department-manager' => 'production-manager',
        'cashier' => 'finance-manager',
        'production-manager' => 'ceo',
        'hr-manager' => 'ceo',
        'finance-manager' => 'ceo',
        'sales-manager' => 'ceo',
        'inventory-officer' => 'ceo',
        'store-keeper' => 'ceo',
        'pa' => 'director',
        'personal-assistant' => 'director',
        'ceo' => 'director',
        'director' => 'director',
        'system-admin' => 'director',
    ];

    protected $fillable = ['number', 'inventory_store_id', 'requested_by', 'approver_role', 'needed_on', 'purpose', 'status',
        'decision_note', 'receipt_note',
        'approved_by', 'approved_at', 'prepared_by', 'prepared_at', 'dispatched_by', 'dispatched_at',
        'received_by', 'received_at', 'acknowledged_by', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['needed_on' => 'date', 'approved_at' => 'datetime', 'prepared_at' => 'datetime', 'dispatched_at' => 'datetime',
            'received_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public static function approverRoleFor(User $user): string
    {
        $codes = $user->roles->pluck('code')->push($user->role?->code)->filter()->unique();
        // The most senior role the person holds decides (a manager who is also a supervisor asks the CEO).
        foreach (['director', 'ceo', 'system-admin', 'pa', 'personal-assistant', 'production-manager', 'hr-manager',
            'finance-manager', 'sales-manager', 'inventory-officer', 'store-keeper', 'cashier', 'department-manager',
            'veterinary-officer', 'daily-supervisor', 'poultry-attendant', 'casual-worker'] as $code) {
            if ($codes->contains($code)) {
                return self::APPROVER_BY_ROLE[$code];
            }
        }

        return 'ceo';
    }

    /** Numbers follow the farm's batch style: SR001/26, SR002/26 … (26 = the year). */
    public static function nextNumber(): string
    {
        $year = now()->format('y');
        $last = static::where('number', 'like', '%/'.$year)->orderByDesc('id')->value('number');
        $next = $last ? ((int) preg_replace('/\D/', '', strstr($last, '/', true))) + 1 : 1;

        return sprintf('SR%03d/%s', $next, $year);
    }

    public function isApprovedBy(User $user): bool
    {
        return $user->isSuperuser() || $user->roles->pluck('code')->push($user->role?->code)->contains($this->approver_role);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryRequestLine::class);
    }

    public function stepUser(string $step): BelongsTo
    {
        return $this->belongsTo(User::class, $step.'_by');
    }

    public function approver(): BelongsTo { return $this->stepUser('approved'); }
    public function preparer(): BelongsTo { return $this->stepUser('prepared'); }
    public function dispatcher(): BelongsTo { return $this->stepUser('dispatched'); }
    public function receiver(): BelongsTo { return $this->stepUser('received'); }
    public function acknowledger(): BelongsTo { return $this->stepUser('acknowledged'); }

    public function statusLabel(): string
    {
        return self::STEPS[$this->status] ?? ucfirst($this->status);
    }
}
