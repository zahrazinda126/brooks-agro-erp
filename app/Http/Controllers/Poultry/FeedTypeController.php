<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FeedType;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 | Feed types are the farm's own list.
 |
 | Suppliers change, prices change, and every farm mixes differently. A list
 | written into a template means a developer is needed every time the store
 | buys something new — which in practice means people record the nearest
 | wrong option instead.
 */
class FeedTypeController extends Controller
{
    public function index(): View
    {
        return view('poultry.feed-types.index', [
            'groups' => FeedType::orderBy('sort_order')->get()->groupBy('category'),
        ]);
    }

    public function create(): View
    {
        return view('poultry.feed-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('feed-types', 'create'), 403);

        $data = $this->validated($request);

        $data['code'] = Str::slug($data['name']);

        if (FeedType::where('code', $data['code'])->exists()) {
            $data['code'] .= '-' . now()->format('His');
        }

        $feed = FeedType::create($data);
        $this->syncInventoryItem($feed, $request->user()->id);

        AuditLog::record('created', $feed, "Added feed type {$feed->name}.",
            null, null, 'feed-types');

        return redirect()
            ->route('feed-types.index')
            ->with('status', "{$feed->name} added. Workers can now record it.");
    }

    public function edit(FeedType $feedType): View
    {
        return view('poultry.feed-types.edit', ['feed' => $feedType]);
    }

    public function update(Request $request, FeedType $feedType): RedirectResponse
    {
        abort_unless($request->user()->canDo('feed-types', 'edit'), 403);

        $before = $feedType->only(['name', 'category', 'default_kg_per_bag', 'cost_per_kg']);

        $feedType->update($this->validated($request));
        $this->syncInventoryItem($feedType, $request->user()->id);

        AuditLog::record('updated', $feedType, "Updated feed type {$feedType->name}.",
            $before, null, 'feed-types');

        return redirect()->route('feed-types.index')->with('status', 'Changes saved.');
    }

    /**
     * Hidden, never deleted — past records point at it, and removing one
     * would leave months of feed history with no name against it.
     */
    public function toggle(Request $request, FeedType $feedType): RedirectResponse
    {
        abort_unless($request->user()->canDo('feed-types', 'edit'), 403);

        $feedType->update(['is_active' => ! $feedType->is_active]);

        $state = $feedType->is_active ? 'available again' : 'hidden';

        AuditLog::record('updated', $feedType, "Feed type {$feedType->name} {$state}.",
            null, null, 'feed-types');

        return redirect()->route('feed-types.index')
            ->with('status', "{$feedType->name} is {$state}.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(['complete', 'energy', 'protein', 'mineral', 'supplement'])],
            'from_week' => ['nullable', 'integer', 'min:0', 'max:100'],
            'to_week'   => ['nullable', 'integer', 'min:0', 'max:150', 'gte:from_week'],
            'default_kg_per_bag' => ['required', 'integer', 'min:1', 'max:200'],
            'cost_per_kg' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'note'        => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'to_week.gte' => 'The end week cannot be before the start week.',
        ]);
    }

    private function syncInventoryItem(FeedType $feed, int $userId): void
    {
        $store = InventoryStore::where('code', 'feed')->first();
        if (! $store) return;

        InventoryItem::updateOrCreate(
            ['inventory_store_id'=>$store->id, 'code'=>'FEED-'.strtoupper($feed->code)],
            ['name'=>$feed->name, 'category'=>'feed', 'unit'=>'kg', 'reorder_level'=>0, 'default_location'=>$store->name, 'notes'=>'Linked to the registered feed type.', 'is_active'=>$feed->is_active, 'created_by'=>$userId]
        );
    }
}
