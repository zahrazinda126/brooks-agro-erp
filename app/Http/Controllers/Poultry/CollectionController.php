<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\AnimalGroup;
use App\Models\EggCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/*
 | What was recorded, by whom, and when.
 |
 | The person and the exact moment were already being captured on every
 | record — automatically, from the signed-in account. They were simply
 | never shown to anyone. This is the screen that shows them.
 */
class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $from = $request->query('from', now()->subDays(7)->toDateString());
        $to   = $request->query('to', now()->toDateString());

        $query = EggCollection::with(['batch', 'lines.grade', 'recorder.employee'])
            ->visibleTo($user)
            ->whereBetween('collected_on', [$from, $to])
            ->orderByDesc('collected_on')
            ->orderByDesc('recorded_at');

        if ($batchId = $request->query('batch')) {
            $query->where('animal_group_id', $batchId);
        }

        $collections = $query->paginate(25)->withQueryString();

        // Totals across the whole range, not just this page.
        $all = EggCollection::visibleTo($user)
            ->whereBetween('collected_on', [$from, $to]);

        if ($batchId) {
            $all->where('animal_group_id', $batchId);
        }

        $rows = (clone $all)->get(['total_eggs', 'recorded_by', 'collected_on', 'collected_at', 'recorded_at']);

        /*
         | Entered more than six hours after the stated collection time.
         |
         | Not wrong in itself — signal is poor in the houses. But a record
         | typed late is a record written from memory, and memory rounds
         | numbers. Worth seeing.
         */
        $late = $rows->filter(function ($r) {
            if (! $r->collected_at) {
                return false;
            }

            $collected = Carbon::parse($r->collected_on->format('Y-m-d') . ' ' . $r->collected_at);

            return $collected->diffInHours($r->recorded_at, false) > 6;
        })->count();

        return view('poultry.collections.index', [
            'collections' => $collections,
            'batches'     => AnimalGroup::visibleTo($user)->orderBy('code')->get(),
            'filters'     => ['from' => $from, 'to' => $to, 'batch' => $batchId],
            'totals'      => [
                'eggs'    => $rows->sum('total_eggs'),
                'records' => $rows->count(),
                'people'  => $rows->pluck('recorded_by')->unique()->count(),
                'late'    => $late,
            ],
        ]);
    }
}
