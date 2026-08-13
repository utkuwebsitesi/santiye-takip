@extends('layouts.app')
@section('title', 'Yeni Kullanıcı')
@section('heading', 'Yeni Kullanıcı')
@section('content')
<form class="card form-grid" method="post" action="{{ route('users.store') }}">@csrf
@include('users.partials.form')
<div class="form-actions full"><a class="btn secondary" href="{{ route('users.index') }}">Vazgeç</a><button class="btn primary">Kullanıcı Oluştur</button></div>
</form>
@endsection
