<?php

namespace App\Http\Controllers;

use App\Models\{AuditLog, Visitor};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('visitors'), 403);
        $search = $request->string('search')->trim()->toString();
        $visitors = Visitor::with(['recorder','checkoutRecorder'])
            ->when($request->input('status') === 'inside', fn($q) => $q->whereNull('checked_out_at'))
            ->when($search, fn($q) => $q->where(function($x) use ($search) {
                $term = '%'.strtolower($search).'%';
                $x->whereRaw('lower(visitor_name) like ?', [$term])
                    ->orWhereRaw('lower(organization) like ?', [$term])
                    ->orWhereRaw('lower(person_to_visit) like ?', [$term])
                    ->orWhereRaw('lower(vehicle_registration) like ?', [$term]);
            }))->latest('checked_in_at')->paginate(30)->withQueryString();
        return view('visitors.index', [
            'visitors'=>$visitors, 'search'=>$search,
            'inside'=>Visitor::whereNull('checked_out_at')->count(),
            'today'=>Visitor::whereDate('visit_date', today())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('visitors','create'), 403);
        $data = $request->validate([
            'visitor_name'=>['required','string','max:160'], 'phone'=>['nullable','string','max:40'],
            'organization'=>['nullable','string','max:160'], 'purpose'=>['required','string','max:250'],
            'person_to_visit'=>['required','string','max:160'], 'visit_date'=>['required','date'],
            'checked_in_at'=>['required','date'], 'vehicle_registration'=>['nullable','string','max:40'],
            'items_carried'=>['nullable','string','max:500'], 'pass_number'=>['nullable','string','max:60'],
            'notes'=>['nullable','string','max:2000'],
        ]);
        $visitor = Visitor::create($data + ['recorded_by'=>$request->user()->id]);
        AuditLog::record('visitor_checked_in', $visitor, $visitor->visitor_name.' checked in to visit '.$visitor->person_to_visit, null, $visitor->toArray(), 'visitors');
        return back()->with('status', 'Visitor checked in successfully.');
    }

    public function checkout(Request $request, Visitor $visitor): RedirectResponse
    {
        abort_unless($request->user()->canDo('visitors','edit'), 403);
        abort_if($visitor->checked_out_at, 422, 'This visitor has already checked out.');
        $data = $request->validate(['checked_out_at'=>['required','date','after_or_equal:'.$visitor->checked_in_at->format('Y-m-d H:i:s')]]);
        $visitor->update($data + ['checked_out_by'=>$request->user()->id]);
        AuditLog::record('visitor_checked_out', $visitor, $visitor->visitor_name.' checked out.', null, $data, 'visitors');
        return back()->with('status', 'Visitor checked out successfully.');
    }
}
