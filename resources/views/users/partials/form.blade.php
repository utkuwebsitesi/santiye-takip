<label>Ad soyad<input name="name" value="{{ old('name', $user->name ?? '') }}" required></label>
<label>Kullanıcı adı<input name="username" value="{{ old('username', $user->username ?? '') }}" required></label>
@php
    $permissionRoleDefaults = collect(['personnel', 'admin', 'super_admin'])->mapWithKeys(fn (string $role): array => [$role => \App\Models\Permission::defaultKeysForRole($role)]);
@endphp
<label>Rol<select id="user-role" name="role"><option value="personnel" @selected(old('role', $user->role ?? '')==='personnel')>Personel</option><option value="admin" @selected(old('role', $user->role ?? '')==='admin')>Şirket Yöneticisi</option>@if(auth()->user()->isSuperAdmin())<option value="super_admin" @selected(old('role', $user->role ?? '')==='super_admin')>Sistem Yöneticisi</option>@endif</select></label>
<label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))> Aktif hesap</label>
<label>Yeni parola {{ isset($user) ? '(değişmeyecekse boş bırakın)' : '' }}<input type="password" name="password" autocomplete="new-password" @required(!isset($user))></label>
<label>Parola tekrarı<input type="password" name="password_confirmation" autocomplete="new-password" @required(!isset($user))></label>
<label class="full">Yetki değişikliği gerekçesi <small>(Rol veya yetki değiştirirken kısaca nedenini yazın.)</small><textarea name="reason" rows="2" minlength="5" maxlength="1000">{{ old('reason') }}</textarea></label>
<div class="full permission-panel" data-role-defaults='@json($permissionRoleDefaults)'>
    <input type="hidden" name="permission_form" value="1">
    <div class="permission-panel-head"><div><strong>Kullanıcı yetkileri</strong><small>Bu kullanıcı hangi ekranları ve işlemleri kullanabilsin?</small></div><span class="permission-hint">Seçimleri kaldırırsanız işlem engellenir.</span></div>
    <div class="permission-grid">
        @php
            $selectedPermissions = collect(old('permission_keys', isset($user)
                ? ($user->permissions_configured ? $user->permissions->pluck('key')->all() : \App\Models\Permission::defaultKeysForRole($user->role))
                : \App\Models\Permission::defaultKeysForRole(old('role', 'personnel'))));
        @endphp
        @foreach($permissions->groupBy('group') as $group => $groupPermissions)
            <fieldset class="permission-group">
                <legend>{{ $group }}</legend>
                @foreach($groupPermissions as $permission)
                    <label class="permission-option">
                        <input type="checkbox" name="permission_keys[]" value="{{ $permission->key }}" @checked($selectedPermissions->contains($permission->key)) @disabled($permission->key === 'system.manage' && !auth()->user()->isSuperAdmin())>
                        <span><strong>{{ $permission->label }}</strong>@if($permission->description)<small>{{ $permission->description }}</small>@endif</span>
                    </label>
                @endforeach
            </fieldset>
        @endforeach
    </div>
    <small class="permission-note">Şirket yöneticisi sistem ayarları yetkisini veremez. Sistem yöneticisi tüm yetkilere sahiptir.</small>
</div>
<script>
    (() => {
        const role = document.querySelector('#user-role');
        const panel = document.querySelector('.permission-panel');
        if (!role || !panel) return;
        let defaults = {};
        try { defaults = JSON.parse(panel.dataset.roleDefaults || '{}'); } catch (_) {}
        role.addEventListener('change', () => {
            const selected = new Set(defaults[role.value] || []);
            panel.querySelectorAll('input[name="permission_keys[]"]').forEach(input => {
                if (!input.disabled) input.checked = selected.has(input.value);
            });
        });
    })();
</script>
