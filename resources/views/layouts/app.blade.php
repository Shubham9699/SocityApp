<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Society dashboard') · Lotus Orchid</title>
    <script>
        (function () {
            var theme = localStorage.getItem('lotus-orchid-theme');
            if (theme === 'dark' || theme === 'light') {
                document.documentElement.dataset.theme = theme;
            }
        }());
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="app-page">
    <div class="app-frame">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand-mark">{{ $user->role === 'super_admin' ? 'SA' : 'LO' }}</span>
                <span><strong>{{ $user->role === 'super_admin' ? ($activeSociety?->name ?? 'Platform Admin') : ($activeSociety?->name ?? 'Society Portal') }}</strong><small>{{ $user->role === 'super_admin' ? 'SUPER ADMINISTRATION' : 'SOCIETY PORTAL' }}</small></span>
            </a>
            <div class="side-label">WORKSPACE</div>
            <nav class="side-nav" aria-label="Main navigation">
                @if ($user->role === 'super_admin')
                    @foreach ([
                        'societies' => ['♧', 'Societies'],
                        'activity' => ['⌁', 'Activity log'],
                    ] as $key => [$icon, $label])
                        <a class="nav-link {{ $tab === $key ? 'is-active' : '' }}" href="{{ route('dashboard', ['tab' => $key]) }}">
                            <span class="nav-icon" aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span>
                            @if ($key === 'societies' && !empty($metrics['pending_societies']))<span class="nav-count">{{ $metrics['pending_societies'] }}</span>@endif
                        </a>
                    @endforeach
                    @if ($activeSociety)
                        <div class="side-label">SOCIETY WORKSPACE</div>
                        @foreach ([
                            'dashboard' => ['⌂', 'Overview'],
                            'billing' => ['₹', 'Billing'],
                            'complaints' => ['⌁', 'Helpdesk'],
                            'notices' => ['◉', 'Notice board'],
                            'directory' => ['♧', 'Directory'],
                        ] as $key => [$icon, $label])
                            <a class="nav-link {{ $tab === $key ? 'is-active' : '' }}" href="{{ route('dashboard', ['tab' => $key]) }}">
                                <span class="nav-icon" aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span>
                                @if ($key === 'complaints' && !empty($metrics['open_complaints']))<span class="nav-count">{{ $metrics['open_complaints'] }}</span>@endif
                            </a>
                        @endforeach
                        <form method="post" action="{{ route('super-admin.societies.leave') }}" class="leave-society-form">@csrf @method('DELETE')
                            <button class="nav-link" type="submit"><span class="nav-icon" aria-hidden="true">←</span><span>Leave workspace</span></button>
                        </form>
                    @endif
                @else
                @foreach ([
                    'dashboard' => ['⌂', 'Overview'],
                    'billing' => ['₹', 'Billing'],
                    'complaints' => ['⌁', 'Helpdesk'],
                    'notices' => ['◉', 'Notice board'],
                    'directory' => ['♧', 'Directory'],
                    'architecture' => ['⌘', 'About this app'],
                ] as $key => [$icon, $label])
                    <a class="nav-link {{ $tab === $key ? 'is-active' : '' }}" href="{{ route('dashboard', ['tab' => $key]) }}">
                        <span class="nav-icon" aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span>
                        @if ($key === 'complaints' && $metrics['open_complaints'] > 0)<span class="nav-count">{{ $metrics['open_complaints'] }}</span>@endif
                    </a>
                @endforeach
                @endif
            </nav>
            <div class="sidebar-spacer"></div>
            <div class="sidebar-help"><span class="help-icon">?</span><strong>Need a hand?</strong><p>Contact your society office for help with your account.</p></div>
            <div class="profile-card">
                <span class="avatar">{{ mb_substr($user->full_name, 0, 1) }}</span>
                <span class="profile-copy"><strong>{{ $user->full_name }}</strong><small>{{ ucfirst($user->role) }}@if ($flat) · {{ $flat->wing }}-{{ $flat->flat_number }}@endif</small></span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="logout-button" aria-label="Sign out" title="Sign out">↗</button></form>
            </div>
        </aside>

        <div class="main-column">
            <header class="topbar">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">☰</button>
                <div class="breadcrumb"><span>{{ $activeSociety?->name ?? ($user->role === 'super_admin' ? 'Platform' : 'Society Portal') }}</span><span class="crumb-separator">/</span><strong>{{ ucfirst($tab === 'dashboard' ? 'Overview' : $tab) }}</strong></div>
                <div class="topbar-right">
                    <span class="status-pill"><span></span> {{ $user->role === 'super_admin' ? 'Platform' : 'Society portal' }}</span>
                    @include('components.theme-toggle')
                    <span class="top-avatar">{{ mb_substr($user->full_name, 0, 1) }}</span>
                </div>
            </header>

            <main class="page-content">
                @if (session('success'))
                    <div class="flash flash-success" role="status"><span>✓</span>{{ session('success') }}<button type="button" class="flash-close" aria-label="Dismiss">×</button></div>
                @endif
                @if ($errors->any())
                    <div class="flash flash-error" role="alert"><span>!</span><div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div><button type="button" class="flash-close" aria-label="Dismiss">×</button></div>
                @endif
                @yield('content')
                <footer class="page-footer"><span>{{ $activeSociety?->name ?? $user->society?->name ?? 'Society Manager' }}</span><span>Society management portal</span></footer>
            </main>
        </div>
    </div>
    <div class="sidebar-scrim" id="sidebarScrim"></div>
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
