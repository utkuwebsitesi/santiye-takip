@extends('layouts.app')
@section('title', 'Gelir / Gider Ekle')
@section('heading', 'Yeni Kasa Hareketi')
@section('content')
<div class="page-title"><div><h1>Gelir / Gider Kaydı</h1><p>Kaydedilen işlem personel tarafından değiştirilemez.</p></div></div>
<form class="card form-grid transaction-form" method="post" enctype="multipart/form-data" action="{{ route('transactions.store') }}">@csrf
    <label>İşlem türü<select id="transaction-type" name="type" required><option value="income" @selected(old('type')==='income')>Gelir</option><option value="expense" @selected(old('type')==='expense')>Gider</option></select></label>
    <label>Tarih<input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" required></label>
    <label>Kategori<select id="transaction-category" name="category" required><option value="">Kategori seçiniz</option>@foreach($categories as $category)<option value="{{ $category->name }}" data-type="{{ $category->type }}" @selected(old('category')===$category->name)>{{ $category->name }}</option>@endforeach</select></label>
    <label>Tutar (₺)<input id="transaction-amount" type="number" name="amount" value="{{ old('amount') }}" min="0.01" step="0.01" inputmode="decimal" required><small>Yakıt tutarı litre × fiyat olarak hesaplanır; kasa bakiyesinden düşmez.</small></label>
    <label class="full">Açıklama<input name="description" value="{{ old('description') }}" maxlength="255" required></label>
    @include('transactions.partials.fuel-fields', ['transaction' => new \App\Models\Transaction()])
    @include('transactions.partials.maintenance-expense-fields', ['transaction' => new \App\Models\Transaction()])
    <label class="full">Fiş, fatura veya belge <small>JPG, PNG, WEBP veya PDF; en fazla 5 MB</small><input type="file" name="document" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
    <div class="form-actions full"><a class="btn secondary" href="{{ route('transactions.index') }}">Vazgeç</a><button class="btn primary">Kaydet ve Kilitle</button></div>
</form>
@include('transactions.partials.fuel-script')
@endsection
