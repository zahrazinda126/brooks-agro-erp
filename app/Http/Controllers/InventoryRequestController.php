<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Communication;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryRequest;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\InventoryStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stock requests (Brooks Farm Hub warehouse flow):
 * Request → Approval (head of the requester's department) → Prepared (store) → Dispatched (store; stock
 * leaves here, first to expire first out) → Received (requester confirms what arrived) → Acknowledged (store closes).
 */
class InventoryRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->isDataEntryOnly(), 403);
        $issueStores = $this->issueStoreIds($user);
        $viewStores = InventoryController::storesFor($user)->pluck('id')->all();
        $filter = in_array($request->query('show'), ['mine', 'open', 'closed', 'all'], true) ? $request->query('show') : 'mine';

        $visible = InventoryRequest::with(['store', 'requester', 'lines.item', 'approver', 'preparer', 'dispatcher', 'receiver', 'acknowledger'])
            ->where(function ($q) use ($user, $viewStores) {
                $q->where('requested_by', $user->id)->orWhereIn('inventory_store_id', $viewStores)
                    ->orWhereIn('approver_role', $user->roles->pluck('code')->push($user->role?->code)->filter()->all());
            })->latest('id')->get();
        if ($user->isSuperuser()) {
            $visible = InventoryRequest::with(['store', 'requester', 'lines.item', 'approver', 'preparer', 'dispatcher', 'receiver', 'acknowledger'])->latest('id')->get();
        }
        $needsMe = $visible->filter(fn ($r) => $this->nextStepFor($r, $user, $issueStores) !== null);
        $list = match ($filter) {
            'mine' => $needsMe,
            'open' => $visible->whereIn('status', InventoryRequest::OPEN),
            'closed' => $visible->whereNotIn('status', InventoryRequest::OPEN),
            default => $visible,
        };

        $stores = InventoryStore::where('is_active', true)->orderBy('section')->orderBy('name')->get();

        return view('inventory.requests', [
            'list' => $list->values(),
            'filter' => $filter,
            'counts' => ['mine' => $needsMe->count(), 'open' => $visible->whereIn('status', InventoryRequest::OPEN)->count(),
                'closed' => $visible->whereNotIn('status', InventoryRequest::OPEN)->count(), 'all' => $visible->count()],
            'stores' => $stores,
            'items' => InventoryItem::where('is_active', true)->orderBy('name')->get(['id', 'inventory_store_id', 'name', 'unit', 'code']),
            'issueStores' => $issueStores,
            'user' => $user,
            'stepFor' => fn ($r) => $this->nextStepFor($r, $user, $issueStores),
            'approverName' => fn ($code) => DB::table('roles')->where('code', $code)->value('name') ?? ucfirst($code),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isDataEntryOnly(), 403);
        $data = $request->validate([
            'inventory_store_id' => ['required', Rule::exists('inventory_stores', 'id')->where('is_active', true)],
            'needed_on' => ['nullable', 'date', 'after_or_equal:today'],
            'purpose' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'distinct', Rule::exists('inventory_items', 'id')->where('inventory_store_id', $request->input('inventory_store_id'))],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ], ['lines.required' => 'Add at least one item.', 'lines.*.inventory_item_id.exists' => 'Each item must come from the chosen store.',
            'lines.*.inventory_item_id.distinct' => 'List each item once.']);

        $approverRole = InventoryRequest::approverRoleFor($user);
        $req = DB::transaction(function () use ($data, $user, $approverRole) {
            $req = InventoryRequest::create(['number' => InventoryRequest::nextNumber(), 'inventory_store_id' => $data['inventory_store_id'],
                'requested_by' => $user->id, 'approver_role' => $approverRole, 'needed_on' => $data['needed_on'] ?? null,
                'purpose' => $data['purpose'], 'status' => 'requested']);
            foreach ($data['lines'] as $line) {
                $req->lines()->create(['inventory_item_id' => $line['inventory_item_id'], 'quantity_requested' => $line['quantity']]);
            }
            // Someone who is their own approver (the Director) needs no separate approval.
            if ($req->isApprovedBy($user)) {
                $req->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            }

            return $req;
        });
        AuditLog::record('inventory_request_created', $req, 'Stock request '.$req->number.' raised.', null, $data, 'inventory');
        if ($req->status === 'requested') {
            $this->notifyRole($approverRole, $request, 'Stock request '.$req->number.' needs your approval',
                $user->displayName().' asks the '.$req->store->name.' for '.$req->lines()->count().' item(s): '.$req->purpose);
        }

        return redirect()->route('inventory.requests.index', ['show' => 'open'])->with('status', 'Request '.$req->number.' sent'
            .($req->status === 'requested' ? ' for approval.' : '. It is approved and goes to the store.'));
    }

    /** One endpoint per step; each checks that it is this person's turn. */
    public function step(Request $request, InventoryRequest $inventoryRequest, string $step): RedirectResponse
    {
        $user = $request->user();
        $req = $inventoryRequest->load('lines.item', 'store', 'requester');
        $allowed = $this->nextStepFor($req, $user, $this->issueStoreIds($user));
        $isCancel = $step === 'cancel' && $req->status === 'requested' && $req->requested_by === $user->id;
        abort_unless($isCancel || $allowed === $step || ($step === 'reject' && $allowed === 'approve'), 403, 'This step is not yours to take on this request.');

        $note = null;
        switch ($step) {
            case 'approve':
                $req->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(),
                    'decision_note' => $request->input('decision_note')]);
                break;
            case 'reject':
                $data = $request->validate(['decision_note' => ['required', 'string', 'max:1000']], ['decision_note.required' => 'Say why the request is rejected.']);
                $req->update(['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'decision_note' => $data['decision_note']]);
                $this->notifyUser($req->requested_by, $request, 'Stock request '.$req->number.' was rejected', $data['decision_note']);
                break;
            case 'prepare':
                $data = $request->validate(['issue' => ['required', 'array'], 'issue.*' => ['nullable', 'numeric', 'min:0']]);
                DB::transaction(function () use ($req, $data, $user) {
                    foreach ($req->lines as $line) {
                        $qty = (float) ($data['issue'][$line->id] ?? 0);
                        abort_if($qty > (float) $line->quantity_requested, 422, 'Do not prepare more '.$line->item->name.' than was requested.');
                        if ($qty > 0) InventoryStock::allocate($line->item, $qty); // stops here if the store cannot supply it
                        $line->update(['quantity_issued' => $qty]);
                    }
                    abort_if($req->lines()->sum('quantity_issued') <= 0, 422, 'Prepare at least one item, or ask the approver to reject the request.');
                    $req->update(['status' => 'prepared', 'prepared_by' => $user->id, 'prepared_at' => now()]);
                });
                break;
            case 'dispatch':
                DB::transaction(function () use ($req, $user) {
                    foreach ($req->lines as $line) {
                        $qty = (float) $line->quantity_issued;
                        if ($qty <= 0) continue;
                        $item = $line->item;
                        $location = $item->default_location ?: $req->store->name;
                        $atLocation = (float) DB::table('inventory_movements')->where('inventory_item_id', $item->id)->where('location', $location)
                            ->where('receipt_status', 'confirmed')->selectRaw(InventoryStock::balanceSql().' as b')->value('b');
                        abort_if($qty > $atLocation, 422, 'Only '.number_format($atLocation, 2).' '.$item->unit.' of '.$item->name.' is at '.$location.'.');
                        foreach (InventoryStock::allocate($item, $qty) as $part) {
                            InventoryMovement::create(['inventory_item_id' => $item->id, 'inventory_store_id' => $req->inventory_store_id,
                                'inventory_batch_id' => $part['batch_id'], 'inventory_request_id' => $req->id, 'movement_date' => today(),
                                'movement_type' => 'issued', 'receipt_status' => 'confirmed', 'location' => $location, 'quantity' => $part['quantity'],
                                'supplier_or_recipient' => $req->requester->displayName(), 'reference' => $req->number,
                                'notes' => 'Stock request '.$req->number.': '.$req->purpose, 'recorded_by' => $user->id]);
                        }
                    }
                    $req->update(['status' => 'dispatched', 'dispatched_by' => $user->id, 'dispatched_at' => now()]);
                });
                $this->notifyUser($req->requested_by, $request, 'Stock request '.$req->number.' is on its way',
                    'The '.$req->store->name.' has dispatched your items. Confirm what you receive under Stock requests.');
                break;
            case 'receive':
                $data = $request->validate(['received' => ['required', 'array'], 'received.*' => ['nullable', 'numeric', 'min:0'], 'receipt_note' => ['nullable', 'string', 'max:1000']]);
                $short = false;
                foreach ($req->lines as $line) {
                    $got = (float) ($data['received'][$line->id] ?? $line->quantity_issued);
                    $short = $short || abs($got - (float) $line->quantity_issued) > 0.001;
                    $line->update(['quantity_received' => $got]);
                }
                abort_if($short && blank($data['receipt_note'] ?? null), 422, 'What you received differs from what was dispatched: add a note saying what is missing or extra.');
                $req->update(['status' => 'received', 'received_by' => $user->id, 'received_at' => now(), 'receipt_note' => $data['receipt_note'] ?? null]);
                break;
            case 'acknowledge':
                $req->update(['status' => 'acknowledged', 'acknowledged_by' => $user->id, 'acknowledged_at' => now()]);
                break;
            case 'cancel':
                $req->update(['status' => 'cancelled']);
                break;
            default:
                abort(404);
        }
        AuditLog::record('inventory_request_'.$step, $req, 'Stock request '.$req->number.': '.$step.'.', null, ['status' => $req->status], 'inventory');

        return back()->with('status', 'Request '.$req->number.': '.InventoryRequest::STEPS[$req->status].'.');
    }

    /** The step this person may take next on a request, or null when it is not their turn. */
    private function nextStepFor(InventoryRequest $req, User $user, array $issueStores): ?string
    {
        $isStore = in_array($req->inventory_store_id, $issueStores, true);

        return match ($req->status) {
            'requested' => $req->isApprovedBy($user) ? 'approve' : null,
            'approved' => $isStore ? 'prepare' : null,
            'prepared' => $isStore ? 'dispatch' : null,
            'dispatched' => $req->requested_by === $user->id ? 'receive' : null,
            'received' => $isStore ? 'acknowledge' : null,
            default => null,
        };
    }

    /** Stores where this person may issue stock (store staff). */
    private function issueStoreIds(User $user): array
    {
        return InventoryController::storesFor($user)->filter(fn ($s) => $s->access['can_issue'] ?? false)->pluck('id')->all();
    }

    private function notifyRole(string $roleCode, Request $request, string $subject, string $body): void
    {
        User::where('is_active', true)->where(fn ($q) => $q->whereHas('role', fn ($r) => $r->where('code', $roleCode))
            ->orWhereHas('roles', fn ($r) => $r->where('code', $roleCode)))->pluck('id')
            ->each(fn ($id) => $this->notifyUser($id, $request, $subject, $body));
    }

    private function notifyUser(int $userId, Request $request, string $subject, string $body): void
    {
        Communication::create(['kind' => 'notification', 'subject' => $subject, 'body' => $body, 'audience' => 'individual',
            'recipient_id' => $userId, 'created_by' => $request->user()->id, 'action_url' => route('inventory.requests.index')]);
    }
}
