@extends('layouts.app')

@section('content')
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>{{ $page->title }}</h1>
            <div class="row">
                {!! $page->content !!}
            </div>
        </div>
    </section>
@endsection
