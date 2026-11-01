<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fbf1ec">
    <title>Sign in · Brooks Agro</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/farmhub.css') }}?v={{ filemtime(public_path('css/farmhub.css')) }}">
</head>
<body>
    <main class="auth">
        <div class="auth-card">

            <div class="auth-mark">
                <img class="auth-logo" src="{{ asset('images/logo.png') }}" alt="Brooks Agro Farm logo">
                <h1>Brooks Agro</h1>
                <p>Farm Management</p>
            </div>

            @if (session('status'))
                <div class="notice notice--ok">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="field @error('username') field--error @enderror">
                    <label for="username">Username</label>
                    <input id="username"
                           name="username"
                           type="text"
                           value="{{ old('username') }}"
                           autocomplete="username"
                           autocapitalize="none"
                           autocorrect="off"
                           spellcheck="false"
                           required
                           autofocus>
                    @error('username')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field @error('password') field--error @enderror">
                    <label for="password">Password</label>
                    <input id="password"
                           name="password"
                           type="password"
                           autocomplete="current-password"
                           required>
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn">Sign in</button>
            </form>

            <p class="muted" style="margin:18px 0 0;text-align:center;font-size:.875rem;">
                Accounts are issued by HR. If you cannot sign in, speak to your supervisor.
            </p>
        </div>
    </main>
</body>
</html>
