@extends('layouts.app')
@section('title', 'Yakıt Kaydını Düzelt')
@section('heading', 'Yönetici Düzeltmesi')
@section('content')
<div class="page-title"><div><h1>Yakıt Kaydını Düzelt</h1><p>Gerekçe zorunludur; önceki değerler silinmez.</p></div></div>
<form class="card form-grid" method="post" enctype="multipart/form-data" action="{{ route('fuel.update', $fuelEntry) }}">@csrf @method('patch')
    <label class="full">Araç / makine<select name="vehicle_id">@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" data-tracks-meters="{{ $vehicle->tracks_meters ? 1 : 0 }}" @selected($fuelEntry->vehicle_id===$vehicle->id)>{{ $vehicle->display_name }}</option>@endforeach</select></label>
    <label class="full">Tanker<select name="tanker_id">@foreach($tankers as $tanker)<option value="{{ $tanker->id }}" @selected($fuelEntry->tanker_id===$tanker->id)>{{ $tanker->name }}</option>@endforeach</select><small>Stok güvenliği için tanker ve litre değiştirilemez.</small></label>
    <label>Tarih<input type="date" name="fuel_date" value="{{ $fuelEntry->fuel_date->toDateString() }}"></label>
    <label>Saat<input type="time" name="fuel_time" value="{{ substr((string) $fuelEntry->fuel_time, 0, 5) }}"></label>
    <label>Litre<input type="number" step="0.001" name="liters" value="{{ $fuelEntry->liters }}"></label>
    <label>Kayıt anındaki son alış maliyeti<input type="number" step="0.0001" name="unit_price" value="{{ $fuelEntry->unit_price }}" readonly></label>
    <label class="meter-field">Kilometre<input type="number" step="0.1" name="meter_value" value="{{ $fuelEntry->meter_value }}"></label>
    <label class="meter-field">Çalışma saati<input type="number" step="0.1" name="operating_hours" value="{{ $fuelEntry->operating_hours }}"></label>
    <label>İstasyon<input name="station" value="{{ $fuelEntry->station }}"></label>
    <label class="full">Not<textarea name="notes">{{ $fuelEntry->notes }}</textarea></label>
    @if($fuelEntry->receipt_path)<p class="full"><a href="{{ route('documents.fuel', $fuelEntry) }}">Mevcut fişi indir</a></p><label class="check full"><input type="checkbox" name="remove_receipt" value="1"> Mevcut fiş bağlantısını kaldır</label>@endif
    <label class="full">Yeni fiş / fatura<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
    <label class="full"><span>Sayaç istisnası gerekçesi <small class="label-inline-note">(KM veya çalışma saati önceki değerden düşükse doldurun)</small></span><textarea name="meter_override_reason">{{ old('meter_override_reason') }}</textarea></label>
    <label class="full">Düzeltme gerekçesi<textarea name="reason" minlength="5" required>{{ old('reason') }}</textarea></label>
    <div class="form-actions full"><a class="btn secondary" href="{{ route('fuel.index') }}">Vazgeç</a><button class="btn primary">Gerekçeyle Güncelle</button></div>
</form>
<script>
const editVehicle=document.querySelector('select[name="vehicle_id"]');const refreshEditMeters=()=>{const enabled=editVehicle.options[editVehicle.selectedIndex]?.dataset.tracksMeters!=='0';document.querySelectorAll('.meter-field').forEach(field=>{field.hidden=!enabled;field.querySelector('input').disabled=!enabled})};editVehicle.addEventListener('change',refreshEditMeters);refreshEditMeters();
</script>
<form class="danger-zone" method="post" action="{{ route('fuel.destroy', $fuelEntry) }}" data-confirm="Bu yakıt kaydı görünür listeden kaldırılacak. Devam edilsin mi?">@csrf @method('delete')
    <label>Silme gerekçesi<input name="reason" minlength="5" required></label><button class="btn danger">Gerekçeyle Sil</button>
</form>
@endsection
