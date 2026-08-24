<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Models\Permission;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::when(! request()->user()->isSuperAdmin(), fn ($query) => $query->where('role', '!=', 'super_admin'))
                ->orderByDesc('is_active')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['permissions' => Permission::orderBy('group')->orderBy('sort_order')->get()]);
    }

    public function store(UserRequest $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $permissionKeys = $this->permissionKeys($request, $data['role']) ?? Permission::defaultKeysForRole($data['role']);
        unset($data['permission_keys'], $data['permission_form']);
        $data['permissions_configured'] = true;
        DB::transaction(function () use ($data, $audit, $permissionKeys): void {
            $user = User::create($data);
            $audit->created($user, 'Kullanıcı oluşturuldu.');
            $this->syncPermissions($user, $permissionKeys);
        });

        return redirect()->route('users.index')->with('success', 'Kullanıcı oluşturuldu.');
    }

    public function edit(User $user): View
    {
        abort_if($user->isSuperAdmin() && ! request()->user()->isSuperAdmin(), 403);

        return view('users.edit', [
            'user' => $user->load('permissions'),
            'permissions' => Permission::orderBy('group')->orderBy('sort_order')->get(),
        ]);
    }

    public function update(UserRequest $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $permissionKeys = $this->permissionKeys($request, $data['role']);
        unset($data['permission_keys'], $data['permission_form']);
        if ($permissionKeys !== null) {
            $data['permissions_configured'] = true;
        } else {
            unset($data['permissions_configured']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->guardAdminContinuity($request->user(), $user, $data);

        DB::transaction(function () use ($user, $data, $audit, $permissionKeys): void {
            $old = $user->getAttributes();
            $passwordReset = array_key_exists('password', $data);
            $user->update($data);
            if ($permissionKeys !== null) {
                $this->syncPermissions($user, $permissionKeys);
            }
            $event = $passwordReset ? 'password_reset' : 'user_updated';
            $audit->event($user, $event, $old, $user->fresh()->getAttributes(), 'Yönetici kullanıcı bilgilerini güncelledi.');
        });

        return redirect()->route('users.index')->with('success', 'Kullanıcı güncellendi.');
    }

    /** @return array<int, string>|null */
    private function permissionKeys(UserRequest $request, string $role): ?array
    {
        if (! $request->boolean('permission_form')) {
            return null;
        }

        $keys = array_values(array_unique($request->validated('permission_keys', [])));
        if ($role === 'super_admin') {
            return Permission::defaultKeysForRole('super_admin');
        }

        return array_values(array_filter($keys, fn (string $key): bool => $key !== 'system.manage' && ($role === 'admin' || $key !== 'users.manage')));
    }

    /** @param array<int, string> $keys */
    private function syncPermissions(User $user, array $keys): void
    {
        $ids = Permission::query()->whereIn('key', $keys)->pluck('id')->all();
        $user->permissions()->sync($ids);
    }

    private function guardAdminContinuity(User $actor, User $target, array $data): void
    {
        abort_if($target->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
        abort_if($actor->is($target) && ($data['is_active'] === false), 422, 'Kendi hesabınızı pasifleştiremezsiniz.');

        $removesSuperAdmin = $target->isSuperAdmin() && (! $data['is_active'] || $data['role'] !== 'super_admin');
        if ($removesSuperAdmin) {
            $otherSuperAdmins = User::whereKeyNot($target->id)->where('role', 'super_admin')->where('is_active', true)->exists();
            abort_unless($otherSuperAdmins, 422, 'Sistemdeki son aktif sistem yöneticisi değiştirilemez.');
        }

        $removesAdmin = $target->role === 'admin' && (! $data['is_active'] || $data['role'] !== 'admin');
        if ($removesAdmin) {
            $otherActiveAdmins = User::whereKeyNot($target->id)->where('role', 'admin')->where('is_active', true)->exists();
            abort_unless($otherActiveAdmins, 422, 'Sistemdeki son aktif yönetici pasifleştirilemez veya personel yapılamaz.');
        }
    }
}
