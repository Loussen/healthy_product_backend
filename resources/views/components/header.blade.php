<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home',['locale' => \Illuminate\Support\Facades\App::getLocale()]) }}">
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
                    <img src="{{ asset('assets/images/' . app()->getLocale() . '.png') }}" width="24" alt="{{ app()->getLocale() }}">
                </button>
                <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                    @foreach(config('services.locales') as $locale => $language)
                        @php
                            // Mevcut route bilgileri ve parametreleri al
                            $routeName = Route::currentRouteName();
                            $routeParams = request()->route()->parameters();

                            // Locale'i güncelle
                            $routeParams['locale'] = $locale;

                            // Eğer mevcut route ismi yoksa (yani tanımlı değilse), varsayılan ana sayfaya gönder
                            $url = $routeName
                                ? route($routeName, $routeParams)
                                : url($locale);
                        @endphp
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ $url }}">
                                <img src="{{ asset('assets/images/' . $locale . '.png') }}" width="24" alt="{{ $locale }}">
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
