@php($meta = $meta ?? [])
<!DOCTYPE html>
<html lang="{{ $meta['language'] ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ $meta['title'] ?? config('app.name') }}</title>
    @if (! empty($meta['description']))
        <meta name="description" content="{{ $meta['description'] }}">
        <meta property="og:description" content="{{ $meta['description'] }}">
    @endif
    @if (! empty($meta['canonical']))
        <link rel="canonical" href="{{ $meta['canonical'] }}">
        <meta property="og:url" content="{{ $meta['canonical'] }}">
    @endif
    @if (! empty($meta['robots']))
        <meta name="robots" content="{{ $meta['robots'] }}">
    @endif
    <meta property="og:title" content="{{ $meta['title'] ?? '' }}">
    <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
    <meta property="og:site_name" content="{{ $meta['site_name'] ?? '' }}">
    @if (! empty($meta['image']))
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @if (! empty($meta['icon']))
        <link rel="icon" href="{{ $meta['icon'] }}">
    @endif
    @viteReactRefresh
    @vite(['resources/js/front.jsx'])
    @inertiaHead
    {{-- Theme & plugin styles/scripts (enqueue_scripts) and the cms_head action --}}
    {!! cms_head('front') !!}
</head>
<body class="cms-body">
    @inertia
    {{-- Footer scripts: the theme bundle, plugin scripts, cms_footer action --}}
    {!! cms_footer('front') !!}
</body>
</html>
