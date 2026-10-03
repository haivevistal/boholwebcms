<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ config('cms.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @viteReactRefresh
    @vite(['resources/js/admin.jsx'])
    @inertiaHead
    {{-- Plugin admin styles/scripts (admin_enqueue_scripts) & admin_head --}}
    @if (cms_installed() && auth()->check())
        {!! cms_head('admin') !!}
    @endif
</head>
<body class="font-sans antialiased bg-slate-100 text-slate-900">
    @inertia
    @if (cms_installed() && auth()->check())
        {!! cms_footer('admin') !!}
    @endif
</body>
</html>
