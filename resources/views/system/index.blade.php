@extends('layouts.app')
@section('title', 'Sistem Yönetimi')
@section('heading', 'Sistem Yönetimi')
@section('content')
<div class="page-title"><div><h1>Sistem Yönetimi</h1><p>Yazılım kimliği, şirket, kategoriler ve menü bölümleri</p></div></div>
<div class="system-grid">
<section class="card system-card"><div class="card-head"><h2>Yazılım ve Şirket</h2></div>
<form class="form-stack compact-form" method="post" action="{{ route('system.settings') }}">@csrf @method('put')
<label>Yazılım adı<input name="software_name" value="{{ old('software_name', $settings['software_name'] ?? 'Şantiye Takip') }}" required></label>
<label>Alt tanım<input name="software_tagline" value="{{ old('software_tagline', $settings['software_tagline'] ?? '') }}" required></label>
<label>Şirket adı<input name="company_name" value="{{ old('company_name', $settings['company_name'] ?? '') }}" required></label>
<button class="btn primary">Bilgileri Güncelle</button></form></section>

<section class="card system-card"><div class="card-head"><h2>Yeni Kategori</h2></div>
<form class="form-stack compact-form" method="post" action="{{ route('system.categories.store') }}">@csrf
<label>Tür<select name="type"><option value="expense">Gider</option><option value="income">Gelir</option></select></label>
<label>Kategori adı<input name="name" required maxlength="100"></label><button class="btn primary">Kategori Ekle</button></form></section>
</div>

<section class="card system-section"><div class="card-head"><div><h2>Veritabanı Yedekleri</h2><p>Yedekler web erişimine kapalı özel klasörde tutulur ve SHA-256 ile doğrulanır.</p></div>
<form method="post" action="{{ route('system.backups.store') }}">@csrf<button class="btn primary">Şimdi Yedek Al</button></form></div>
@error('backup')<div class="alert error">{{ $message }}</div>@enderror
<div class="table-wrap"><table><thead><tr><th>Tarih</th><th>Dosya</th><th>Boyut</th><th>Doğrulama</th><th></th></tr></thead><tbody>
@forelse($backups as $backup)<tr><td>{{ date('d.m.Y H:i', $backup['created_at']) }}</td><td>{{ $backup['filename'] }}</td><td>{{ number_format($backup['size'] / 1024, 1, ',', '.') }} KB</td><td>{{ $backup['verified'] ? 'Hazır' : 'Eksik' }}</td><td><a href="{{ route('system.backups.download', $backup['filename']) }}">İndir</a></td></tr>
@empty<tr><td colspan="5">Henüz doğrulanmış yedek bulunmuyor.</td></tr>@endforelse
</tbody></table></div></section>

<section class="card system-section"><div class="card-head"><h2>Gelir / Gider Kategorileri</h2></div>
<div class="management-list">@foreach($categories as $category)
<form method="post" action="{{ route('system.categories.update', $category) }}">@csrf @method('put')
<span class="badge {{ $category->type }}">{{ $category->type==='income'?'Gelir':'Gider' }}</span>
<input name="name" value="{{ $category->name }}" required>
<label class="check"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Aktif</label>
<button class="btn secondary">Kaydet</button></form>@endforeach</div></section>

<section class="card system-section"><div class="card-head"><h2>Sol Menü Bölümleri</h2></div>
<form method="post" action="{{ route('system.navigation') }}">@csrf @method('put')
<div class="management-list">@foreach($navigationItems as $item)
<div class="menu-config"><code>{{ $item->key }}</code><input name="items[{{ $item->id }}][label]" value="{{ $item->label }}" required>
<input class="order-input" type="number" name="items[{{ $item->id }}][sort_order]" value="{{ $item->sort_order }}" min="0" max="1000">
<label class="check"><input type="checkbox" name="items[{{ $item->id }}][is_enabled]" value="1" @checked($item->is_enabled)> Görünür</label></div>
@endforeach</div><div class="system-actions"><button class="btn primary">Menüyü Güncelle</button></div></form></section>
@endsection
