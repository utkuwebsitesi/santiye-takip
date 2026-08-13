<label>Tür<select name="type" required><option value="vehicle" @selected(old('type', $vehicle->type ?? '')==='vehicle')>Araç</option><option value="machine" @selected(old('type', $vehicle->type ?? '')==='machine')>Makine</option></select></label>
<label>Ad<input name="name" value="{{ old('name', $vehicle->name ?? '') }}" required></label>
<label>Plaka (araç için)<input name="plate" value="{{ old('plate', $vehicle->plate ?? '') }}"></label>
<label>Makine kodu (makine için)<input name="code" value="{{ old('code', $vehicle->code ?? '') }}"></label>
<input type="hidden" name="tracks_meters" value="0">
<label class="check full meter-note"><input type="checkbox" name="tracks_meters" value="1" @checked(old('tracks_meters', $vehicle->tracks_meters ?? true))> Bu araçta kilometre ve çalışma saati takibi yap</label>
<label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vehicle->is_active ?? true))> Aktif</label>
