<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Society Manager') · Society Manager</title>
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
<body class="guest-page">
    <div class="guest-theme-toggle">@include('components.theme-toggle')</div>
    @if (session('success'))
        <div class="flash flash-success guest-flash">{{ session('success') }}</div>
    @endif
    @yield('content')
    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
