<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SalesCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerDirectoryController extends Controller
{
    public function customers(Request $request): View
    {
        $this->authorizeOwner($request);
        $search = trim((string) $request->query('search'));

        $customers = SalesCustomer::query()
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            }))
            ->withCount(['sales as purchase_count' => fn ($query) => $query->where('status', '!=', 'void')])
            ->withSum(['sales as total_purchased' => fn ($query) => $query->where('status', '!=', 'void')], 'total')
            ->withSum(['sales as outstanding_balance' => fn ($query) => $query->where('status', '!=', 'void')], 'balance')
            ->withMax(['sales as last_purchase_on' => fn ($query) => $query->where('status', '!=', 'void')], 'sale_date')
            ->orderByDesc('total_purchased')->orderBy('name')->paginate(30)->withQueryString();

        $sales = Sale::where('status', '!=', 'void');

        return view('owner.customers', [
            'customers' => $customers,
            'search' => $search,
            'totalCustomers' => SalesCustomer::where('is_active', true)->count(),
            'repeatCustomers' => SalesCustomer::whereHas('sales', fn ($query) => $query->where('status', '!=', 'void'), '>=', 2)->count(),
            'oneTimeCustomers' => SalesCustomer::whereHas('sales', fn ($query) => $query->where('status', '!=', 'void'), '=', 1)->count(),
            'customersWithoutSales' => SalesCustomer::whereDoesntHave('sales', fn ($query) => $query->where('status', '!=', 'void'))->count(),
            'topCustomers' => SalesCustomer::query()->withCount(['sales as purchase_count' => fn ($query) => $query->where('status', '!=', 'void')])
                ->withSum(['sales as total_purchased' => fn ($query) => $query->where('status', '!=', 'void')], 'total')
                ->whereHas('sales', fn ($query) => $query->where('status', '!=', 'void'))
                ->orderByDesc('purchase_count')->orderByDesc('total_purchased')->limit(5)->get(),
            'salesValue' => (float) (clone $sales)->sum('total'),
            'outstanding' => (float) (clone $sales)->sum('balance'),
        ]);
    }

    private function authorizeOwner(Request $request): void
    {
        $codes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        abort_unless($codes->contains(fn ($code) => in_array($code, ['ceo', 'director'], true)), 403);
    }
}
