<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('staff-performance'), 403);

        return redirect()->route('poultry.performance.index');
    }
}
