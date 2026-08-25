@extends('layouts.app')
@section('title', 'Araç ve Makineler')
@section('heading', 'Araç ve Makineler')
@section('content')
<div class="page-title"><div><h1>Araç ve Makine Tanımları</h1><p>Yetkileri ayrı ayrı vererek ekleme, düzenleme ve silme işlemlerini kontrol edin.</p></div>@if(auth()->user()->hasPermission('vehicles.create'))<a class="btn primary" href="{{ route('araclar.create') }}">+ Yeni Tanım</a>@endif</div>
<section class="card table-wrap"><table><thead><tr><th>Tür</th><th>Plaka / Kod</th><th>Ad</th><th>Takip</th><th>Durum</th><th></th></tr></thead><tbody>
@foreach($vehicles as $vehicle)<tr><td>{{ $vehicle->type === 'vehicle' ? 'Araç' : 'Makine' }}</td><td>{{ $vehicle->plate ?? $vehicle->code }}</td><td>{{ $vehicle->name }}</td><td>{{ $vehicle->tracks_meters ? 'KM + çalışma saati' : 'Sayaç takibi yok' }}</td><td>{{ $vehicle->is_active ? 'Aktif' : 'Pasif' }}</td><td><div class="table-actions">@if(auth()->user()->hasPermission('vehicles.update'))<a href="{{ route('araclar.edit', $vehicle) }}">Düzenle</a>@endif @if(auth()->user()->hasPermission('vehicles.delete'))<form method="post" action="{{ route('araclar.destroy', $vehicle) }}" data-confirm="{{ $vehicle->display_name }} tanımını silmek istediğinize emin misiniz?">@csrf @method('DELETE')<button class="link-button danger-text">Sil</button></form>@endif</div></td></tr>@endforeach
</tbody></table></section>{{ $vehicles->links() }}
@endsection
