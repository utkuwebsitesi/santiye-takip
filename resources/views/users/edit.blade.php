@extends('layouts.app')
@section('title', 'Kullanıcıyı Düzenle')
@section('heading', 'Kullanıcıyı Düzenle')
@section('content')
<form class="card form-grid" method="post" action="{{ route('users.update', $user) }}">@csrf @method('put')
@include('users.partials.form')
<div class="form-actions full"><a class="btn secondary" href="{{ route('users.index') }}">Vazgeç</a><button class="btn primary">Güncelle</button></div>
</form>
@endsection
