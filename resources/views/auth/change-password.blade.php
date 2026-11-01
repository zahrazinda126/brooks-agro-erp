<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fbf1ec">
    <title>Choose a password · Brooks Agro</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/farmhub.css') }}?v={{ filemtime(public_path('css/farmhub.css')) }}">
</head>
<body>
    <main class="auth">
        <div class="auth-card">

            <div class="auth-mark">
                <h1>Choose a password</h1>
                <p>{{ auth()->user()->username }}</p>
            </div>

            <div class="notice">
                @if (auth()->user()->passwordHasExpired())
                    Your password has expired. Set a new one to continue.
                @else
                    This is your first sign-in. Set your own password before continuing.
                @endif
            </div>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')

                <div class="field @error('current_password') field--error @enderror">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password"
                           autocomplete="current-password" required autofocus>
                    @error('current_password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field @error('password') field--error @enderror">
                    <label for="password">New password</label>
                    <input id="password" name="password" type="password"
                           autocomplete="new-password" required>
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">New password again</label>
                    <input id="password_confirmation" name="password_confirmation"
                           type="password" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn">Save password</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" style="margin-top:14px;text-align:center;">
                @csrf
                <button type="submit" class="btn btn--quiet">Sign out instead</button>
            </form>
        </div>
    </main>
</body>
</html>
