@extends('layouts.app')

@section('title',' - '.$page->title)

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
