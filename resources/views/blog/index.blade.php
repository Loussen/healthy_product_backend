@extends('layouts.app')

@section('title', ' - ' . __('messages.blog.title'))
@section('meta_description', __('messages.blog.meta_description'))

@section('content')
    <section class="hero blog-hero">
        <div class="container">
            <div class="row mt-5">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h1>{{ __('messages.blog.title') }}</h1>
                    <p class="lead">{{ __('messages.blog.subtitle') }}</p>
                </div>
            </div>
            <div class="row g-4">
                @forelse($articles as $article)
                    <div class="col-md-6 col-lg-4">
                        <article class="blog-card h-100">
                            <div class="blog-card-body">
                                <time class="blog-date" datetime="{{ $article->updated_at->toIso8601String() }}">
                                    {{ $article->updated_at->translatedFormat('F d, Y') }}
                                </time>
                                <h2 class="blog-card-title">
                                    <a href="{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}">
                                        {{ $article->title }}
                                    </a>
                                </h2>
                                <p class="blog-excerpt">{{ $article->getExcerpt(140) }}</p>
                                <a href="{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}"
                                   class="blog-read-more">
                                    {{ __('messages.blog.read_more') }} &rarr;
                                </a>
                            </div>
                        </article>
                    </div>
                @empty
                    <div class="col-12 text-center">
                        <p>{{ __('messages.blog.no_articles') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection

@push('jsonld')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Blog",
    "name": "{{ addslashes(__('messages.blog.title')) }}",
    "description": "{{ addslashes(__('messages.blog.meta_description')) }}",
    "url": "{{ route('blog.index', ['locale' => app()->getLocale()]) }}",
    "publisher": { "@id": "{{ rtrim(config('app.url'), '/') }}/#organization" }
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
        }
    ]
}
</script>
@endpush
