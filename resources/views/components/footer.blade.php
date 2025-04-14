<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 footer-contact">
                <h5 class="mb-3">Vital Scan</h5>
                <p>{{ __('messages.footer_slogan') }}</p>
                <p>
                    📧 <strong>Email:</strong>
                    <a href="mailto:info@vitalscan.app">info@vitalscan.app</a>
                </p>
                    <img src="{{ asset('assets/images/logo_new.png') }}"
                         alt="Vital Scan Logo"
                         style="width: 20%; display: block;" />
            </div>

            <div class="col-lg-2">
                <h5>{{ __('messages.menus.footer_menu.product') }}</h5>
                <ul class="list-unstyled">
                    <li><a href="{{ route('home',['locale' => \Illuminate\Support\Facades\App::getLocale()]) }}#features">{{ __('messages.menus.footer_menu.specifications') }}</a></li>
                    <li><a href="{{ route('home',['locale' => \Illuminate\Support\Facades\App::getLocale()]) }}#how-it-works">{{ __('messages.menus.footer_menu.how_it_works') }}</a></li>
                    <li><a href="{{ route('home',['locale' => \Illuminate\Support\Facades\App::getLocale()]) }}#download">{{ __('messages.menus.footer_menu.download') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>{{ __('messages.menus.footer_menu.company') }}</h5>
                <ul class="list-unstyled">
                    <li><a href="{{ route('page',['slug' => 'about-us', 'locale' => \Illuminate\Support\Facades\App::getLocale()]) }}">{{ __('messages.menus.footer_menu.about_us') }}</a></li>
                    <li><a href="{{ route('home',['locale' => \Illuminate\Support\Facades\App::getLocale()]) }}#download">{{ __('messages.menus.footer_menu.contact') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>{{ __('messages.menus.footer_menu.legal') }}</h5>
                <ul class="list-unstyled">
                    <li><a href="{{ route('page',['slug' => 'privacy-policy', 'locale' => \Illuminate\Support\Facades\App::getLocale()]) }}">{{ __('messages.menus.footer_menu.privacy') }}</a></li>
                    <li><a href="{{ route('page',['slug' => 'terms-conditions', 'locale' => \Illuminate\Support\Facades\App::getLocale()]) }}">{{ __('messages.menus.footer_menu.conditions') }}</a></li>
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
