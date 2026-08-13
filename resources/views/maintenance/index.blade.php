@extends('layouts.app')
@section('title', 'Bakım / Onarım Takibi')
@section('heading', 'Bakım / Onarım')
@section('content')
<div class="maintenance-page">
    <div class="page-title"><div><h1>Araç Bakım Takibi</h1><p>Bakım geçmişi, maliyetler ve yaklaşan bakım hatırlatmaları.</p></div><a class="btn primary" href="{{ route('maintenance.create') }}">+ Yeni Bakım</a></div>
    <form class="filters wrap" method="get">
        <label>Araç / Makine<select name="vehicle_id"><option value="">Tümü</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(request('vehicle_id') == $vehicle->id)>{{ $vehicle->plate ?? $vehicle->code }} · {{ $vehicle->name }}</option>@endforeach</select></label>
        <label>Başlangıç<input type="date" name="from" value="{{ request('from') }}"></label>
        <label>Bitiş<input type="date" name="to" value="{{ request('to') }}"></label>
        <button class="btn secondary">Filtrele</button>
        @if(request()->hasAny(['vehicle_id', 'from', 'to']))<a class="btn secondary" href="{{ route('maintenance.index') }}">Temizle</a>@endif
    </form>
    <section class="card table-wrap"><table>
        <thead><tr><th>Tarih</th><th>Araç / Makine</th><th>İşlem</th><th>Maliyet</th><th>Sonraki bakım</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        @forelse($entries as $entry)
            @php
                $currentMeter = (float) ($currentMeters[$entry->vehicle_id] ?? 0);
                $currentHour = (float) ($currentHours[$entry->vehicle_id] ?? 0);
                $dateDue = $entry->next_maintenance_date?->isPast() === true;
                $meterDue = $entry->next_meter_value !== null && $currentMeter >= (float) $entry->next_meter_value;
                $hourDue = $entry->next_operating_hours !== null && $currentHour >= (float) $entry->next_operating_hours;
                $due = $dateDue || $meterDue || $hourDue;
            @endphp
            <tr>
                <td>{{ $entry->maintenance_date->format('d.m.Y') }}</td>
                <td><strong>{{ $entry->vehicle->plate ?? $entry->vehicle->code }}</strong><small>{{ $entry->vehicle->name }}</small></td>
                <td>{{ $entry->maintenance_type }}<small>{{ \Illuminate\Support\Str::limit($entry->description, 55) }}</small></td>
                <td>{{ number_format($entry->cost, 2, ',', '.') }} ₺<small>{{ $entry->transaction_id ? 'Kasaya işlendi' : 'Yalnız takip' }}</small></td>
                <td>
                    {{ $entry->next_maintenance_date?->format('d.m.Y') ?? '—' }}
                    @if($entry->next_meter_value)<small>{{ number_format($entry->next_meter_value, 0, ',', '.') }} km</small>@endif
                    @if($entry->next_operating_hours)<small>{{ number_format($entry->next_operating_hours, 1, ',', '.') }} saat</small>@endif
                </td>
                <td><span class="badge {{ $due ? 'due' : 'planned' }}">{{ $due ? 'Bakım zamanı' : ($entry->next_maintenance_date || $entry->next_meter_value || $entry->next_operating_hours ? 'Planlandı' : 'Takvim yok') }}</span></td>
                <td class="maintenance-actions">
                    @if($entry->document_path)<a href="{{ route('documents.maintenance', $entry) }}">Belge</a>@endif
                    @if(auth()->user()->isAdmin())<a href="{{ route('maintenance.edit', $entry) }}">Düzenle</a>@endif
                </td>
            </tr>
        @empty
            <tr><td class="empty" colspan="7">Henüz bakım veya onarım kaydı yok.</td></tr>
        @endforelse
        </tbody>
    </table></section>
    {{ $entries->links() }}
</div>
@endsection
