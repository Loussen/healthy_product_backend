@php
    $baseUrl = rtrim(config('app.url'), '/');
    $currentUrl = url()->current();
    $currentPath = request()->path();
    $pathSegments = ($currentPath && $currentPath !== '/') ? explode('/', $currentPath) : [];
    $locales = config('services.locales', []);
    $slugPart = (count($pathSegments) > 1) ? '/' . implode('/', array_slice($pathSegments, 1)) : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Scan - Your vital scan compass @yield('title', '')</title>
    <meta name="description" content="@yield('meta_description', __('messages.meta.description'))">
    <meta name="keywords" content="{{ __('messages.meta.keywords') }}">
    <meta name="author" content="Fuad Hasanli">

    {{-- Apple Smart App Banner (iOS Safari) - increases App Store impressions --}}
    <meta name="apple-itunes-app" content="app-id={{ config('services.apple.app_store_id', '6755874667') }}">

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ $currentUrl }}">

    {{-- Hreflang - multi language support for SEO --}}
    @foreach($locales as $locale => $language)
        <link rel="alternate" hreflang="{{ str_replace('_', '-', $locale) }}" href="{{ $baseUrl }}/{{ $locale }}{{ $slugPart }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $baseUrl }}/{{ config('services.default_locale', 'az') }}{{ $slugPart }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $currentUrl }}">
    <meta property="og:title" content="Vital Scan - Your vital scan compass @yield('title', '')">
    <meta property="og:description" content="@yield('meta_description', __('messages.meta.description'))">
    <meta property="og:image" content="{{ asset('assets/images/graphic.jpeg') }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    <meta property="og:site_name" content="Vital Scan">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $currentUrl }}">
    <meta name="twitter:title" content="Vital Scan - Your vital scan compass @yield('title', '')">
    <meta name="twitter:description" content="@yield('meta_description', __('messages.meta.description'))">
    <meta name="twitter:image" content="{{ asset('assets/images/graphic.jpeg') }}">

    @stack('meta')

    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">

    <link rel="shortcut icon" href="{{ asset('assets/favicon/favicon-96x96.png') }}">

    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('assets/favicon/apple-icon-57x57.png') }}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('assets/favicon/apple-icon-60x60.png') }}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('assets/favicon/apple-icon-72x72.png') }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('assets/apple-icon-76x76.png') }}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('assets/favicon/apple-icon-114x114.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('assets/favicon/apple-icon-120x120.png') }}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('assets/favicon/apple-icon-144x144.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('assets/favicon/apple-icon-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/favicon/apple-icon-180x180.png') }}">
    <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('assets/favicon/android-icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('assets/favicon/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('assets/favicon/manifest.json') }}">

    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="{{ asset('assets/favicon/ms-icon-144x144.png') }}">
    <meta name="theme-color" content="#ffffff">

    {{-- JSON-LD Structured Data: Organization, WebSite, SoftwareApplication (iOS + Android) --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Organization",
                "@id": "{{ $baseUrl }}/#organization",
                "name": "Vital Scan",
                "url": "{{ $baseUrl }}",
                "logo": "{{ asset('assets/images/logo_new.png') }}",
                "description": "{{ addslashes(__('messages.meta.description')) }}"
            },
            {
                "@type": "WebSite",
                "name": "Vital Scan",
                "url": "{{ $baseUrl }}",
                "description": "{{ addslashes(__('messages.meta.description')) }}",
                "publisher": { "@id": "{{ $baseUrl }}/#organization" }
            },
            {
                "@type": "SoftwareApplication",
                "name": "Vital Scan - Product Scanner",
                "applicationCategory": "HealthApplication",
                "operatingSystem": "iOS",
                "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
                "description": "{{ addslashes(__('messages.meta.description')) }}",
                "downloadUrl": "{{ config('services.apple.app_store_url', 'https://apps.apple.com/us/app/vital-scan/id6755874667') }}"
            },
            {
                "@type": "SoftwareApplication",
                "name": "Vital Scan - Product Scanner",
                "applicationCategory": "HealthApplication",
                "operatingSystem": "Android",
                "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
                "description": "{{ addslashes(__('messages.meta.description')) }}",
                "downloadUrl": "{{ config('services.play_store_url', 'https://play.google.com/store/apps/details?id=com.healthyproduct.app') }}"
            }
        ]
    }
    </script>
    @stack('jsonld')
</head>
<body>

    @include('components.header')

    @yield('content')

    @include('components.footer')

    @stack('scripts')

</body>
</html>
