@php
    $maintenance = $transaction->maintenanceEntry ?? null;
@endphp
<section id="maintenance-expense-fields" class="fuel-expense-fields maintenance-expense-fields full" @if(old('category', $transaction->category) !== 'Bakım / Onarım' && ! $maintenance) hidden @endif>
    <div class="fuel-head"><strong>Araç Bakım / Onarım Kaydı</strong><small>Bu gider, seçilen araç veya makinenin bakım geçmişine ve raporlarına işlenir.</small></div>
    <div class="fuel-grid">
        <label>Araç veya makine<select name="maintenance_vehicle_id"><option value="">Araç veya makine seçiniz</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('maintenance_vehicle_id', $maintenance?->vehicle_id) == $vehicle->id)>{{ $vehicle->display_name }}</option>@endforeach</select></label>
        <label>Servis / usta<input name="maintenance_service_provider" value="{{ old('maintenance_service_provider', $maintenance?->service_provider) }}" maxlength="150" placeholder="İsteğe bağlı"></label>
    </div>
</section>
