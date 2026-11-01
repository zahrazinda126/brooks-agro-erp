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

        // Opening the list counts as reading: everything addressed to you is marked read, so the banner goes away.
        // The ones that were new stay highlighted on this visit.
        $newIds = Communication::where('recipient_id', $request->user()->id)->whereNull('read_at')->pluck('id')->all();
        if ($newIds) Communication::whereIn('id', $newIds)->update(['read_at' => now()]);

        return view('communications.index', [
            'messages' => $messages,
            'newIds' => $newIds,
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
