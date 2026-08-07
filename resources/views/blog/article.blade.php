@extends('layouts.app')

@section('title', ' - ' . $article->title)
@section('meta_description', $article->getExcerpt(160))
@section('og_image', $article->getCoverImageUrl())

@push('meta')
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="{{ $article->created_at->toIso8601String() }}">
    <meta property="article:modified_time" content="{{ $article->updated_at->toIso8601String() }}">
    <meta property="article:author" content="Vital Scan">
@endpush

@section('content')
    <section class="hero blog-article">
        <div class="container">
            <div class="row mt-5">
                <div class="col-lg-8 mx-auto">
                    <nav aria-label="breadcrumb" class="blog-breadcrumb mb-4">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="{{ route('home', ['locale' => app()->getLocale()]) }}">Vital Scan</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('blog.index', ['locale' => app()->getLocale()]) }}">{{ __('messages.blog.title') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $article->title }}</li>
                        </ol>
                    </nav>

                    <article itemscope itemtype="https://schema.org/Article">
                        <header class="mb-4">
                            <h1 itemprop="headline">{{ $article->title }}</h1>
                            <div class="blog-meta">
                                <time itemprop="dateModified" datetime="{{ $article->updated_at->toIso8601String() }}">
                                    {{ __('messages.last_updated') }}:
                                    {{ $article->updated_at->translatedFormat('F d, Y') }}
                                </time>
                            </div>
                            @if($article->hasCoverImage())
                                <figure class="blog-cover-figure mb-4">
                                    <img src="{{ $article->getCoverImageUrl() }}"
                                         alt="{{ $article->title }}"
                                         class="blog-cover-image"
                                         width="1200"
                                         height="675"
                                         itemprop="image"
                                         loading="eager"
                                         decoding="async"
                                         fetchpriority="high">
                                </figure>
                            @endif
                        </header>

                        <div class="blog-content" itemprop="articleBody">
                            {!! $article->content !!}
                        </div>

                        <footer class="blog-cta mt-5 p-4 rounded">
                            <h3>{{ __('messages.blog.cta_title') }}</h3>
                            <p>{{ __('messages.blog.cta_text') }}</p>
                            <div class="d-flex gap-3 flex-wrap">
                                <a target="_blank" href="https://play.google.com/store/apps/details?id=com.healthyproduct.app">
                                    <img src="{{ asset('assets/images/playstore.png') }}" alt="Download Vital Scan on Google Play" class="playstore">
                                </a>
                                <a target="_blank" href="{{ config('services.apple.app_store_url') }}">
                                    <img src="{{ asset('assets/images/appstore.png') }}" alt="Download Vital Scan on App Store" class="appstore">
                                </a>
                            </div>
                        </footer>
                    </article>

                    @if($related->isNotEmpty())
                        <aside class="related-articles mt-5">
                            <h3>{{ __('messages.blog.related_articles') }}</h3>
                            <div class="row g-3">
                                @foreach($related as $relatedArticle)
                                    <div class="col-md-4">
                                        <a href="{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $relatedArticle->slug]) }}"
                                           class="related-card d-block rounded overflow-hidden">
                                            <img src="{{ $relatedArticle->getCoverImageUrl() }}"
                                                 alt="{{ $relatedArticle->title }}"
                                                 class="related-card-cover"
                                                 width="400"
                                                 height="210"
                                                 loading="lazy"
                                                 decoding="async">
                                            <span class="related-card-title d-block p-3">
                                                <strong>{{ $relatedArticle->title }}</strong>
                                            </span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </aside>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

@push('jsonld')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "{{ addslashes($article->title) }}",
    "description": "{{ addslashes($article->getExcerpt(160)) }}",
    "image": "{{ $article->getCoverImageUrl() }}",
    "datePublished": "{{ $article->created_at->toIso8601String() }}",
    "dateModified": "{{ $article->updated_at->toIso8601String() }}",
    "author": {
        "@type": "Organization",
        "name": "Vital Scan",
        "url": "{{ rtrim(config('app.url'), '/') }}"
    },
    "publisher": {
        "@type": "Organization",
        "name": "Vital Scan",
        "logo": {
            "@type": "ImageObject",
            "url": "{{ asset('assets/images/logo_new.png') }}"
        }
    },
    "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}"
    }
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Vital Scan",
            "item": "{{ route('home', ['locale' => app()->getLocale()]) }}"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "{{ addslashes(__('messages.blog.title')) }}",
            "item": "{{ route('blog.index', ['locale' => app()->getLocale()]) }}"
        },
        {
            "@type": "ListItem",
            "position": 3,
            "name": "{{ addslashes($article->title) }}",
            "item": "{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}"
        }
    ]
}
</script>
@endpush
