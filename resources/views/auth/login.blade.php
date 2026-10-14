@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
<main class="auth-layout">
    <section class="auth-story">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark">SM</span>
            <span><strong>Society Manager</strong><small>COMMUNITY PORTAL</small></span>
        </a>
        <div class="story-copy">
            <span class="eyebrow">A better way to live together</span>
            <h1>Your society,<br>in good hands.</h1>
            <p>One simple place for maintenance, community updates and resident support.</p>
        </div>
        <div class="story-foot"><span class="story-dot"></span> Secure resident and committee access</div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-heading">
                <span class="brand-mark brand-mark-small">SM</span>
                <span class="eyebrow">WELCOME BACK</span>
                <h2>Sign in to your account</h2>
                <p>Enter your details to continue to your society workspace.</p>
            </div>

            @if ($errors->any())
                <div class="form-errors" role="alert">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}" class="stack-form">
                @csrf
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="you@example.com" required autofocus>

                <div class="label-row"><label for="password">Password</label><a class="text-link" href="{{ route('password.request') }}">Forgot password?</a></div>
                <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>

                <label for="role">Sign in as</label>
                <select id="role" name="role" required>
                    <option value="resident" @selected(old('role', 'resident') === 'resident')>Resident</option>
                    <option value="admin" @selected(old('role') === 'admin')>Society administrator</option>
                    <option value="staff" @selected(old('role') === 'staff')>Maintenance staff</option>
                    <option value="super_admin" @selected(old('role') === 'super_admin')>Super admin</option>
                </select>

                <label class="check-line"><input type="checkbox" name="remember" value="1" checked> Keep me signed in</label>
                <button class="button button-primary button-wide" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>

            <div class="demo-box">
                <div class="demo-heading"><span>Try the demo</span><span class="demo-badge">DEMO</span></div>
                <p>All demo accounts use <code>Secret@123</code>.</p>
                <div class="demo-actions">
                    <button type="button" class="button button-soft demo-login" data-email="ramesh.patel@gmail.com" data-role="resident">Resident</button>
                    <button type="button" class="button button-soft demo-login" data-email="admin@societymanager.org" data-role="admin">Admin</button>
                    <button type="button" class="button button-soft demo-login" data-email="maintenance.lead@societymanager.org" data-role="staff">Staff</button>
                </div>
            </div>

            <p class="auth-switch">Resident? <a href="{{ route('register') }}">Create a resident account</a></p>
            <p class="auth-switch">Registering a community? <a href="{{ route('society-register') }}">Register your society</a></p>
        </div>
        <p class="auth-legal">By continuing, you agree to use the society portal responsibly.</p>
    </section>
</main>
@endsection
