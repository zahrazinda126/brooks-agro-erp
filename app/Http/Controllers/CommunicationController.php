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
        abort_unless(Communication::visibleTo($request->user())->whereKey($communication->id)->exists(), 403);
        if ($communication->recipient_id === $request->user()->id) $communication->update(['read_at' => now()]);
        $text=mb_strtolower($communication->subject.' '.$communication->body);
        $target=$communication->action_url ?: (str_contains($text,'directive') ? route('directives.index',absolute:false) : (str_contains($text,'approval')||str_contains($text,'awaiting your review') ? route('approvals.index',absolute:false) : (str_contains($text,'payroll') ? route('payroll.index',absolute:false) : route('communications.index',absolute:false))));
        return redirect($target);
    }
}
