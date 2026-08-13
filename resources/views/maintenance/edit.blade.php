@extends('layouts.app')
@section('title', 'Bakım Kaydını Düzenle')
@section('heading', 'Bakım / Onarım Düzenle')
@section('content')
<div class="page-title"><div><h1>Bakım Kaydını Düzenle</h1><p>Yapılan değişiklik gerekçesiyle birlikte işlem geçmişine kaydedilir.</p></div></div>
<form class="card form-grid" method="post" enctype="multipart/form-data" action="{{ route('maintenance.update', $maintenance) }}">
    @csrf @method('PATCH')
    @include('maintenance.partials.form')
    <label class="full">Değişiklik gerekçesi<input name="reason" value="{{ old('reason') }}" minlength="5" maxlength="1000" required></label>
    <div class="form-actions full"><a class="btn secondary" href="{{ route('maintenance.index') }}">Vazgeç</a><button class="btn primary">Değişiklikleri Kaydet</button></div>
</form>
<form class="danger-zone" method="post" action="{{ route('maintenance.destroy', $maintenance) }}" onsubmit="return confirm('Bu bakım kaydı silinsin mi?')">
    @csrf @method('DELETE')
    <label>Silme gerekçesi<input name="reason" minlength="5" maxlength="1000" required></label>
    <button class="btn danger">Kaydı Sil</button>
</form>
@endsection
