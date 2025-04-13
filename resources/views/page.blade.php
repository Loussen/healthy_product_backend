@extends('layouts.app')

@section('title',' - '.$page->title)

@section('content')
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1 class="text-center">{{ $icon." ".$page->title }}</h1>
            <div class="row mt-5">
                {!! $page->content !!}
            </div>
        </div>
    </section>
@endsection
