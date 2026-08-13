@extends('layouts.app')
@section('title', 'Kasa Hareketini Düzelt')
@section('heading', 'Yönetici Düzeltmesi')
@section('content')
<div class="page-title"><div><h1>Kasa Hareketini Düzelt</h1><p>Gerekçe zorunludur; eski ve yeni değerler kalıcı olarak saklanır.</p></div></div>
<form class="card form-grid transaction-form" method="post" enctype="multipart/form-data" action="{{ route('transactions.update', $transaction) }}">@csrf @method('patch')
    <label>İşlem türü<select id="transaction-type" name="type"><option value="income" @selected($transaction->type==='income')>Gelir</option><option value="expense" @selected($transaction->type==='expense')>Gider</option></select></label>
    <label>Tarih<input type="date" name="occurred_on" value="{{ old('occurred_on', $transaction->occurred_on->toDateString()) }}"></label>
    <label>Kategori<select id="transaction-category" name="category"><option value="">Kategori seçiniz</option>@foreach($categories as $category)<option value="{{ $category->name }}" data-type="{{ $category->type }}" @selected(old('category', $transaction->category)===$category->name)>{{ $category->name }}</option>@endforeach @if(!$categories->contains('name', $transaction->category))<option value="{{ $transaction->category }}" data-type="{{ $transaction->type }}" selected>{{ $transaction->category }}</option>@endif</select></label>
    <label>Tutar (₺)<input id="transaction-amount" type="number" step="0.01" name="amount" value="{{ old('amount', $transaction->amount) }}"><small>Yakıt tutarı litre × fiyat olarak hesaplanır; kasa bakiyesinden düşmez.</small></label>
    <label class="full">Açıklama<input name="description" value="{{ old('description', $transaction->description) }}"></label>
    @include('transactions.partials.fuel-fields')
    @include('transactions.partials.maintenance-expense-fields')
    @if($transaction->document_path)<p class="full"><a href="{{ route('documents.transaction', $transaction) }}">Mevcut belgeyi indir</a></p><label class="check full"><input type="checkbox" name="remove_document" value="1"> Mevcut belge bağlantısını kaldır</label>@endif
    <label class="full">Yeni belge<input type="file" name="document" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
    <label class="full">Düzeltme gerekçesi<textarea name="reason" minlength="5" required>{{ old('reason') }}</textarea></label>
    <div class="form-actions full"><a class="btn secondary" href="{{ route('transactions.index') }}">Vazgeç</a><button class="btn primary">Gerekçeyle Güncelle</button></div>
</form>
<form class="danger-zone" method="post" action="{{ route('transactions.destroy', $transaction) }}" data-confirm="Bu kayıt görünür listeden kaldırılacak. Devam edilsin mi?">@csrf @method('delete')
    <label>Silme gerekçesi<input name="reason" minlength="5" required></label><button class="btn danger">Gerekçeyle Sil</button>
</form>
@include('transactions.partials.fuel-script')
@endsection
