<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Scan - Your vital scan compass</title>
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="{{ asset('assets/images/logo.png') }}" alt="Vital Scan">
            <span class="brand-text">Your vital scan compass</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#features">Özellikler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#how-it-works">Nasıl Çalışır</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#download">İndir</a>
                </li>
            </ul>
            <a href="#download" class="btn btn-primary ms-lg-3">Hemen Başla</a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1>Sağlıklı yaşam için akıllı seçimler yapın</h1>
                <p class="lead">Ürünleri tarayın, içeriklerini analiz edin ve sağlığınız için en iyi kararları verin. Yapay zeka destekli uygulamamız ile sağlıklı yaşam artık daha kolay!</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" class="">
                        <img src="{{ asset('assets/images/playstore.png') }}" alt="Play Store VScan" class="playstore">
                    </a>
                    <a href="#" class="">
                        <img src="{{ asset('assets/images/appstore.png') }}" alt="App Store VScan" class="appstore">
                    </a>
                </div>
            </div>
            <div class="col-lg-6 camera-content-div">
                <img src="{{ asset('assets/images/screen_camera-portrait.png') }}" alt="Vital Scan" class="camera-content">
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="features">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Neden Vital Scan?</h2>
            <p class="lead">Your vital scan compass</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="icon-wrapper bg-primary-soft">
                        <i class="bi bi-camera text-primary"></i>
                    </div>
                    <h3>Hızlı Tarama</h3>
                    <p>Ürünleri saniyeler içinde tarayın ve detaylı içerik analizine ulaşın.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="icon-wrapper bg-success-soft">
                        <i class="bi bi-graph-up text-success"></i>
                    </div>
                    <h3>Sağlık Skoru</h3>
                    <p>Her ürün için özel sağlık skoru hesaplaması ve detaylı içerik analizi.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="icon-wrapper bg-warning-soft">
                        <i class="bi bi-bookmark-heart text-warning"></i>
                    </div>
                    <h3>Kişiselleştirme</h3>
                    <p>Alerjilerinizi ve kategorinizi belirleyin, size özel öneriler alın.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section id="how-it-works" class="how-it-works">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Nasıl Çalışır?</h2>
            <p class="lead">3 basit adımda sağlıklı yaşama başlayın</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h4>Ürünü Tarayın</h4>
                    <p>Ürünün barkodunu veya ambalajını kameranızla tarayın.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h4>Analizi İnceleyin</h4>
                    <p>Yapay zeka destekli detaylı içerik analizini görüntüleyin.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h4>Karar Verin</h4>
                    <p>Sağlık skoruna göre en doğru kararı verin.</p>
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
                <h2>Hemen İndirin</h2>
                <p class="lead">Sağlıklı yaşam için ilk adımı atın. Uygulamayı indirin ve ürünleri analiz etmeye başlayın.</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" class="store-badge">
                        <img src="{{ asset('assets/images/playstore.png') }}" alt="App Store VScan">
                    </a>
                    <a href="#" class="store-badge">
                        <img src="{{ asset('assets/images/appstore.png') }}" alt="Google Play VScan">
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="download-image">
                    <img src="{{ asset('assets/images/splash_screen-left.png') }}" alt="App Screenshots" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <h5>Vital Scan</h5>
                <p>Sağlıklı yaşam için akıllı seçimler yapın.</p>
            </div>
            <div class="col-lg-2">
                <h5>Ürün</h5>
                <ul class="list-unstyled">
                    <li><a href="#features">Özellikler</a></li>
                    <li><a href="#how-it-works">Nasıl Çalışır</a></li>
                    <li><a href="#download">İndir</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>Şirket</h5>
                <ul class="list-unstyled">
                    <li><a href="#">Hakkımızda</a></li>
                    <li><a href="#">İletişim</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>Yasal</h5>
                <ul class="list-unstyled">
                    <li><a href="#">Gizlilik</a></li>
                    <li><a href="#">Koşullar</a></li>
                </ul>
            </div>
            <div class="col-lg-2">
                <h5>Sosyal</h5>
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
                <p class="mb-0">&copy; 2025 Vital Scan. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </div>
</footer>

<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
