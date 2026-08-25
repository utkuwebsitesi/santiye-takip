@extends('layouts.app')
@section('title', 'Tanımı Düzenle')
@section('heading', 'Tanımı Düzenle')
@section('content')
<form class="card form-grid" method="post" action="{{ route('araclar.update', $vehicle) }}">@csrf @method('PATCH')
    @include('vehicles.partials.form')
    <div class="form-actions full"><a class="btn secondary" href="{{ route('araclar.index') }}">Vazgeç</a><button class="btn primary">Güncelle</button></div>
</form>
@endsection
