@extends('layouts.app')
@section('title', 'Kasa Hareketleri')
@section('heading', 'Kasa Hareketleri')
@section('content')
<div class="transactions-page">
<div class="page-title"><div><h1>Tüm Hareketler</h1><p>Personelin girdiği gelir ve gider kayıtları</p></div>@if(auth()->user()->hasPermission('transactions.create'))<a class="btn primary" href="{{ route('transactions.create') }}">+ Yeni Hareket</a>@endif</div>
<form class="filters" method="get"><select name="type"><option value="">Tüm türler</option><option value="income" @selected(request('type')==='income')>Gelir</option><option value="expense" @selected(request('type')==='expense')>Gider</option></select><input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}"><button class="btn secondary">Filtrele</button></form>
<section class="card table-wrap"><table><thead><tr><th>Tarih</th><th>Tür</th><th>Kategori / Açıklama</th><th>Personel</th><th>Tutar</th><th>Durum</th><th></th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->occurred_on->format('d.m.Y') }}</td><td><span class="badge {{ $item->type }}">{{ $item->type === 'income' ? 'Gelir' : 'Gider' }}</span></td><td><strong>{{ $item->category }}</strong><small class="table-description" title="{{ $item->description }}">{{ \Illuminate\Support\Str::limit($item->description, 100) }}</small>@if($item->document_path)<small><a href="{{ route('documents.transaction', $item) }}">Belgeyi indir</a></small>@endif</td><td>{{ $item->creator->name }}</td><td class="{{ $item->type === 'income' ? 'positive' : 'negative' }}">{{ number_format($item->amount, 2, ',', '.') }} ₺</td><td><span class="lock">🔒 Kilitli</span></td><td>@if(auth()->user()->hasPermission('transactions.manage'))<a href="{{ route('transactions.edit', $item) }}">Düzelt / Sil</a>@endif</td></tr>
@empty<tr><td colspan="7" class="empty">Kayıt bulunamadı.</td></tr>@endforelse
</tbody></table></section><div class="list-footer"><span>{{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} / {{ $items->total() }} kayıt</span>{{ $items->links() }}</div>
</div>
@endsection
