<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $messages = Communication::with(['sender', 'recipient'])
            ->visibleTo($request->user())->latest()->paginate(30);

        return view('communications.index', [
            'messages' => $messages,
            'canCompose' => $request->user()->canDo('messages', 'create'),
            'recipients' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function markRead(Request $request, Communication $communication): RedirectResponse
    {
        abort_unless($communication->recipient_id === $request->user()->id, 403);
        $communication->update(['read_at' => now()]);

        return $communication->action_url ? redirect($communication->action_url) : back();
    }
}
