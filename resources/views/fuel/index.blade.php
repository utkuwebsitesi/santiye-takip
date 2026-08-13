@extends('layouts.app')
@section('title', 'Yakıt Raporu')
@section('heading', 'Yakıt Raporu')
@section('content')
<div class="page-title"><div><h1>Plaka Bazlı Yakıt Geçmişi</h1><p>Tanker stokundan araç ve makinelere yapılan ikmaller ile maliyetleri</p></div><a class="btn primary" href="{{ route('fuel.create') }}">+ Araç İkmali</a></div>
<form class="filters" method="get"><select name="vehicle_id" data-auto-submit><option value="">Tüm araç ve makineler</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(request('vehicle_id')==$vehicle->id)>{{ $vehicle->display_name }}</option>@endforeach</select><input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}"><button class="btn secondary">Filtrele</button></form>
@if($selectedVehicle && $fuelSummary)
<section class="stats fuel-summary">
    <article><span>Seçili araç / makine</span><strong>{{ $selectedVehicle->plate ?? $selectedVehicle->code }}</strong><small>{{ $selectedVehicle->name }}</small></article>
    <article><span>Toplam yakıt</span><strong>{{ number_format($fuelSummary['liters'], 3, ',', '.') }} L</strong><small>Seçili tarih aralığında</small></article>
    <article><span>KM tüketimi</span><strong>{{ $fuelSummary['km_rate'] !== null ? number_format($fuelSummary['km_rate'], 2, ',', '.').' L' : '—' }}</strong><small>100 kilometrede</small></article>
    <article><span>Saatlik tüketim</span><strong>{{ $fuelSummary['hour_rate'] !== null ? number_format($fuelSummary['hour_rate'], 2, ',', '.').' L' : '—' }}</strong><small>Çalışma saatinde</small></article>
    <article class="featured"><span>Toplam maliyet</span><strong>{{ number_format($fuelSummary['amount'], 2, ',', '.') }} ₺</strong><small>Seçili kayıtlar</small></article>
</section>
@endif
<section class="card table-wrap"><table><thead><tr><th>Tarih</th><th>Plaka / Makine</th><th>Kaynak Tanker</th><th>Litre</th><th>Stok Maliyeti</th><th>Toplam</th><th>KM / Saat</th><th>Personel</th><th></th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->fuel_date->format('d.m.Y') }}<small>{{ substr((string) $item->fuel_time, 0, 5) }}</small></td><td><strong>{{ $item->vehicle->display_name }}</strong><small>{{ $item->station }}</small>@if($item->receipt_path)<small><a href="{{ route('documents.fuel', $item) }}">Fişi indir</a></small>@endif</td><td>{{ $item->tanker?->name ?? 'Eski kayıt' }}</td><td>{{ number_format($item->liters, 3, ',', '.') }} L</td><td>{{ number_format($item->unit_price, 4, ',', '.') }} ₺/L</td><td class="negative">{{ number_format($item->total_amount, 2, ',', '.') }} ₺</td><td>{{ $item->meter_value !== null ? number_format($item->meter_value, 1, ',', '.').' km' : '—' }}<small>{{ $item->operating_hours !== null ? number_format($item->operating_hours, 1, ',', '.').' saat' : '—' }}</small></td><td>{{ $item->creator->name }}</td><td>@if(auth()->user()->isAdmin())<a href="{{ route('fuel.edit', $item) }}">Düzelt / Sil</a>@endif</td></tr>
@empty<tr><td colspan="9" class="empty">Yakıt kaydı bulunamadı.</td></tr>@endforelse
</tbody></table></section>{{ $items->links() }}
@endsection
