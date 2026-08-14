<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('messages'), 403);
        $messages = Communication::with(['sender', 'recipient'])
            ->visibleTo($request->user())->latest()->paginate(30);

        return view('communications.index', [
            'messages' => $messages,
            'canCompose' => $request->user()->canDo('messages', 'create'),
            'recipients' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
