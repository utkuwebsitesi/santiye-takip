@extends('layouts.app')
@section('title', 'Tanker Yönetimi')
@section('heading', 'Tanker Yönetimi')
@section('content')
<div class="page-title"><div><h1>Tanker Yönetimi</h1><p>Aktif tankerler stok, yakıt ikmali ve raporlarda otomatik görünür.</p></div><a class="btn secondary" href="{{ route('tankers.index') }}">Stoklara Dön</a></div>

<section class="card tanker-manager-create"><div class="card-head"><h2>Yeni Tanker Ekle</h2></div><form class="form-grid" method="post" action="{{ route('tankers.store') }}">@csrf<label>Tankerin adı<input name="name" value="{{ old('name') }}" maxlength="80" placeholder="Örn. Şantiye Tankeri" required><small>Yeni tanker stok sıfırdan başlar. Yakıt alımı kaydedildiğinde kasa ve stok takibine katılır.</small></label><div class="form-actions"><button class="btn primary">+ Tanker Ekle</button></div></form></section>

<section class="card table-wrap tanker-manager-list">
    <div class="card-head"><div><h2>Mevcut Tankerler</h2><small>Engel olan aktif veya arşiv kayıtları burada ayrı ayrı gösterilir.</small></div></div>
    <table>
        <thead><tr><th>Tanker</th><th>Mevcut Stok</th><th>Son Alış Fiyatı</th><th>Durum</th><th>İşlemler</th></tr></thead>
        <tbody>
        @foreach($tankers as $tanker)
            @php
                $activeHistoryCount = $tanker->active_movement_count + $tanker->active_fuel_entry_count;
                $archivedHistoryCount = $tanker->archived_movement_count + $tanker->archived_fuel_entry_count;
                $hasStock = (float) $tanker->stock_liters > 0;
            @endphp
            <tr>
                <td><form id="tanker-{{ $tanker->id }}" method="post" action="{{ route('tankers.update', $tanker) }}">@csrf @method('PATCH')<input name="name" value="{{ $tanker->name }}" maxlength="80" required></form></td>
                <td>{{ number_format($tanker->stock_liters, 3, ',', '.') }} L</td>
                <td>{{ number_format($tanker->average_unit_cost, 4, ',', '.') }} ₺/L</td>
                <td><select name="is_active" form="tanker-{{ $tanker->id }}"><option value="1" @selected($tanker->is_active)>Aktif</option><option value="0" @selected(! $tanker->is_active)>Pasif</option></select></td>
                <td><div class="tanker-actions">
                    <button class="btn secondary" form="tanker-{{ $tanker->id }}">Kaydet</button>
                    @if($hasStock)
                        <span class="tanker-history-note is-blocking">Stok var: {{ number_format($tanker->stock_liters, 3, ',', '.') }} L</span>
                    @elseif($activeHistoryCount > 0)
                        <span class="tanker-history-note is-blocking">Engel: {{ $tanker->active_movement_count }} hareket · {{ $tanker->active_fuel_entry_count }} yakıt kaydı</span>
                    @elseif($archivedHistoryCount > 0)
                        <span class="tanker-history-note is-archive">Arşivde: {{ $tanker->archived_movement_count }} hareket · {{ $tanker->archived_fuel_entry_count }} yakıt kaydı</span>
                        <form method="post" action="{{ route('tankers.purge', $tanker) }}" data-confirm="{{ $tanker->name }} tankerinin {{ $archivedHistoryCount }} arşiv kaydı kalıcı olarak silinecek. Devam etmek istiyor musunuz?">@csrf @method('DELETE')<button class="btn danger">Arşivi temizle ve sil</button></form>
                    @else
                        <form method="post" action="{{ route('tankers.destroy', $tanker) }}" data-confirm="{{ $tanker->name }} tankerini silmek istediğinize emin misiniz? Bu işlem geri alınamaz.">@csrf @method('DELETE')<button class="btn danger">Sil</button></form>
                    @endif
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="tanker-manager-note">Aktif hareketi veya stoğu olan tanker silinemez. Sadece arşiv kayıtları varsa, onay verildiğinde arşiv temizlenerek tanker kaldırılabilir.</p>
</section>
@endsection
