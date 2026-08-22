<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['account' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $request->user();
        $before = $account->only(['name', 'username', 'email']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($account->id)],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users')->ignore($account->id)],
        ], [
            'username.regex' => 'The username may only contain letters, numbers, dots, underscores and hyphens.',
        ]);

        $data['email'] = $data['email'] ?: null;
        $account->update($data);

        AuditLog::record('account_details_updated', $account, 'Account details updated.', $before, $account->only(['name', 'username', 'email']), 'account');

        return back()->with('status', 'Your account details have been updated.');
    }
}
