@extends('layouts.app')
@section('title', 'Düzeltme ve Silme Geçmişi')
@section('heading', 'Denetim Geçmişi')
@section('content')
<div class="page-title"><div><h1>Düzeltme ve Silme Geçmişi</h1><p>Kim, ne zaman, hangi kayıtta ne değiştirdi?</p></div></div>
<form class="filters wrap" method="get">
    <label>Başlangıç<input type="date" name="from" value="{{ request('from') }}"></label>
    <label>Bitiş<input type="date" name="to" value="{{ request('to') }}"></label>
    <label>Kullanıcı<select name="user_id"><option value="">Tümü</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id')==$user->id)>{{ $user->name }}</option>@endforeach</select></label>
    <label>İşlem<select name="event"><option value="">Tümü</option>@foreach($events as $event)<option value="{{ $event }}" @selected(request('event')===$event)>{{ \App\Models\AuditLog::eventLabelFor($event) }}</option>@endforeach</select></label>
    <label>Kayıt türü<select name="record_type"><option value="">Tümü</option>@foreach($recordTypes as $type)<option value="{{ $type }}" @selected(request('record_type')===$type)>{{ \App\Models\AuditLog::recordTypeLabelFor($type) }}</option>@endforeach</select></label>
    <button class="btn primary">Filtrele</button>
</form>
<section class="audit-list">
@forelse($logs as $log)
<article class="card audit-item">
    <div class="audit-heading"><span class="badge {{ $log->event === 'deleted' ? 'expense' : 'income' }}">{{ $log->eventLabel() }}</span><strong>{{ $log->recordTypeLabel() }} #{{ $log->auditable_id }}</strong></div>
    <p class="audit-reason"><b>İşlem açıklaması:</b> {{ $log->reason ?: 'Otomatik sistem işlemi' }}</p>
    <div class="audit-meta">
        <span><b>İşlemi yapan:</b> {{ $log->user?->name ?? 'Sistem' }}</span>
        <span><b>Tarih:</b> {{ $log->created_at->format('d.m.Y H:i') }}</span>
        <span><b>IP:</b> {{ $log->ip_address ?: '—' }}</span>
    </div>
    @php($changes = $log->readableChanges())
    @if($changes)
    <details class="audit-details" @if(count($changes) <= 3) open @endif>
        <summary>Değiştirilen alanları göster ({{ count($changes) }})</summary>
        <div class="audit-change-table">
            <div class="audit-change-head"><span>Alan</span><span>Önce</span><span>Sonra</span></div>
            @foreach($changes as $change)
            <div class="audit-change-row"><b>{{ $change['field'] }}</b><span class="old-value">{{ $change['old'] }}</span><span class="new-value">{{ $change['new'] }}</span></div>
            @endforeach
        </div>
    </details>
    @else
    <p class="audit-no-change">Bu işlemde gösterilecek alan değişikliği bulunmuyor.</p>
    @endif
</article>
@empty
<div class="card empty">Henüz düzeltme veya silme işlemi yok.</div>
@endforelse
</section>
{{ $logs->links() }}
@endsection
