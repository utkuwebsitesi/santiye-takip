@extends('layouts.app')
@section('title', 'Tankere Yakıt Alımı')
@section('heading', 'Tankere Yakıt Alımı')
@section('content')
<div class="page-title"><div><h1>Tankere Yakıt Al</h1><p>Bu işlem tanker stokunu artırır ve toplam tutarı ana kasadan düşer.</p></div></div>
<form class="card form-grid transaction-form" method="post" action="{{ route('tankers.purchase.store') }}">@csrf
    <label class="full">Yakıtın alınacağı tanker<select name="tanker_id" required><option value="">Tanker seçiniz</option>@foreach($tankers as $tanker)<option value="{{ $tanker->id }}" @selected(old('tanker_id', request('tanker_id'))==$tanker->id)>{{ $tanker->name }} — Mevcut: {{ number_format($tanker->stock_liters, 3, ',', '.') }} L</option>@endforeach</select></label>
    <label>Tarih<input type="date" name="movement_date" value="{{ old('movement_date', now()->toDateString()) }}" required></label>
    <label>Saat<input type="time" name="movement_time" value="{{ old('movement_time', now()->format('H:i')) }}"></label>
    <label>Alınan litre<input id="purchase-liters" type="number" name="liters" value="{{ old('liters') }}" min="0.001" step="0.001" required></label>
    <label>Litre alış fiyatı (₺)<input id="purchase-price" type="number" name="unit_cost" value="{{ old('unit_cost') }}" min="0.0001" step="0.0001" required></label>
    <label class="full">Tedarikçi / istasyon<input name="supplier" value="{{ old('supplier') }}" maxlength="150"></label>
    <p class="meter-note full">Kasadan düşecek tahmini toplam: <strong id="purchase-total">0,00 ₺</strong></p>
    <label class="full">Not<textarea name="notes" rows="3">{{ old('notes') }}</textarea></label>
    <div class="form-actions full"><a class="btn secondary" href="{{ route('tankers.index') }}">Vazgeç</a><button class="btn primary">Stoka Ekle ve Kasadan Düş</button></div>
</form>
<script>
const refreshPurchaseTotal=()=>{const total=Number(document.getElementById('purchase-liters').value||0)*Number(document.getElementById('purchase-price').value||0);document.getElementById('purchase-total').textContent=total.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺'};
document.getElementById('purchase-liters').addEventListener('input',refreshPurchaseTotal);document.getElementById('purchase-price').addEventListener('input',refreshPurchaseTotal);refreshPurchaseTotal();
</script>
@endsection
