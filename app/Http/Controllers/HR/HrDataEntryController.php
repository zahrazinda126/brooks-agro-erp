<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrDataEntryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('hr'), 403);

        return view('hr.data-entry', [
            'canManage' => $request->user()->canDo('hr', 'create') || $request->user()->canDo('hr', 'edit'),
            'canRecordFleet' => $request->user()->canDo('fleet', 'create') || $request->user()->canDo('fleet', 'edit'),
        ]);
    }
}
