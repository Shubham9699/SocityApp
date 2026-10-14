@extends('layouts.guest')

@section('title', 'Create resident account')

@section('content')
<main class="auth-layout">
    <section class="auth-story">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark">SM</span>
            <span><strong>Society Manager</strong><small>COMMUNITY PORTAL</small></span>
        </a>
        <div class="story-copy">
            <span class="eyebrow">Welcome to the neighborhood</span>
            <h1>Make yourself<br>at home.</h1>
            <p>Create a resident account to follow society notices, bills and maintenance requests.</p>
        </div>
        <div class="story-foot"><span class="story-dot"></span> Your account is protected by Laravel authentication</div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="auth-heading">
                <span class="brand-mark brand-mark-small">SM</span>
                <span class="eyebrow">RESIDENT REGISTRATION</span>
                <h2>Create your account</h2>
                <p>Register your contact details and flat to get started.</p>
            </div>

            @if ($errors->any())
                <div class="form-errors" role="alert">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <form method="post" action="{{ route('register.store') }}" class="stack-form">
                @csrf
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" autocomplete="name" maxlength="100" required>

                <div class="form-grid">
                    <div><label for="register_email">Email address</label><input id="register_email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                    <div><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required></div>
                </div>
                <label for="society_id">Society</label>
                <select id="society_id" name="society_id" required>
                    <option value="">Choose your society</option>
                    @foreach ($societies as $society)
                        <option value="{{ $society->id }}" @selected((string) old('society_id') === (string) $society->id)>{{ $society->name }}</option>
                    @endforeach
                </select>

                <div class="form-grid form-grid-flat">
                    <div><label for="wing">Wing</label><input id="wing" name="wing" type="text" value="{{ old('wing', 'A') }}" maxlength="10" required></div>
                    <div><label for="flat_number">Flat number</label><input id="flat_number" name="flat_number" type="text" value="{{ old('flat_number') }}" maxlength="20" placeholder="402" required></div>
                    <div><label for="occupancy_status">Occupancy</label><select id="occupancy_status" name="occupancy_status"><option value="owner_occupied">Owner</option><option value="tenant_occupied" @selected(old('occupancy_status') === 'tenant_occupied')>Tenant</option></select></div>
                </div>

                <div class="form-grid">
                    <div><label for="register_password">Password</label><input id="register_password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
                    <div><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
                </div>

                <button class="button button-primary button-wide" type="submit">Create resident account <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
        </div>
        <p class="auth-legal">Registration is subject to the society's flat ownership records.</p>
    </section>
</main>
@endsection
