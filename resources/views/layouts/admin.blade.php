<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $documentTitle ?? ($pageTitle ? "{$pageTitle} – Ullrich Sport - DHL CSV Generator" : 'Ullrich Sport - DHL CSV Generator') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icon-512.png') }}">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @foreach(($assets['css'] ?? []) as $cssFile)
        <link rel="stylesheet" href="{{ $cssFile }}">
    @endforeach

    @stack('styles')
</head>
<body class="admin-app">
    <a class="admin-skip-link" href="#main-content">Zum Inhalt springen</a>


    @if($showHeader ?? false)
        <header class="admin-header">
            <div class="container">
                <div class="row align-items-center text-center text-md-start g-2">
                    <div class="col-md-3">
                        <div class="admin-logo-container">
                            <img src="{{ $uiConfig['company_logo'] }}" alt="Ullrich Sport" class="admin-logo img-fluid">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h1 class="admin-title mb-2">{{ $uiConfig['app_name'] }}</h1>
                        <p class="admin-tagline mb-0">{{ $uiConfig['tagline'] }}</p>
                    </div>
                    <div class="col-md-3">
                        <div class="admin-dhl-container">
                            <img src="{{ $uiConfig['dhl_logo'] }}" alt="DHL Freight" class="admin-dhl-logo img-fluid">
                        </div>
                    </div>
                </div>
            </div>
        </header>
    @endif

    <div class="app-shell {{ ($showSidebar ?? true) ? '' : 'app-shell--solo' }}">
        @if($showSidebar ?? true)
            <aside class="app-sidebar">
                <div class="admin-sidebar__logo">
                    <img src="{{ $uiConfig['company_logo'] }}" alt="Ullrich Sport" class="admin-sidebar-logo">
                </div>
                <div class="admin-sidebar__user">
                    <div class="admin-user-meta">
                        <div class="admin-user-label"><i class="fa fa-user icon"></i> Angemeldet als</div>
                        <div class="admin-user-name">{{ $displayName }}</div>
                    </div>
                    <form method="post" action="{{ $logoutHref }}" class="admin-logout-form">
                        @csrf
                        <button type="submit" class="btn btn-outline-light">
                            <i class="fa fa-sign-out icon"></i> Logout
                        </button>
                    </form>
                </div>

                <nav class="admin-nav" aria-label="Hauptnavigation">
                    <div class="admin-nav-card">
                        <x-navigation
                            class="admin-nav__list"
                            :current-section="$currentSection ?? ''"
                            :items="$navigationItems ?? null"
                        />
                    </div>
                </nav>
            </aside>
        @endif

        <main id="main-content" class="app-main" tabindex="-1">
            <div class="app-main__inner main-content">
                    @if(!empty($breadcrumbItems))
                        <x-ui.breadcrumbs :items="$breadcrumbItems" class="admin-breadcrumbs mb-4" />
                    @endif

                    @if($showSpinner ?? false)
                        <x-ui.spinner :message="$spinnerMessage ?? 'Daten werden geladen...'" />
                    @endif

                    <x-flash-messages
                        :messages="$messages ?? []"
                        :error="$error ?? null"
                        :success="$success ?? null"
                        :info="$info ?? null"
                    />

                @yield('content')
            </div>
        </main>
    </div>

    @if($showFooter ?? true)
        <footer class="admin-footer py-5 mt-5">
            <div class="container">
                <div class="row text-center gy-4">
                    <div class="col-md-4"></div>
                    <div class="col-md-4"></div>
                    <div class="col-md-4">
                        <div class="admin-powered-by">
                            <span class="fw-medium">Made with</span>
                            <i class="fa fa-heart icon text-danger"></i>
                            <span class="fw-medium">by</span>
                            <span class="admin-powered-name">Samuel Tubach</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    @endif


    @foreach(($assets['js'] ?? []) as $jsFile)
        <script src="{{ $jsFile }}" defer></script>
    @endforeach

    @stack('scripts')
</body>
</html>
