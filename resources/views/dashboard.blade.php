@extends('layouts.app')
@section('title', 'Gösterge Paneli')
@section('heading', 'Gösterge Paneli')
@section('content')
<div class="dashboard-page poster-dashboard">
    <div class="page-title"><div><h1>Genel Durum</h1><p>Şantiyenizin finansı, yakıtı ve filosu tek ekranda.</p></div>@if(auth()->user()->hasPermission('transactions.create'))<a class="btn primary" href="{{ route('transactions.create') }}">+ Yeni Hareket</a>@endif</div>

    @php
        $sparkline = function (array $values): array {
            $minimum = min($values);
            $range = max(0.0001, max($values) - $minimum);
            $last = count($values) - 1;
            $points = collect($values)->map(function (float $value, int $index) use ($minimum, $range, $last): array {
                return [
                    'x' => 3 + (94 * $index / max(1, $last)),
                    'y' => 26 - (20 * (($value - $minimum) / $range)),
                ];
            });

            return [
                'line' => $points->map(fn (array $point) => number_format($point['x'], 2, '.', '').','.number_format($point['y'], 2, '.', ''))->implode(' '),
                'last' => $points->last(),
            ];
        };
    @endphp
    <section class="poster-stats" aria-label="Operasyon özeti">
        @foreach($dashboardMetrics as $key => $metric)
            @php
                $chart = $sparkline($metric['series']);
                $displayValue = match ($metric['format']) {
                    'currency' => number_format($metric['value'], 2, ',', '.').' ₺',
                    'liters' => number_format($metric['value'], 0, ',', '.').' L',
                    default => number_format($metric['value'], 0, ',', '.'),
                };
            @endphp
            <article class="poster-stat {{ $key }}">
                <span class="poster-stat-label"><i aria-hidden="true">{{ $metric['icon'] }}</i>{{ $metric['title'] }}</span>
                <strong>{{ $displayValue }}</strong>
                <small>{{ $metric['subtitle'] }}</small>
                <div class="poster-trend {{ $metric['trend']['tone'] }}"><b aria-hidden="true">{{ $metric['trend']['arrow'] }}</b><span>{{ $metric['trend']['text'] }}</span></div>
                <svg data-sparkline="{{ $key }}" viewBox="0 0 100 32" preserveAspectRatio="none" aria-label="Son yedi günlük {{ strtolower($metric['title']) }} eğilimi"><polyline points="{{ $chart['line'] }}"/><circle cx="{{ number_format($chart['last']['x'], 2, '.', '') }}" cy="{{ number_format($chart['last']['y'], 2, '.', '') }}" r="2.7"/></svg>
            </article>
        @endforeach
    </section>

    <section class="poster-dashboard-grid">
        <article class="poster-card poster-tanker-card">
            <div class="poster-card-head"><div><h2>Tanker Stok Durumu</h2><small>Güncel stok ve dağılım</small></div>@if(auth()->user()->hasPermission('tankers.view'))<a href="{{ route('tankers.index') }}">Tüm stoklar</a>@endif</div>
            <div class="poster-tanker-body">
                <div class="poster-tanker-visual"><img src="{{ asset('assets/dashboard-tanker-poster-transparent.png') }}" alt="Yakıt tankeri" loading="lazy"></div>
                <div class="poster-tanker-summary"><strong>{{ number_format($totalTankerStock, 0, ',', '.') }} L</strong><span>Mevcut stok</span><div class="poster-stock-rows">
                    @forelse($tankers as $tanker)
                    <div><span>{{ $tanker->name }}</span><b>{{ number_format($tanker->stock_liters, 0, ',', '.') }} L</b><i><em style="width: {{ min(100, round(((float) $tanker->stock_liters / $highestTankerStock) * 100)) }}%"></em></i></div>
                    @empty
                    <p class="empty">Henüz tanker tanımı yok.</p>
                    @endforelse
                </div></div>
            </div>
        </article>

        <article class="poster-card poster-fleet-card">
            <div class="poster-card-head"><div><h2>Araç &amp; Makine Yakıt Takibi</h2><small>Son dağıtımlar</small></div>@if(auth()->user()->hasPermission('fuel.view'))<a href="{{ route('fuel.index') }}">Yakıt raporu</a>@endif</div>
            <div class="poster-fleet-list">
                @forelse($recentFuel->take(4) as $item)
                <div class="poster-fleet-row"><span class="poster-fleet-icon">{{ $item->vehicle->type === 'machine' ? '⚙' : '▰' }}</span><div><strong>{{ $item->vehicle->display_name }}</strong><small>{{ $item->fuel_date->format('d.m.Y') }} · {{ $item->tanker?->name ?? 'Tanker seçilmedi' }}</small></div><i><em style="width: {{ min(100, round(((float) $item->liters / $recentFuelPeak) * 100)) }}%"></em></i><b>{{ number_format($item->liters, 0, ',', '.') }} L</b></div>
                @empty
                <p class="empty">Henüz yakıt dağıtımı yok.</p>
                @endforelse
            </div>
        </article>

        <article class="poster-card poster-maintenance-card">
            <div class="poster-card-head"><div><h2>Bakım Hatırlatmaları</h2><small>Yaklaşan ve geciken işlemler</small></div>@if(auth()->user()->hasPermission('maintenance.view'))<a href="{{ route('maintenance.index') }}">Bakımı gör</a>@endif</div>
            <div class="poster-maintenance-list">
                @forelse($dueMaintenance as $item)
                <div><span @class(['poster-maintenance-alert', 'is-critical' => $loop->first])>!</span><strong>{{ $item->vehicle->display_name }}</strong><span>{{ $item->maintenance_type }} · {{ $item->reminder_reasons->first() }}</span><b>Takipte</b></div>
                @empty
                <p class="empty">Yaklaşan bakım uyarısı yok.</p>
                @endforelse
            </div>
        </article>
    </section>

    <div class="grid two poster-detail-grid">
    <section class="card dashboard-recent-card"><div class="card-head"><div><h2>Son Kasa Hareketleri</h2><small>Son 25 kayıt · en yeni üstte</small></div>@if(auth()->user()->hasPermission('transactions.view'))<a href="{{ route('transactions.index') }}">Tümünü Gör</a>@endif</div>
        <div class="compact-transaction-grid" role="list" aria-label="Son kasa hareketleri">
        @forelse($recentTransactions as $item)
            <article class="compact-transaction-row" role="listitem">
                <time datetime="{{ $item->occurred_on->toDateString() }}">{{ $item->occurred_on->format('d.m.Y') }}</time>
                <div class="compact-transaction-main"><strong>{{ $item->category }}</strong><small title="{{ $item->description }}">{{ \Illuminate\Support\Str::limit($item->description, 58) }}</small><em>{{ $item->creator->name }}</em></div>
                <b class="{{ $item->type === 'income' ? 'positive' : 'negative' }}">{{ $item->type === 'income' ? '+' : '-' }}{{ number_format($item->amount, 2, ',', '.') }} ₺</b>
            </article>
        @empty
            <p class="empty">Henüz kasa hareketi yok.</p>
        @endforelse
        </div>
    </section>
    <section class="card"><div class="card-head"><h2>Son Yakıt Kayıtları</h2>@if(auth()->user()->hasPermission('fuel.view'))<a href="{{ route('fuel.index') }}">Tümünü Gör</a>@endif</div>
        <div class="table-wrap"><table><thead><tr><th>Tarih</th><th>Araç / Makine</th><th>Litre</th><th>Tutar</th></tr></thead><tbody>
        @forelse($recentFuel as $item)<tr><td>{{ $item->fuel_date->format('d.m.Y') }}</td><td>{{ $item->vehicle->display_name }}</td><td>{{ number_format($item->liters, 2, ',', '.') }} L</td><td class="negative">{{ number_format($item->total_amount, 2, ',', '.') }} ₺</td></tr>
        @empty<tr><td colspan="4" class="empty">Henüz yakıt kaydı yok.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
</div>
@endsection
