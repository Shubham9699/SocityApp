@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
<main class="auth-layout">
    <section class="auth-story">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark">SM</span>
            <span><strong>Society Manager</strong><small>COMMUNITY PORTAL</small></span>
        </a>
        <div class="story-copy">
            <span class="eyebrow">ACCOUNT RECOVERY</span>
            <h1>Back to your<br>community.</h1>
            <p>We'll send a secure, time-limited reset link to the email address on your account.</p>
        </div>
        <div class="story-foot"><span class="story-dot"></span> Reset links are single-use and expire automatically</div>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-heading">
                <span class="brand-mark brand-mark-small">SM</span>
                <span class="eyebrow">PASSWORD HELP</span>
                <h2>Reset your password</h2>
                <p>Enter your account email address and we'll send a reset link.</p>
            </div>
            @if (session('status'))<div class="form-success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="form-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <form method="post" action="{{ route('password.email') }}" class="stack-form">
                @csrf
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                <button class="button button-primary button-wide" type="submit">Send reset link <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch"><a href="{{ route('login') }}">← Back to sign in</a></p>
        </div>
    </section>
</main>
@endsection
