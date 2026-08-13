@php
    $maintenanceTypes = ['Periyodik Bakım', 'Yağ / Filtre', 'Lastik', 'Fren', 'Motor', 'Elektrik', 'Kaporta', 'Arıza / Onarım', 'Muayene', 'Diğer'];
@endphp
<label>Araç / Makine
    <select name="vehicle_id" required>
        <option value="">Seçiniz</option>
        @foreach($vehicles as $vehicle)
            <option value="{{ $vehicle->id }}" data-tracks-meters="{{ $vehicle->tracks_meters ? 1 : 0 }}" @selected(old('vehicle_id', $maintenance->vehicle_id) == $vehicle->id)>
                {{ $vehicle->plate ?? $vehicle->code }} · {{ $vehicle->name }}
            </option>
        @endforeach
    </select>
</label>
<label>Bakım tarihi<input type="date" name="maintenance_date" value="{{ old('maintenance_date', optional($maintenance->maintenance_date)->toDateString() ?? now()->toDateString()) }}" required></label>
<label>İşlem türü
    <select name="maintenance_type" required>
        <option value="">Seçiniz</option>
        @foreach($maintenanceTypes as $type)
            <option value="{{ $type }}" @selected(old('maintenance_type', $maintenance->maintenance_type) === $type)>{{ $type }}</option>
        @endforeach
    </select>
</label>
<label>Servis / Usta<input name="service_provider" value="{{ old('service_provider', $maintenance->service_provider) }}" maxlength="150" placeholder="İsteğe bağlı"></label>
<label>Maliyet (₺)<input type="number" name="cost" value="{{ old('cost', $maintenance->cost) }}" min="0" step="0.01" inputmode="decimal" required></label>
<label class="maintenance-meter-field">Mevcut kilometre
    <input type="number" name="meter_value" value="{{ old('meter_value', $maintenance->meter_value) }}" min="0" step="0.1" inputmode="decimal" placeholder="İsteğe bağlı">
</label>
<label class="maintenance-meter-field">Mevcut çalışma saati<input type="number" name="operating_hours" value="{{ old('operating_hours', $maintenance->operating_hours) }}" min="0" step="0.1" inputmode="decimal" placeholder="İsteğe bağlı"></label>
<label>Sonraki bakım tarihi<input type="date" name="next_maintenance_date" value="{{ old('next_maintenance_date', optional($maintenance->next_maintenance_date)->toDateString()) }}"></label>
<label class="maintenance-meter-field">Sonraki bakım kilometresi<input type="number" name="next_meter_value" value="{{ old('next_meter_value', $maintenance->next_meter_value) }}" min="0" step="0.1" inputmode="decimal" placeholder="İsteğe bağlı"></label>
<label class="maintenance-meter-field">Sonraki bakım çalışma saati<input type="number" name="next_operating_hours" value="{{ old('next_operating_hours', $maintenance->next_operating_hours) }}" min="0" step="0.1" inputmode="decimal" placeholder="İsteğe bağlı"></label>
<label class="full">Açıklama<textarea name="description" rows="3" maxlength="2000" required>{{ old('description', $maintenance->description) }}</textarea></label>
<label class="check full maintenance-expense">
    <input type="hidden" name="record_as_expense" value="0">
    <input type="checkbox" name="record_as_expense" value="1" @checked(old('record_as_expense', (bool) $maintenance->transaction_id))>
    <span>Bu maliyeti kasaya “Bakım / Onarım” gideri olarak da kaydet</span>
</label>
<label class="full">Fiş, fatura veya servis belgesi
    <small>JPG, PNG, WEBP veya PDF; en fazla 5 MB</small>
    <input type="file" name="document" accept=".jpg,.jpeg,.png,.webp,.pdf">
</label>
@if($maintenance->document_path)
    <div class="full current-document">
        <a href="{{ route('documents.maintenance', $maintenance) }}">Mevcut belgeyi indir</a>
        <label class="check"><input type="checkbox" name="remove_document" value="1"> <span>Mevcut belgeyi kaldır</span></label>
    </div>
@endif
<script>
(() => {
    const vehicle = document.querySelector('select[name="vehicle_id"]');
    const refreshMeterFields = () => {
        const enabled = vehicle.options[vehicle.selectedIndex]?.dataset.tracksMeters !== '0';
        document.querySelectorAll('.maintenance-meter-field').forEach(field => {
            field.hidden = !enabled;
            field.querySelector('input').disabled = !enabled;
        });
    };
    vehicle.addEventListener('change', refreshMeterFields);
    refreshMeterFields();
})();
</script>
