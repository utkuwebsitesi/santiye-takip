@extends('layouts.app')
@section('title', 'Kullanıcı Yönetimi')
@section('heading', 'Kullanıcı Yönetimi')
@section('content')
<div class="page-title"><div><h1>Kullanıcılar</h1><p>Hesapları fiziksel olarak silmeden yönetin.</p></div><a class="btn primary" href="{{ route('users.create') }}">+ Yeni Kullanıcı</a></div>
<section class="card table-wrap"><table><thead><tr><th>Ad</th><th>Kullanıcı adı</th><th>Rol</th><th>Durum</th><th></th></tr></thead><tbody>
@foreach($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->username }}</td><td>{{ $user->isSuperAdmin() ? 'Sistem Yöneticisi' : ($user->isAdmin() ? 'Şirket Yöneticisi' : 'Personel') }}</td><td>{{ $user->is_active ? 'Aktif' : 'Pasif' }}</td><td><a href="{{ route('users.edit', $user) }}">Düzenle / Parola sıfırla</a></td></tr>@endforeach
</tbody></table></section>{{ $users->links() }}
@endsection
