@extends('layouts.app')
@section('title', 'Bakım Kaydı Ekle')
@section('heading', 'Yeni Bakım / Onarım')
@section('content')
<div class="page-title"><div><h1>Bakım / Onarım Kaydı</h1><p>Araç veya makineye yapılan işlemi ve bir sonraki bakım bilgisini kaydedin.</p></div></div>
<form class="card form-grid" method="post" enctype="multipart/form-data" action="{{ route('maintenance.store') }}">
    @csrf
    @include('maintenance.partials.form', ['maintenance' => new \App\Models\MaintenanceEntry()])
    <div class="form-actions full"><a class="btn secondary" href="{{ route('maintenance.index') }}">Vazgeç</a><button class="btn primary">Bakımı Kaydet</button></div>
</form>
@endsection
