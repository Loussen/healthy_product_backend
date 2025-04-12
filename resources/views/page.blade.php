<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Scan - Your vital scan compass</title>
    <meta name="description" content="Your vital scan compass">
    <meta name="keywords" content="vital scan, vital, scan, vscan, healthy product">
    <meta name="author" content="Fuad Hasanli">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://vitalscan.app">
    <meta property="og:title" content="Vital Scan - Your vital scan compass">
    <meta property="og:description" content="Bu, sosyal medyada gösterilecek kısa açıklamadır.">
    <meta property="og:image" content="https://seninsiten.com/images/og-image.jpg">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://vitalscan.app">
    <meta name="twitter:title" content="Vital Scan - Your vital scan compass">
    <meta name="twitter:description" content="Bu, Twitter’da gözükecek kısa açıklamadır.">
    <meta name="twitter:image" content="https://seninsiten.com/images/twitter-image.jpg">

    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">

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
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="{{ asset('assets/images/logo.png') }}" alt="VScan Vital Scan">
            <span class="brand-text d-sm-inline">Vital Scan</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#features">{{ __('messages.menus.top_menu.specifications') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#how-it-works">{{ __('messages.menus.top_menu.how_it_works') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#download">{{ __('messages.menus.top_menu.download') }}</a>
                </li>
                <li class="nav-item d-lg-none">
                    <a href="#download" class="btn btn-primary w-100">{{ __('messages.menus.top_menu.start_now') }}</a>
                </li>
            </ul>
            <div class="dropdown">
                <button class="btn dropdown-toggle d-flex align-items-center gap-2" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="{{ asset('assets/images/'.app()->getLocale().'.png') }}" width="24" alt="{{ app()->getLocale() }}"> <!-- Default selected -->
                </button>
                <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                    @foreach(config('services.locales') as $locale => $language)
                        <li>
                            @php
                                $params = ['locale' => $locale];

                                if (\Illuminate\Support\Facades\Route::currentRouteName() === 'page') {
                                    $params['slug'] = request()->route('slug');
                                } else {
                                    $params['id'] = request()->route('id');
                                }
                            @endphp
                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route(Route::currentRouteName(), $params) }}">
                                <img src="{{ asset('assets/images/'.$locale.'.png') }}" width="24" alt="{{ $locale }}">
                                {{ $language }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <a href="#download" class="btn btn-primary ms-lg-3 d-none d-lg-block">{{ __('messages.menus.top_menu.start_now') }}</a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>{{ __('messages.privacy_policy') }}</h1>
        <div class="row">
            {!! $page->content !!}
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <h5>Vital Scan</h5>
                <p>{{ __('messages.footer_slogan') }}</p>
            </div>
            <div class="col-lg-2">
                <h5>{{ __('messages.menus.footer_menu.product') }}</h5>
                <ul class="list-unstyled">
                    <li><a href="#features">{{ __('messages.menus.footer_menu.specifications') }}</a></li>
                    <li><a href="#how-it-works">{{ __('messages.menus.footer_menu.how_it_works') }}</a></li>
                    <li><a href="#download">{{ __('messages.menus.footer_menu.download') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>{{ __('messages.menus.footer_menu.company') }}</h5>
                <ul class="list-unstyled">
                    <li><a href="#">{{ __('messages.menus.footer_menu.about_us') }}</a></li>
                    <li><a href="#">{{ __('messages.menus.footer_menu.contact') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>{{ __('messages.menus.footer_menu.legal') }}</h5>
                <ul class="list-unstyled">
                    <li><a href="#">{{ __('messages.menus.footer_menu.privacy') }}</a></li>
                    <li><a href="#">{{ __('messages.menus.footer_menu.conditions') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>{{ __('messages.menus.footer_menu.social') }}</h5>
                <div class="social-links">
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-12 text-center">
                <p class="mb-0">&copy; 2025 Vital Scan. {{ __('messages.copywriter') }}</p>
            </div>
        </div>
    </div>
</footer>

<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
