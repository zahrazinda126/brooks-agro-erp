<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InventoryController;
use App\Models\Employee;
use App\Models\InventoryMovement;
use App\Models\InventoryStore;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffConsumablesController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('hr'), 403);

        $store = InventoryStore::where('code', 'welfare')->where('is_active', true)->firstOrFail();
        $visibleStore = InventoryController::storesFor($request->user())->firstWhere('id', $store->id);
        abort_unless($visibleStore && $visibleStore->access['can_view'], 403);

        $filters = $request->validate([
            'employee' => ['nullable', 'integer', 'exists:employees,id'],
            'item' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $issues = InventoryMovement::with([
            'item',
            'employee' => fn ($query) => $query->withTrashed(),
            'recorder',
        ])->where('inventory_store_id', $store->id)
            ->where('movement_type', 'issued')
            ->where('receipt_status', 'confirmed')
            ->when($filters['employee'] ?? null, fn ($query, $employee) => $query->where('employee_id', $employee))
            ->when($filters['item'] ?? null, fn ($query, $item) => $query->where('inventory_item_id', $item))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('movement_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('movement_date', '<=', $to))
            ->latest('movement_date')->latest('id')->paginate(40)->withQueryString();

        return view('hr.consumables.index', [
            'store' => $store,
            'canViewValue' => $visibleStore->access['can_view_value'],
            'issues' => $issues,
            'filters' => $filters,
            'employees' => Employee::withTrashed()->orderBy('last_name')->orderBy('first_name')->get(),
            'items' => $store->items()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
