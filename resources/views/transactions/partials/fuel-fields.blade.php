@php($fuel = $transaction->fuelEntry ?? null)
<input id="is-fuel-expense" type="hidden" name="is_fuel_expense" value="{{ old('category', $transaction->category) === 'Yakıt' || $fuel ? 1 : 0 }}">
<section id="fuel-expense-fields" class="fuel-expense-fields full" @if(old('category', $transaction->category) !== 'Yakıt' && ! $fuel) hidden @endif>
    <div class="fuel-head"><strong>Plakaya Yakıt Kaydı</strong><small>Araç yakıt geçmişine ve tüketim takibine işlenir; kasa bakiyesini etkilemez.</small></div>
    <div class="fuel-grid">
        <label class="full">Araç veya makine<select name="vehicle_id"><option value="">Seçiniz</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" data-tracks-meters="{{ $vehicle->tracks_meters ? 1 : 0 }}" @selected(old('vehicle_id', $fuel?->vehicle_id)==$vehicle->id)>{{ $vehicle->display_name }}</option>@endforeach</select></label>
        <label class="full">Yakıtın verileceği tanker<select name="tanker_id"><option value="">Tanker seçiniz</option>@foreach($tankers as $tanker)<option value="{{ $tanker->id }}" data-unit-cost="{{ $tanker->average_unit_cost }}" data-stock="{{ $tanker->stock_liters }}" @disabled((float)$tanker->stock_liters <= 0 && $fuel?->tanker_id !== $tanker->id) @selected(old('tanker_id', $fuel?->tanker_id)==$tanker->id)>{{ $tanker->name }} — {{ (float)$tanker->stock_liters > 0 ? 'Kalan: '.number_format($tanker->stock_liters, 3, ',', '.').' L' : 'Stok yok' }}</option>@endforeach</select><small>Stok yoksa önce <a href="{{ route('tankers.purchase.create') }}">tankere yakıt alımı girin</a>.</small></label>
        <label>Yakıt saati<input type="time" name="fuel_time" value="{{ old('fuel_time', $fuel ? substr((string) $fuel->fuel_time, 0, 5) : now()->format('H:i')) }}"></label>
        <label>Tankerden verilen litre<input type="number" name="liters" value="{{ old('liters', $fuel?->liters) }}" min="0.001" step="0.001" inputmode="decimal"></label>
        <label>Son alış birim maliyeti (₺)<input type="number" name="unit_price" value="{{ old('unit_price', $fuel?->unit_price) }}" min="0" step="0.0001" inputmode="decimal" readonly></label>
        <label class="meter-field">Kilometre<input type="number" name="meter_value" value="{{ old('meter_value', $fuel?->meter_value) }}" min="0" step="0.1" inputmode="decimal"></label>
        <label class="meter-field">Çalışma saati<input type="number" name="operating_hours" value="{{ old('operating_hours', $fuel?->operating_hours) }}" min="0" step="0.1" inputmode="decimal"></label>
        <label>Akaryakıt istasyonu<input name="station" value="{{ old('station', $fuel?->station) }}" maxlength="150"></label>
        <label class="full">Yakıt notu<textarea name="fuel_notes" rows="2" maxlength="1000">{{ old('fuel_notes', $fuel?->notes) }}</textarea></label>
        @if(auth()->user()->hasPermission('fuel.manage'))<label class="full"><span>Sayaç istisnası gerekçesi <small class="label-inline-note">(KM veya çalışma saati önceki değerden düşükse doldurun)</small></span><textarea name="meter_override_reason">{{ old('meter_override_reason') }}</textarea></label>@endif
    </div>
</section>
