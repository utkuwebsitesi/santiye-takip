@extends('layouts.app')
@section('title', 'Parolamı Değiştir')
@section('heading', 'Hesap Güvenliği')
@section('content')
<div class="page-title"><div><h1>Parolamı Değiştir</h1><p>En az 10 karakter; büyük/küçük harf ve rakam kullanın.</p></div></div>
<form class="card form-grid" method="post" action="{{ route('password.update') }}">@csrf @method('put')
    <label class="full">Mevcut parola<input type="password" name="current_password" autocomplete="current-password" required></label>
    <label>Yeni parola<input type="password" name="password" autocomplete="new-password" required></label>
    <label>Yeni parola tekrarı<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
    <div class="form-actions full"><button class="btn primary">Parolayı Değiştir</button></div>
</form>
@endsection
