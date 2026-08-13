@extends('layouts.app')
@section('title', 'Yeni Araç / Makine')
@section('heading', 'Yeni Araç / Makine')
@section('content')
<form class="card form-grid" method="post" action="{{ route('araclar.store') }}">@csrf
    @include('vehicles.partials.form')
    <div class="form-actions full"><a class="btn secondary" href="{{ route('araclar.index') }}">Vazgeç</a><button class="btn primary">Kaydet</button></div>
</form>
@endsection
