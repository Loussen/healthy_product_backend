@extends('layouts.app')

@section('title', ' - ' . $page->title)

@section('meta_description', Str::limit(strip_tags($page->content), 160))

@push('jsonld')
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
            "name": "{{ addslashes($page->title) }}",
            "item": "{{ route('page', ['locale' => app()->getLocale(), 'slug' => $page->slug]) }}"
        }
    ]
}
</script>
@endpush

@section('content')
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row mt-5">
                <h2 class="text-center mb-5">{{ $icon." ".$page->title }}</h2>
                <p class="mb-3">
                    <span>{{ __('messages.last_updated') }}</span>
                    <br>
                    {{ \Carbon\Carbon::parse($page->updated_at)->translatedFormat('F d, Y') }}
                </p>
                {!! $page->content !!}
            </div>
        </div>
    </section>
@endsection
