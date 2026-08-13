@extends('layouts.app')
@section('title', 'Tanker Stokları')
@section('heading', 'Tanker Stokları')
@section('content')
<div class="page-title"><div><h1>Tanker Yakıt Stokları</h1><p>Alımlar kasadan düşer; araç ikmalleri tanker stokundan düşer.</p></div><div class="page-actions">@if(auth()->user()->isAdmin())<a class="btn secondary" href="{{ route('tankers.manage') }}">Tankerleri Yönet</a>@endif <a class="btn primary" href="{{ route('tankers.purchase.create') }}">+ Tankere Yakıt Al</a></div></div>
<section class="stats tanker-stats">
@foreach($tankers as $tanker)
    <article @class(['featured' => $loop->first])><span>{{ $tanker->name }}</span><strong>{{ number_format($tanker->stock_liters, 3, ',', '.') }} L</strong><small>Son alış fiyatı: {{ number_format($tanker->average_unit_cost, 4, ',', '.') }} ₺/L<br>Tahmini stok değeri: {{ number_format($tanker->stock_liters * $tanker->average_unit_cost, 2, ',', '.') }} ₺</small></article>
@endforeach
</section>
<section class="card table-wrap"><div class="card-head"><h2>Stok Hareketleri</h2></div><table><thead><tr><th>Tarih</th><th>Tanker</th><th>İşlem</th><th>Araç / Kaynak</th><th>Litre</th><th>Birim Maliyet</th><th>Tutar</th><th>Kalan</th></tr></thead><tbody>
@forelse($movements as $item)<tr><td>{{ $item->movement_date->format('d.m.Y') }}<small>{{ substr((string)$item->movement_time, 0, 5) }}</small></td><td><strong>{{ $item->tanker->name }}</strong></td><td><span class="badge {{ $item->type === 'purchase' ? 'income' : 'expense' }}">{{ $item->type === 'purchase' ? 'Alım' : 'İkmal' }}</span></td><td>{{ $item->type === 'purchase' ? ($item->supplier ?: 'Tedarikçi') : $item->fuelEntry?->vehicle?->display_name }}</td><td>{{ $item->type === 'purchase' ? '+' : '−' }}{{ number_format($item->liters, 3, ',', '.') }} L</td><td>{{ number_format($item->unit_cost, 4, ',', '.') }} ₺</td><td>{{ number_format($item->total_amount, 2, ',', '.') }} ₺</td><td><strong>{{ number_format($item->balance_after, 3, ',', '.') }} L</strong></td></tr>
@empty<tr><td colspan="8" class="empty">Henüz tanker hareketi yok.</td></tr>@endforelse
</tbody></table></section>{{ $movements->links() }}
@endsection
