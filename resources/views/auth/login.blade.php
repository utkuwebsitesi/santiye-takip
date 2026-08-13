<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1f3a">
    <title>Giriş — Şantiye Takip</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/brand-mark.svg') }}?v=20260731-1">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v=20260731-1">
</head>
<body class="login-page">
<section class="login-card">
    <div class="login-brand"><img class="brand-logo login-logo" src="{{ asset('assets/brand-mark.svg') }}" alt="Şantiye Takip"><h1>Şantiye <span>Takip</span></h1><p>Kasa &amp; Yakıt Yönetimi</p></div>
    <form method="post" action="{{ route('login') }}" class="form-stack">
        @csrf
        <input type="hidden" name="captcha_token" value="{{ $captchaToken }}">
        <label>Kullanıcı adı<input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></label>
        <label>Şifre<input type="password" name="password" autocomplete="current-password" required></label>
        <label>Güvenlik doğrulaması
            <span class="captcha-field"><strong>{{ $captchaQuestion }}</strong><input name="captcha" inputmode="numeric" autocomplete="off" maxlength="10" aria-label="Güvenlik sorusunun cevabı" required></span>
        </label>
        @error('captcha')<p class="field-error">{{ $message }}</p>@enderror
        @error('username')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn primary" type="submit">Güvenli Giriş</button>
    </form>
    <footer class="login-signature" aria-label="Yazılım geliştiricisi">
        <span>Geliştiren</span>
        <img src="{{ asset('assets/utkuweb-logo.webp') }}" alt="Utkuweb Web Çözümleri" width="132" height="48">
    </footer>
</section>
</body>
</html>
