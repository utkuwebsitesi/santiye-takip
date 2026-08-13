<label>Ad soyad<input name="name" value="{{ old('name', $user->name ?? '') }}" required></label>
<label>Kullanıcı adı<input name="username" value="{{ old('username', $user->username ?? '') }}" required></label>
<label>Rol<select name="role"><option value="personnel" @selected(old('role', $user->role ?? '')==='personnel')>Personel</option><option value="admin" @selected(old('role', $user->role ?? '')==='admin')>Şirket Yöneticisi</option>@if(auth()->user()->isSuperAdmin())<option value="super_admin" @selected(old('role', $user->role ?? '')==='super_admin')>Sistem Yöneticisi</option>@endif</select></label>
<label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))> Aktif hesap</label>
<label>Yeni parola {{ isset($user) ? '(değişmeyecekse boş bırakın)' : '' }}<input type="password" name="password" autocomplete="new-password" @required(!isset($user))></label>
<label>Parola tekrarı<input type="password" name="password_confirmation" autocomplete="new-password" @required(!isset($user))></label>
