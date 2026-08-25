<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $brand['software_name'])</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/brand-mark.svg') }}?v=20260731-1">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v=20260825-release-note-1">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <img class="brand-logo" src="{{ asset('assets/brand-mark.svg') }}" alt="{{ $brand['software_name'] }}">
            <span><strong>{{ $brand['software_name'] }}</strong><small>{{ $brand['software_tagline'] }}</small></span>
        </a>
        <nav>
            @foreach($navigation as $item)
                @if($loop->first || ($item->minimum_role !== $navigation[$loop->index-1]->minimum_role))<span class="nav-label">{{ $item->minimum_role === 'personnel' ? 'İŞLEMLER' : ($item->minimum_role === 'admin' ? 'ŞİRKET YÖNETİMİ' : 'SİSTEM') }}</span>@endif
                <a href="{{ route($item->route_name) }}" @class(['active' => request()->routeIs($item->route_pattern)])>{{ $item->label }}</a>
            @endforeach
        </nav>
        <div class="userbox">
            <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
            <a class="user-account" href="{{ route('password.edit') }}" title="Parolamı değiştir">
                <strong>{{ auth()->user()->name }}</strong>
                <small>{{ auth()->user()->isAdmin() ? 'Yönetici' : 'Personel' }} · Parola</small>
            </a>
            <form method="post" action="{{ route('logout') }}">@csrf<button title="Çıkış">Çıkış</button></form>
        </div>
    </aside>
    <main>
        <header class="topbar">
            <button class="menu-button" type="button" aria-label="Menüyü aç veya kapat" aria-expanded="false">☰</button>
            <div class="topbar-heading"><strong>@yield('heading', $brand['software_name'])</strong><small>{{ $brand['company_name'] }} · {{ now()->translatedFormat('d F Y, l') }}</small></div>
            <div class="mobile-page-label" title="@yield('heading', $brand['software_name'])"><span>@yield('heading', $brand['software_name'])</span></div>
            @if(!empty($headerBriefing['weather']) || !empty($headerBriefing['rates']))
            <aside class="topbar-briefing" aria-label="Ankara hava durumu ve döviz kurları">
                @if(!empty($headerBriefing['weather']))
                <div class="weather-brief">
                    <span class="brief-title">Ankara</span>
                    @foreach($headerBriefing['weather'] as $day)
                    <span class="weather-day" title="{{ $day['date'] }} · {{ $day['condition'] }}">
                        <b>{{ $day['day'] }}</b><i>{{ $day['icon'] }}</i><small>{{ $day['max'] }}°<em>{{ $day['min'] }}°</em></small>
                    </span>
                    @endforeach
                </div>
                @endif
                @if(!empty($headerBriefing['rates']))
                <div class="rate-brief" title="TCMB döviz satış kuru">
                    @foreach($headerBriefing['rates'] as $rate)
                    <span><i>{{ $rate['icon'] }}</i><b>{{ $rate['code'] }}</b><small>{{ $rate['value'] }}</small></span>
                    @endforeach
                </div>
                @endif
            </aside>
            @endif
            <div class="topbar-actions">
                @if(!empty($headerBriefing['weather']) || !empty($headerBriefing['rates']))
                @php($todayWeather = $headerBriefing['weather'][0] ?? null)
                <details class="mobile-briefing">
                    <summary aria-label="Hava durumu ve döviz kurlarını aç">
                        <small>Ankara</small>
                        <span>{{ $todayWeather['icon'] ?? '₺' }}</span>
                        @if($todayWeather)<b>{{ $todayWeather['max'] }}°</b>@endif
                    </summary>
                    <div class="mobile-briefing-panel">
                        <div class="mobile-briefing-head"><strong>Ankara · 5 Günlük Hava</strong><small>Son veri {{ $headerBriefing['weather_updated_at'] ?? '—' }}</small></div>
                        @if(!empty($headerBriefing['weather']))
                        <div class="mobile-weather-days">
                            @foreach($headerBriefing['weather'] as $day)
                            <span title="{{ $day['condition'] }}"><b>{{ $day['day'] }}</b><i>{{ $day['icon'] }}</i><small>{{ $day['max'] }}° <em>{{ $day['min'] }}°</em></small></span>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($headerBriefing['rates']))
                        <div class="mobile-rates">
                            <div><strong>TCMB Döviz Satış</strong><small>Son veri {{ $headerBriefing['rates_updated_at'] ?? '—' }}</small></div>
                            @foreach($headerBriefing['rates'] as $rate)
                            <span><i>{{ $rate['icon'] }}</i><b>{{ $rate['code'] }}</b><strong>{{ $rate['value'] }} ₺</strong></span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </details>
                @endif
                @if(auth()->user()?->hasPermission('notifications.view'))
                <details class="notification-menu">
                    <summary aria-label="Bildirimleri aç">
                        <span class="bell">🔔</span>
                        @if(($unreadNotificationCount ?? 0) > 0)<b>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</b>@endif
                    </summary>
                    <div class="notification-panel">
                        <div class="notification-panel-head"><strong>Bildirimler</strong><a href="{{ route('notifications.index') }}">Tümünü Gör</a></div>
                        @forelse(($headerNotifications ?? collect()) as $notification)
                            <a href="{{ route('notifications.open', $notification) }}" @class(['notification-mini', 'unread' => !$notification->read_at])>
                                <strong>{{ $notification->title }}</strong>
                                <small>{{ $notification->message }}</small>
                                <time>{{ $notification->created_at->diffForHumans() }}</time>
                            </a>
                        @empty
                            <p class="empty">Bildirim yok.</p>
                        @endforelse
                    </div>
                </details>
                @endif
                <span class="secure">● Güvenli Oturum</span>
            </div>
        </header>
        <section class="content">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert error"><strong>İşlem tamamlanamadı.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
            <footer class="app-version" aria-label="Sürüm bilgisi">
                <span class="app-version-name">{{ $brand['software_name'] }}</span>
                <b>v{{ config('app.version', '1.1.0') }}</b>
                <span class="app-version-date">{{ config('app.release_date', '25 Ağustos 2026') }}</span>
                <details class="app-release-note">
                    <summary>Sürüm notu</summary>
                    <p>Yetki bazlı kullanıcı yönetimi, PDF/Excel rapor izinleri, kompakt kayıt listeleri ve cPanel uyumlu güvenli yedekleme iyileştirmeleri.</p>
                </details>
            </footer>
        </section>
    </main>
</div>
@if(auth()->user()?->hasPermission('notifications.view') && ($newNotifications ?? collect())->isNotEmpty())
<aside class="maintenance-toast" role="alert" aria-live="polite" data-auto-dismiss="8000">
    <button type="button" class="maintenance-toast-close" aria-label="Bakım uyarısını kapat">×</button>
    <strong>⚠ Bakım zamanı geldi</strong>
    @foreach($newNotifications->take(3) as $notification)
        <div class="maintenance-toast-item">
            <b>{{ $notification->title }}</b>
            <small>{{ $notification->message }}</small>
        </div>
    @endforeach
    @if($newNotifications->count() > 3)<small>+ {{ $newNotifications->count() - 3 }} bildirim daha</small>@endif
    <a href="{{ route('notifications.index') }}">Bildirim geçmişini aç</a>
</aside>
@endif
<script>
    document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('submit', e => {
        if (! confirm(el.dataset.confirm)) e.preventDefault();
    }));
    document.querySelectorAll('[data-auto-submit]').forEach(field => {
        field.addEventListener('change', () => field.form?.requestSubmit());
    });
    document.querySelector('.maintenance-toast-close')?.addEventListener('click', event => {
        event.currentTarget.closest('.maintenance-toast')?.remove();
    });
    const temporaryNotification = document.querySelector('[data-auto-dismiss]');
    if (temporaryNotification) {
        window.setTimeout(() => {
            temporaryNotification.classList.add('is-hiding');
            window.setTimeout(() => temporaryNotification.remove(), 250);
        }, Number(temporaryNotification.dataset.autoDismiss || 8000));
    }

    const menuButton = document.querySelector('.menu-button');
    const sidebar = document.querySelector('.sidebar');
    const closeMenu = () => {
        document.body.classList.remove('menu-open');
        menuButton?.setAttribute('aria-expanded', 'false');
    };

    menuButton?.addEventListener('click', event => {
        event.stopPropagation();
        const isOpen = document.body.classList.toggle('menu-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('click', event => {
        if (document.body.classList.contains('menu-open') && ! sidebar?.contains(event.target)) closeMenu();
        document.querySelectorAll('.mobile-briefing[open]').forEach(menu => {
            if (! menu.contains(event.target)) menu.removeAttribute('open');
        });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeMenu();
    });
    sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
</script>
</body>
</html>
