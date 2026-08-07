<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Services\PoultryPerformanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('staff-performance'), 403);
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return view('poultry.performance.index', [
            'from' => $from, 'to' => $to,
            'rows' => PoultryPerformanceService::forPeriod($from, $to),
        ]);
    }
}
