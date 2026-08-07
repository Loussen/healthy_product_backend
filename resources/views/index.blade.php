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
                        <a target="_blank" href="https://play.google.com/store/apps/details?id=com.healthyproduct.app" class="">
                            <img src="{{ asset('assets/images/playstore.png') }}" alt="Download Vital Scan on Google Play Store" class="playstore">
                        </a>
                        <a href="{{ config('services.apple.app_store_url') }}" target="_blank" class="">
                            <img src="{{ asset('assets/images/appstore.png') }}" alt="Download Vital Scan on Apple App Store" class="appstore">
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 camera-content-div">
                    <div id="cameraCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="{{ asset('assets/images/home-page-portrait.png') }}" class="d-block w-100 camera-content" alt="Vital Scan App Home Screen - Product ingredient scanner">
                            </div>
                            <div class="carousel-item">
                                <img src="{{ asset('assets/images/screen_camera-portrait.png') }}" class="d-block w-100 camera-content" alt="Vital Scan Camera Scanner - Scan product labels instantly">
                            </div>
                            <div class="carousel-item">
                                <img src="{{ asset('assets/images/result-portrait.png') }}" class="d-block w-100 camera-content" alt="Vital Scan Analysis Result - Health score and ingredient analysis">
                            </div>
                            <div class="carousel-item">
                                <img src="{{ asset('assets/images/ingredients-portrait.png') }}" class="d-block w-100 camera-content" alt="Vital Scan Ingredients Detail - Detailed ingredient breakdown">
                            </div>
                            <div class="carousel-item">
                                <img src="{{ asset('assets/images/history-portrait.png') }}" class="d-block w-100 camera-content" alt="Vital Scan Scan History - Track your scanned products">
                            </div>
                            <div class="carousel-item">
                                <img src="{{ asset('assets/images/profile-portrait.png') }}" class="d-block w-100 camera-content" alt="Vital Scan User Profile - Personalized health preferences">
                            </div>
                            <div class="carousel-item">
                                <img src="{{ asset('assets/images/splash_screen-portrait.png') }}" class="d-block w-100 camera-content" alt="Vital Scan - Your vital scan compass for healthy living">
                            </div>
                        </div>

                        <!-- Controls -->
                        <button class="carousel-control-prev custom-carousel-control" type="button" data-bs-target="#cameraCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next custom-carousel-control" type="button" data-bs-target="#cameraCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>

                <style>
                    .custom-carousel-control .carousel-control-prev-icon,
                    .custom-carousel-control .carousel-control-next-icon {
                        background-color: #DFE1E5;
                        background-size: 100% 100%;
                        border-radius: 50%;
                        width: 3rem;
                        height: 3rem;
                    }
                </style>


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

    @php
        $latestArticles = \App\Models\Page::blogArticles()->orderByDesc('updated_at')->limit(3)->get();
    @endphp
    @if($latestArticles->isNotEmpty())
    <!-- Blog Section -->
    <section id="blog" class="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2>{{ __('messages.blog.home_section_title') }}</h2>
                <p class="lead">{{ __('messages.blog.home_section_text') }}</p>
            </div>
            <div class="row g-4">
                @foreach($latestArticles as $article)
                    <div class="col-md-4">
                        <article class="blog-card h-100">
                            <a href="{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}"
                               class="blog-card-cover-link">
                                <img src="{{ $article->getCoverImageUrl() }}"
                                     alt="{{ $article->title }}"
                                     class="blog-card-cover"
                                     width="640"
                                     height="360"
                                     loading="lazy"
                                     decoding="async">
                            </a>
                            <div class="blog-card-body">
                                <time class="blog-date" datetime="{{ $article->updated_at->toIso8601String() }}">
                                    {{ $article->updated_at->translatedFormat('F d, Y') }}
                                </time>
                                <h3 class="blog-card-title">
                                    <a href="{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}">
                                        {{ $article->title }}
                                    </a>
                                </h3>
                                <p class="blog-excerpt">{{ $article->getExcerpt(120) }}</p>
                                <a href="{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}"
                                   class="blog-read-more">
                                    {{ __('messages.blog.read_more') }} &rarr;
                                </a>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
            <div class="text-center mt-4">
                <a href="{{ route('blog.index', ['locale' => app()->getLocale()]) }}" class="btn btn-outline-primary">
                    {{ __('messages.blog.view_all') }}
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- FAQ Section -->
    <section id="faq" class="how-it-works">
        <div class="container">
            <div class="text-center mb-5">
                <h2>{{ __('messages.faq.title') }}</h2>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        @foreach(['q1', 'q2', 'q3', 'q4'] as $i => $key)
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="faqHeading{{ $i }}">
                                    <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }}" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#faqCollapse{{ $i }}"
                                            aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="faqCollapse{{ $i }}">
                                        {{ __('messages.faq.' . $key) }}
                                    </button>
                                </h3>
                                <div id="faqCollapse{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                                     aria-labelledby="faqHeading{{ $i }}" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        {{ __('messages.faq.a' . ($i + 1)) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
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
                        <a target="_blank" href="https://play.google.com/store/apps/details?id=com.healthyproduct.app" class="store-badge">
                            <img src="{{ asset('assets/images/playstore.png') }}" alt="Download Vital Scan on Google Play Store">
                        </a>
                        <a href="{{ config('services.apple.app_store_url') }}" target="_blank" class="store-badge">
                            <img src="{{ asset('assets/images/appstore.png') }}" alt="Download Vital Scan on Apple App Store">
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

@push('jsonld')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        @foreach(['q1', 'q2', 'q3', 'q4'] as $i => $key)
        {
            "@type": "Question",
            "name": "{{ addslashes(__('messages.faq.' . $key)) }}",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "{{ addslashes(__('messages.faq.a' . ($i + 1))) }}"
            }
        }@if($i < 3),@endif
        @endforeach
    ]
}
</script>
@endpush


