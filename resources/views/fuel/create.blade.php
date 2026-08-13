@extends('layouts.app')
@section('title', 'Yakıt Kaydı Ekle')
@section('heading', 'Yeni Yakıt Kaydı')
@section('content')
<div class="page-title"><div><h1>Araç / Makine Yakıtı</h1><p>Plaka veya makine koduna göre yakıt kaydı oluşturun.</p></div></div>
@if($tankers->every(fn($tanker) => (float)$tanker->stock_liters <= 0))
<div class="alert error stock-warning"><strong>Tankerlerde kullanılabilir yakıt yok.</strong> Araç ikmali yapmadan önce <a href="{{ route('tankers.purchase.create') }}">tankere yakıt alımı girin</a>.</div>
@endif
<form class="card form-grid" method="post" enctype="multipart/form-data" action="{{ route('fuel.store') }}">@csrf
    <label class="full">Araç veya makine<select name="vehicle_id" required><option value="">Seçiniz</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" data-tracks-meters="{{ $vehicle->tracks_meters ? 1 : 0 }}" @selected(old('vehicle_id')==$vehicle->id)>{{ $vehicle->display_name }}</option>@endforeach</select></label>
    <label class="full">Yakıtın verileceği tanker<select name="tanker_id" required><option value="">Tanker seçiniz</option>@foreach($tankers as $tanker)<option value="{{ $tanker->id }}" data-unit-cost="{{ $tanker->average_unit_cost }}" data-stock="{{ $tanker->stock_liters }}" @disabled((float)$tanker->stock_liters <= 0) @selected(old('tanker_id')==$tanker->id)>{{ $tanker->name }} — {{ (float)$tanker->stock_liters > 0 ? 'Kalan: '.number_format($tanker->stock_liters, 3, ',', '.').' L' : 'Stok yok' }}</option>@endforeach</select><small>Verilen litre seçilen tankerin stokundan düşer. <a href="{{ route('tankers.purchase.create') }}">Tankere yakıt al</a></small></label>
    <label>Yakıt tarihi<input type="date" name="fuel_date" value="{{ old('fuel_date', now()->toDateString()) }}" required></label>
    <label>Saat<input type="time" name="fuel_time" value="{{ old('fuel_time', now()->format('H:i')) }}"></label>
    <label>Tankerden verilen litre<input type="number" name="liters" value="{{ old('liters') }}" min="0.001" step="0.001" inputmode="decimal" required><small id="available-stock"></small></label>
    <label>Son alış birim maliyeti (₺)<input type="number" name="unit_price" value="{{ old('unit_price') }}" min="0" step="0.0001" inputmode="decimal" readonly><small>Seçilen tankere yapılan en son yakıt alımının litre fiyatıdır.</small></label>
    <label class="meter-field">Kilometre<input type="number" name="meter_value" value="{{ old('meter_value') }}" min="0" step="0.1" inputmode="decimal"></label>
    <label class="meter-field">Çalışma saati<input type="number" name="operating_hours" value="{{ old('operating_hours') }}" min="0" step="0.1" inputmode="decimal"></label>
    <label>Akaryakıt istasyonu<input name="station" value="{{ old('station') }}" maxlength="150"></label>
    <label class="full">Not<textarea name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea></label>
    @if(auth()->user()->isAdmin())<label class="full"><span>Sayaç istisnası gerekçesi <small class="label-inline-note">(KM veya çalışma saati önceki değerden düşükse doldurun)</small></span><textarea name="meter_override_reason">{{ old('meter_override_reason') }}</textarea></label>@endif
    <label class="full">Yakıt fişi / faturası<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
    <div class="form-actions full"><a class="btn secondary" href="{{ route('fuel.index') }}">Vazgeç</a><button class="btn primary">Hesapla, Kaydet ve Kilitle</button></div>
</form>
<script>
const tanker=document.querySelector('[name="tanker_id"]'),vehicle=document.querySelector('[name="vehicle_id"]'),price=document.querySelector('[name="unit_price"]'),liters=document.querySelector('[name="liters"]'),stockNote=document.getElementById('available-stock');
const syncCost=()=>{const option=tanker.options[tanker.selectedIndex],stock=Number(option?.dataset.stock||0);price.value=option?.dataset.unitCost||'';liters.max=stock>0?String(stock):'';stockNote.textContent=stock>0?'En fazla '+stock.toLocaleString('tr-TR',{maximumFractionDigits:3})+' L verilebilir.':''};
const syncMeters=()=>{const enabled=vehicle.options[vehicle.selectedIndex]?.dataset.tracksMeters!=='0';document.querySelectorAll('.meter-field').forEach(field=>{field.hidden=!enabled;field.querySelector('input').disabled=!enabled})};
tanker.addEventListener('change',syncCost);vehicle.addEventListener('change',syncMeters);syncCost();syncMeters();
</script>
@endsection
