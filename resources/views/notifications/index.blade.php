@extends('layouts.app')
@section('title', 'Bildirim Geçmişi')
@section('heading', 'Bildirimler')
@section('content')
<div class="page-title"><div><h1>Bildirim Geçmişi</h1><p>Bakım tarihi, kilometre ve çalışma saati uyarıları.</p></div><form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn secondary">Tümünü Okundu İşaretle</button></form></div>
<section class="card notification-history">
@forelse($notifications as $notification)
    <a href="{{ route('notifications.open', $notification) }}" @class(['notification-row', 'unread' => !$notification->read_at])>
        <span class="notification-dot"></span>
        <span><strong>{{ $notification->title }}</strong><small>{{ $notification->message }}</small></span>
        <time>{{ $notification->created_at->format('d.m.Y H:i') }}</time>
    </a>
@empty
    <p class="empty">Henüz bildirim bulunmuyor.</p>
@endforelse
</section>
{{ $notifications->links() }}
@endsection
