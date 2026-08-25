@extends('layouts.app')
@section('title', 'Gelişmiş Raporlar')
@section('heading', 'Gelişmiş Raporlar')
@section('content')
<div class="reports-page">
    <div class="reports-hero">
        <div><span class="reports-kicker">Rapor Merkezi</span><h1>Kasa ve Yakıt Raporları</h1><p>Tarih, kategori, personel ve araç bazında filtreleyin.</p></div>
        @if($canTransactions || $canFuel)
        <div class="reports-hero-meta" aria-label="Rapor kayıt özeti">
            @if($canTransactions)<div class="report-summary-metric report-summary-metric-cash"><span class="report-summary-icon" aria-hidden="true">₺</span><span class="report-summary-copy"><strong>{{ $transactions->total() }}</strong><small>Kasa kaydı</small></span></div>@endif
            @if($canTransactions && $canFuel)<span class="report-summary-divider" aria-hidden="true"></span>@endif
            @if($canFuel)<div class="report-summary-metric report-summary-metric-fuel"><span class="report-summary-icon" aria-hidden="true">L</span><span class="report-summary-copy"><strong>{{ $fuel->total() }}</strong><small>Yakıt kaydı</small></span></div>@endif
        </div>
        @endif
    </div>
    <nav class="report-section-nav" aria-label="Rapor bölümleri">
        @if($canTransactions)<a href="#cash-report">Kasa hareketleri</a>@endif
        @if($canFuel)<a href="#fuel-report">Yakıt geçmişi</a><a href="#fuel-summary">Yakıt özetleri</a>@endif
        @if($canTankers)<a href="#tanker-report">Tanker stokları</a>@endif
        @if($canMaintenance)<a href="#maintenance-report">Bakım geçmişi</a>@endif
    </nav>
    <form class="filters wrap report-filters" method="get">
        <div class="report-filter-heading"><div><strong>Filtreleri daralt</strong><small>İhtiyacınız olan tarih ve varlıkları seçin.</small></div><a class="report-reset" href="{{ route('reports.index') }}">Temizle</a></div>
        <div class="report-filter-fields">
            <label>Başlangıç<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>Bitiş<input type="date" name="to" value="{{ request('to') }}"></label>
            @if($canTransactions)
            <label>Kategori<select name="category"><option value="">Tümü</option>@foreach($categories as $category)<option @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></label>
            <label>Personel<select name="created_by"><option value="">Tümü</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('created_by')==$user->id)>{{ $user->name }}</option>@endforeach</select></label>
            @endif
            @if($canFuel || $canMaintenance)<label>Araç / Makine<select name="vehicle_id"><option value="">Tümü</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(request('vehicle_id')==$vehicle->id)>{{ $vehicle->display_name }}</option>@endforeach</select></label>@endif
            @if(auth()->user()->hasPermission('transactions.manage') || auth()->user()->hasPermission('fuel.manage') || auth()->user()->hasPermission('maintenance.manage'))<label class="check"><input type="checkbox" name="with_deleted" value="1" @checked(request('with_deleted'))> Silinenleri dahil et</label>@endif
            <button class="btn primary">Raporla</button>
        </div>
    </form>
    <div class="report-export-bar">
        <div class="report-export-heading"><strong>Dışa aktar</strong><small>Filtrelenmiş kayıtları indirin.</small></div>
        <div class="report-export-groups">
            @if($canTransactions && auth()->user()->hasAnyPermission(['reports.cash.pdf', 'reports.cash.excel']))<div class="report-export-item"><span class="report-export-label"><i aria-hidden="true">₺</i>Kasa hareketleri</span><span class="report-export-actions">@if(auth()->user()->hasPermission('reports.cash.pdf'))<a class="btn secondary" href="{{ route('reports.cash.pdf', request()->query()) }}">PDF</a>@endif @if(auth()->user()->hasPermission('reports.cash.excel'))<a class="btn secondary" href="{{ route('reports.cash.excel', request()->query()) }}">Excel</a>@endif</span></div>@endif
            @if($canFuel && auth()->user()->hasAnyPermission(['reports.fuel.pdf', 'reports.fuel.excel']))<div class="report-export-item"><span class="report-export-label"><i aria-hidden="true">L</i>Yakıt raporu</span><span class="report-export-actions">@if(auth()->user()->hasPermission('reports.fuel.pdf'))<a class="btn secondary" href="{{ route('reports.fuel.pdf', request()->query()) }}">PDF</a>@endif @if(auth()->user()->hasPermission('reports.fuel.excel'))<a class="btn secondary" href="{{ route('reports.fuel.excel', request()->query()) }}">Excel</a>@endif</span></div>@endif
        </div>
    </div>
    @if(!$canTransactions && !$canFuel && !$canTankers && !$canMaintenance)<div class="alert info">Bu rapor için görüntüleme yetkiniz bulunmuyor.</div>@endif

    @if($canTransactions)
    <section id="cash-report" class="card table-wrap report-primary-card"><div class="card-head"><div><h2>Kasa Hareketleri</h2><small>{{ $transactions->total() }} kayıt · en yeni üstte</small></div><a href="{{ route('transactions.index') }}">Tümünü gör</a></div><table class="report-data-table report-transactions"><thead><tr><th>Tarih</th><th>Tür</th><th>Kategori / Açıklama</th><th>Personel</th><th>Tutar</th></tr></thead><tbody>
        @forelse($transactions as $item)<tr @class(['deleted-row' => $item->trashed()])><td data-label="Tarih"><span class="report-cell-content">{{ $item->occurred_on->format('d.m.Y') }} @if($item->trashed())<small class="report-status">Silinmiş</small>@endif</span></td><td data-label="Tür"><span class="badge {{ $item->type }}">{{ $item->type==='income'?'Gelir':'Gider' }}</span></td><td data-label="Kategori / Açıklama"><span class="report-cell-content"><strong>{{ $item->category }}</strong><small class="table-description">{{ $item->description }}</small></span></td><td data-label="Personel">{{ $item->creator?->name ?? '—' }}</td><td data-label="Tutar" class="numeric">{{ number_format($item->amount,2,',','.') }} ₺</td></tr>@empty<tr><td colspan="5" class="empty">Kayıt bulunamadı.</td></tr>@endforelse
    </tbody></table>{{ $transactions->links() }}</section>
    @endif

    @if($canFuel)
    <section id="fuel-report" class="card table-wrap report-primary-card"><div class="card-head"><div><h2>Yakıt Geçmişi</h2><small>{{ $fuel->total() }} kayıt · en yeni üstte</small></div><a href="{{ route('fuel.index') }}">Tümünü gör</a></div><table class="report-data-table report-fuel"><thead><tr><th>Tarih</th><th>Araç / Makine</th><th>Litre</th><th>Tutar</th></tr></thead><tbody>
        @forelse($fuel as $item)<tr @class(['deleted-row' => $item->trashed()])><td data-label="Tarih"><span class="report-cell-content">{{ $item->fuel_date->format('d.m.Y') }} @if($item->trashed())<small class="report-status">Silinmiş</small>@endif</span></td><td data-label="Araç / Makine"><strong>{{ $item->vehicle?->display_name ?? '—' }}</strong></td><td data-label="Litre" class="numeric">{{ number_format($item->liters,3,',','.') }} L</td><td data-label="Tutar" class="numeric">{{ number_format($item->total_amount,2,',','.') }} ₺</td></tr>@empty<tr><td colspan="4" class="empty">Kayıt bulunamadı.</td></tr>@endforelse
    </tbody></table>{{ $fuel->links() }}</section>

    <div id="fuel-summary" class="grid two report-summary">
        <section class="card table-wrap"><div class="card-head"><h2>Günlük Yakıt Toplamları</h2></div><table class="report-summary-table"><thead><tr><th>Gün</th><th class="numeric">Litre</th><th class="numeric">Tutar</th></tr></thead><tbody>@foreach($fuelTotals as $row)<tr><td data-label="Gün">{{ $row->period }}</td><td data-label="Litre" class="numeric">{{ number_format($row->liters,3,',','.') }}</td><td data-label="Tutar" class="numeric">{{ number_format($row->amount,2,',','.') }} ₺</td></tr>@endforeach</tbody></table></section>
        <section class="card table-wrap"><div class="card-head"><h2>Aylık Yakıt Toplamları</h2></div><table class="report-summary-table"><thead><tr><th>Ay</th><th class="numeric">Litre</th><th class="numeric">Tutar</th></tr></thead><tbody>@foreach($monthlyFuelTotals as $row)<tr><td data-label="Ay">{{ $row->period }}</td><td data-label="Litre" class="numeric">{{ number_format($row->liters,3,',','.') }}</td><td data-label="Tutar" class="numeric">{{ number_format($row->amount,2,',','.') }} ₺</td></tr>@endforeach</tbody></table></section>
    </div>
    @endif

    @if($canTankers)<section id="tanker-report" class="card table-wrap report-tanker-summary"><div class="card-head"><h2>Tanker Stok Özeti</h2><a href="{{ route('tankers.index') }}">Stok hareketlerine git</a></div><table class="report-tanker-table"><thead><tr><th>Tanker</th><th class="numeric">Mevcut Stok</th><th class="numeric">Son Alış Birim Maliyeti</th><th class="numeric">Tahmini Stok Değeri</th></tr></thead><tbody>@forelse($tankers as $tanker)<tr><td data-label="Tanker"><strong>{{ $tanker->name }}</strong></td><td data-label="Mevcut stok" class="numeric">{{ number_format($tanker->stock_liters,3,',','.') }} L</td><td data-label="Birim maliyeti" class="numeric">{{ number_format($tanker->average_unit_cost,4,',','.') }} ₺/L</td><td data-label="Tahmini değer" class="numeric">{{ number_format($tanker->stock_liters * $tanker->average_unit_cost,2,',','.') }} ₺</td></tr>@empty<tr><td colspan="4" class="empty">Aktif tanker bulunamadı.</td></tr>@endforelse</tbody></table></section>@endif

    @if($canMaintenance)<section id="maintenance-report" class="card table-wrap report-maintenance-history"><div class="card-head"><div><h2>Araç / Makine Bakım Geçmişi</h2><small>Gider kaleminden ve bakım ekranından eklenen kayıtlar</small></div><a href="{{ route('maintenance.index', request()->only(['vehicle_id', 'from', 'to'])) }}">Bakım sayfasına git</a></div><table class="report-maintenance-table"><thead><tr><th>Tarih</th><th>Araç / Makine</th><th>İşlem</th><th>Servis / Usta</th><th class="numeric">Maliyet</th><th>Durum</th></tr></thead><tbody>@forelse($maintenance as $entry)<tr @class(['deleted-row' => $entry->trashed()])><td data-label="Tarih">{{ $entry->maintenance_date->format('d.m.Y') }} @if($entry->trashed())<small>Silinmiş</small>@endif</td><td data-label="Araç / Makine"><strong>{{ $entry->vehicle?->display_name ?? '—' }}</strong></td><td data-label="İşlem">{{ $entry->maintenance_type }}<small>{{ $entry->description }}</small></td><td data-label="Servis / Usta">{{ $entry->service_provider ?: '—' }}</td><td data-label="Maliyet" class="numeric">{{ number_format($entry->cost,2,',','.') }} ₺</td><td data-label="Durum"><small>{{ $entry->transaction_id ? 'Kasaya işlendi' : 'Yalnız takip' }}</small></td></tr>@empty<tr><td colspan="6" class="empty">Seçilen filtrelerde bakım veya onarım kaydı bulunamadı.</td></tr>@endforelse</tbody></table>{{ $maintenance->links() }}</section>@endif

    @if($canFuel)<section class="card table-wrap efficiency-card"><div class="card-head"><h2>Tüketim Verimliliği</h2></div><table class="efficiency-table"><thead><tr><th>Araç / Makine</th><th class="numeric">Ortalama</th></tr></thead><tbody>@forelse($efficiency as $row)<tr><td data-label="Araç / Makine">{{ $row->vehicle?->display_name ?? '—' }}</td><td data-label="Ortalama" class="numeric">@if($row->km_rate === null && $row->hour_rate === null)Yeterli sayaç verisi yok@else @if($row->km_rate !== null){{ number_format($row->km_rate,2,',','.') }} L/100 km @endif @if($row->hour_rate !== null)<small>{{ number_format($row->hour_rate,2,',','.') }} L/saat</small>@endif @endif</td></tr>@empty<tr><td colspan="2" class="empty">Hesaplanabilir sayaç verisi yok.</td></tr>@endforelse</tbody></table></section>@endif
</div>
@endsection
