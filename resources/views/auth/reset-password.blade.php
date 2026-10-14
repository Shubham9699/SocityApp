@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
<main class="auth-layout">
    <section class="auth-story">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark">SM</span>
            <span><strong>Society Manager</strong><small>COMMUNITY PORTAL</small></span>
        </a>
        <div class="story-copy">
            <span class="eyebrow">SECURE YOUR ACCOUNT</span>
            <h1>Choose a new<br>password.</h1>
            <p>Use a strong password you don't use for other accounts.</p>
        </div>
        <div class="story-foot"><span class="story-dot"></span> Your new password is stored securely</div>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-heading">
                <span class="brand-mark brand-mark-small">SM</span>
                <span class="eyebrow">PASSWORD RECOVERY</span>
                <h2>Set a new password</h2>
                <p>Enter your email address and choose a new password.</p>
            </div>
            @if ($errors->any())<div class="form-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <form method="post" action="{{ route('password.update') }}" class="stack-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" autocomplete="email" required autofocus>
                <label for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                <button class="button button-primary button-wide" type="submit">Update password <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch"><a href="{{ route('login') }}">← Back to sign in</a></p>
        </div>
    </section>
</main>
@endsection
