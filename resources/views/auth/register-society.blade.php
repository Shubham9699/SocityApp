@extends('layouts.guest')

@section('title', 'Register a society')

@section('content')
<main class="auth-layout">
    <section class="auth-story">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark">SM</span>
            <span><strong>Society Manager</strong><small>COMMUNITY PORTAL</small></span>
        </a>
        <div class="story-copy">
            <span class="eyebrow">A workspace for your community</span>
            <h1>Bring your<br>society online.</h1>
            <p>Register your society and manage residents, maintenance, payments and requests in one place.</p>
        </div>
        <div class="story-foot"><span class="story-dot"></span> New societies are reviewed before access is enabled</div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-heading">
                <span class="brand-mark brand-mark-small">SM</span>
                <span class="eyebrow">SOCIETY REGISTRATION</span>
                <h2>Register your society</h2>
                <p>We'll create your society workspace and first administrator account.</p>
            </div>

            @if ($errors->any())
                <div class="form-errors" role="alert">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <form method="post" action="{{ route('society-register.store') }}" class="stack-form">
                @csrf
                <label for="society_name">Society name</label>
                <input id="society_name" name="society_name" type="text" value="{{ old('society_name') }}" maxlength="150" required>
                <label for="address">Society address (optional)</label>
                <textarea id="address" name="address" rows="2" maxlength="1000">{{ old('address') }}</textarea>
                <label for="admin_name">Administrator full name</label>
                <input id="admin_name" name="admin_name" type="text" value="{{ old('admin_name') }}" maxlength="100" required>
                <div class="form-grid">
                    <div><label for="register_email">Administrator email</label><input id="register_email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                    <div><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required></div>
                </div>
                <div class="form-grid">
                    <div><label for="register_password">Password</label><input id="register_password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
                    <div><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
                </div>
                <button class="button button-primary button-wide" type="submit">Submit for approval <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
        </div>
        <p class="auth-legal">Your workspace stays private to your society after approval.</p>
    </section>
</main>
@endsection
