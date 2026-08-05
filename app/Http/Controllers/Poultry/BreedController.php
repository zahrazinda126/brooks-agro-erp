<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Http\Requests\BreedRequest;
use App\Models\AuditLog;
use App\Models\Breed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
 | Breeds are reference data the farm maintains itself.
 |
 | The nine seeded breeds cover what is common locally, but a delivery
 | note may name something else and carry its own figures. Rather than
 | leaving those batches with no timetable, whoever receives the birds
 | adds the breed once — and every future consignment of it is covered.
 */
class BreedController extends Controller
{
    public function index(Request $request): View
    {
        $breeds = Breed::poultry()
            ->orderBy('purpose')
            ->orderBy('name')
            ->withCount('batches')
            ->get()
            ->groupBy('purpose');

        return view('poultry.breeds.index', ['groups' => $breeds]);
    }

    public function create(): View
    {
        return view('poultry.breeds.create');
    }

    public function store(BreedRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code']    = \Illuminate\Support\Str::slug($data['name']);
        $data['species'] = 'poultry';

        // A slug collision means the same name twice.
        if (Breed::where('code', $data['code'])->exists()) {
            $data['code'] .= '-' . now()->format('His');
        }

        $breed = Breed::create($data);

        AuditLog::record('created', $breed, "Added breed {$breed->name}.",
            null, $breed->only(['name', 'lay_start_week', 'peak_week']), 'breeds');

        return redirect()
            ->route('breeds.index')
            ->with('status', "{$breed->name} added. It can now be chosen on a batch.");
    }

    public function edit(Breed $breed): View
    {
        return view('poultry.breeds.edit', ['breed' => $breed]);
    }

    public function update(BreedRequest $request, Breed $breed): RedirectResponse
    {
        $before = $breed->only(array_keys($request->validated()));

        $breed->update($request->validated());

        AuditLog::record('updated', $breed, "Updated breed {$breed->name}.",
            $before, $request->validated(), 'breeds');

        return redirect()
            ->route('breeds.index')
            ->with('status', 'Changes saved. Existing batches keep their recorded dates.');
    }

    /**
     * Breeds are switched off, never deleted — batches point at them and
     * removing one would leave those batches with no timetable and no
     * explanation of what the birds were.
     */
    public function toggle(Request $request, Breed $breed): RedirectResponse
    {
        abort_unless($request->user()->canDo('breeds', 'edit'), 403);

        $breed->update(['is_active' => ! $breed->is_active]);

        $state = $breed->is_active ? 'available again' : 'hidden';

        AuditLog::record('updated', $breed, "Breed {$breed->name} {$state}.",
            null, null, 'breeds');

        return redirect()
            ->route('breeds.index')
            ->with('status', "{$breed->name} is {$state}.");
    }
}
