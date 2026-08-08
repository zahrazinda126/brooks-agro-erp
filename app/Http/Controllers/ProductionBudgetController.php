<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionBudgetController extends Controller
{
    private const CATEGORIES = [
        'Poultry',
        'Goat Unit',
        'Rabbits Unit',
        'Dogs Unit',
        'Kituntu Garden',
    ];

    private const UNITS = [
        'poultry' => 'Poultry',
        'goats' => 'Goat Unit',
        'rabbits' => 'Rabbits Unit',
        'dogs' => 'Dogs Unit',
        'garden' => 'Kituntu Garden',
    ];

    public function financeDraft(Request $request): View
    {
        abort_unless($request->user()->canAccess('budget'), 403);

        $unit = $request->string('unit')->toString() ?: 'poultry';
        abort_unless(isset(self::UNITS[$unit]), 404);
        $start = $request->date('week')?->startOfWeek() ?? now()->startOfWeek();
        $lines = $this->unitBudgetQuery($unit)
            ->whereDate('period_start', $start->toDateString())
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        return view('production.budgets.finance-draft', [
            'lines' => $lines,
            'start' => $start,
            'end' => $start->copy()->endOfWeek(),
            'locked' => Budget::whereDate('period_start', $start->toDateString())->where('status', '!=', 'draft')->exists(),
            'units' => self::UNITS,
            'unit' => $unit,
            'category' => self::UNITS[$unit],
        ]);
    }

    public function addFinanceDraftLine(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'create') || $request->user()->canDo('budget', 'edit'), 403);

        $data = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'item' => ['required', 'string', 'max:180'],
            'unit' => ['nullable', 'string', 'max:40'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $start = now()->startOfWeek();
        abort_if(
            Budget::whereDate('period_start', $start->toDateString())->where('status', '!=', 'draft')->exists(),
            422,
            'This weekly budget has already entered approval and is locked.'
        );

        Budget::create($data + [
            'planned_amount' => $data['quantity'] * $data['unit_price'],
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfWeek()->toDateString(),
            'status' => 'draft',
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Production budget line added to the Finance draft.');
    }

    public function updateFinanceDraftLine(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'edit'), 403);
        abort_unless($budget->status === 'draft' && $this->isProductionCategory($budget->category), 403);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $budget->update($data + ['planned_amount' => $data['quantity'] * $data['unit_price']]);

        return back()->with('status', 'Production budget line updated.');
    }

    public function deleteFinanceDraftLine(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'edit'), 403);
        abort_unless($budget->status === 'draft' && $this->isProductionCategory($budget->category), 403);

        $budget->delete();

        return back()->with('status', 'Production budget line removed from the Finance draft.');
    }

    private function unitBudgetQuery(string $unit)
    {
        $words = match ($unit) {
            'poultry' => ['poultry'],
            'goats' => ['goat'],
            'rabbits' => ['rabbit'],
            'dogs' => ['dog'],
            'garden' => ['garden', 'kituntu'],
        };

        return Budget::query()->where(function ($query) use ($words) {
            foreach ($words as $word) {
                $query->orWhereRaw('lower(category) like ?', ['%'.$word.'%']);
            }
        });
    }

    private function isProductionCategory(string $category): bool
    {
        $value = strtolower($category);

        return collect(['poultry', 'goat', 'rabbit', 'dog', 'garden', 'kituntu'])
            ->contains(fn (string $word) => str_contains($value, $word));
    }
}
