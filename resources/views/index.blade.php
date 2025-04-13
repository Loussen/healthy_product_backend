@extends('layouts.app')

@section('content')
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1>{{ __('messages.slogan') }}</h1>
                    <p class="lead">{{ __('messages.slogan_text') }}</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="">
                            <img src="{{ asset('assets/images/playstore.png') }}" alt="Play Store Vital Scan VScan" class="playstore">
                        </a>
                        <a href="#" class="">
                            <img src="{{ asset('assets/images/appstore.png') }}" alt="App Store Vital Scan VScan" class="appstore">
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 camera-content-div">
                    <img src="{{ asset('assets/images/screen_camera-portrait.png') }}" alt="VScan Vital Scan" class="camera-content">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2>{{ __('messages.why_vital_scan') }}</h2>
                <p class="lead">{{ __('messages.why_vital_scan_text') }}</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-wrapper bg-primary-soft">
                            <i class="bi bi-camera text-primary"></i>
                        </div>
                        <h3>{{ __('messages.fast_scan') }}</h3>
                        <p>{{ __('messages.fast_scan_text') }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-wrapper bg-success-soft">
                            <i class="bi bi-graph-up text-success"></i>
                        </div>
                        <h3>{{ __('messages.health_score') }}</h3>
                        <p>{{ __('messages.health_score_text') }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-wrapper bg-warning-soft">
                            <i class="bi bi-bookmark-heart text-warning"></i>
                        </div>
                        <h3>{{ __('messages.personalization') }}</h3>
                        <p>{{ __('messages.personalization_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="text-center mb-5">
                <h2>{{ __('messages.how_it_works') }}</h2>
                <p class="lead">{{ __('messages.how_it_works_text') }}</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>{{ __('messages.scan_the_product') }}</h4>
                        <p>{{ __('messages.scan_the_product_text') }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>{{ __('messages.check_the_analysis') }}</h4>
                        <p>{{ __('messages.check_the_analysis_text') }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>{{ __('messages.decide') }}</h4>
                        <p>{{ __('messages.decide_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Download Section -->
    <section id="download" class="download">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2>{{ __('messages.download_now') }}</h2>
                    <p class="lead">{{ __('messages.download_now_text') }}</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="store-badge">
                            <img src="{{ asset('assets/images/playstore.png') }}" alt="App Store Vital Scan VScan">
                        </a>
                        <a href="#" class="store-badge">
                            <img src="{{ asset('assets/images/appstore.png') }}" alt="Google Play Vital Scan VScan">
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="download-image">
                        <img src="{{ asset('assets/images/splash_screen-left.png') }}" alt="App Screenshots Vital Scan VScan" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection


