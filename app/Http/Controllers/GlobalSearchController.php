<?php

namespace App\Http\Controllers;

use App\Models\AnimalGroup;
use App\Models\FarmTask;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SalesCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        abort_if(mb_strlen($query) < 2, 422, 'Enter at least two characters to search.');
        $user = $request->user();
        $like = '%'.mb_strtolower($query).'%';
        $results = collect();

        foreach ($user->sidebarModules()->flatten(1) as $module) {
            if (str_contains(mb_strtolower($module->name.' '.$module->key), mb_strtolower($query)) && $module->url()) {
                $results->push(['type'=>'Page', 'title'=>$module->name, 'detail'=>'Open application page', 'url'=>$module->url()]);
            }
        }

        if ($user->canAccess('poultry') || $user->canAccess('batches') || $user->canViewAllReports()) {
            AnimalGroup::query()->where(fn ($q) => $q->whereRaw('lower(code) like ?', [$like])->orWhereRaw('lower(coalesce(name, \'\')) like ?', [$like])->orWhereRaw('lower(coalesce(house, \'\')) like ?', [$like]))->limit(15)->get()->each(fn ($batch) => $results->push(['type'=>'Poultry batch', 'title'=>$batch->label(), 'detail'=>number_format($batch->current_quantity).' birds · '.str($batch->status)->headline(), 'url'=>route('batches.show', $batch)]));
        }

        foreach (['goats', 'rabbits', 'dogs'] as $unit) {
            if (! $user->canAccessFarmUnit($unit) && ! $user->canViewAllReports()) continue;
            DB::table('livestock_groups')->where('unit_code', $unit)->where(fn ($q) => $q->whereRaw('lower(group_code) like ?', [$like])->orWhereRaw('lower(coalesce(breed, \'\')) like ?', [$like]))->limit(10)->get()->each(fn ($group) => $results->push(['type'=>str($unit)->singular()->title().' group', 'title'=>$group->group_code, 'detail'=>($group->breed ?: 'Breed not recorded').' · '.number_format($group->current_quantity).' animals', 'url'=>route('operations.livestock.section', ['unit'=>$unit, 'section'=>'register'])]));
        }

        if ($user->canAccess('sales')) {
            SaleProduct::whereRaw('lower(name) like ?', [$like])->limit(10)->get()->each(fn ($product) => $results->push(['type'=>'Sale product', 'title'=>$product->name, 'detail'=>str($product->category)->headline().' · '.$product->unit, 'url'=>route('sales.index')]));
            SalesCustomer::whereRaw('lower(name) like ?', [$like])->limit(10)->get()->each(fn ($customer) => $results->push(['type'=>'Customer', 'title'=>$customer->name, 'detail'=>$customer->phone ?: 'Customer account', 'url'=>route('sales.index')]));
            Sale::where(fn ($q) => $q->whereRaw('lower(invoice_number) like ?', [$like])->orWhereRaw('lower(customer_name) like ?', [$like]))->limit(15)->get()->each(fn ($sale) => $results->push(['type'=>'Sale', 'title'=>$sale->invoice_number, 'detail'=>$sale->customer_name.' · UGX '.number_format($sale->total), 'url'=>route('sales.show', $sale)]));
        }

        FarmTask::query()->where(fn ($q) => $q->where('worker_id', $user->id)->orWhereHas('assignment', fn ($assignment) => $assignment->where('supervisor_id', $user->id)))->where(fn ($q) => $q->whereRaw('lower(title) like ?', [$like])->orWhereRaw('lower(coalesce(instructions, \'\')) like ?', [$like]))->limit(15)->get()->each(fn ($task) => $results->push(['type'=>'Task', 'title'=>$task->title, 'detail'=>str($task->unit_code)->headline().' · '.str($task->status)->headline(), 'url'=>route('supervision.index')]));

        return view('search.index', ['query'=>$query, 'results'=>$results->unique(fn ($item) => $item['type'].'|'.$item['title'].'|'.$item['url'])->values()]);
    }
}
