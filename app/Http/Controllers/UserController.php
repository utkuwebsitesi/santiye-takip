<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Models\Permission;
use App\Services\AuditService;
use App\Services\UserPermissionService;
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

    public function store(UserRequest $request, AuditService $audit, UserPermissionService $permissionService): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $permissionKeys = $permissionService->forCreate(
            $request->user(),
            $data['role'],
            $data['permission_keys'] ?? [],
            $request->boolean('permission_form')
        );
        $reason = trim((string) ($data['reason'] ?? 'Kullanıcı oluşturuldu.'));
        unset($data['permission_keys'], $data['permission_form'], $data['reason']);
        $data['permissions_configured'] = true;
        DB::transaction(function () use ($data, $audit, $permissionService, $permissionKeys, $reason): void {
            $user = User::create($data);
            $audit->created($user, $reason);
            $permissionChange = $permissionService->sync($user, $permissionKeys);
            $audit->event($user, 'permissions_updated',
                ['permissions' => $permissionChange['old']],
                ['permissions' => $permissionChange['new']],
                $reason
            );
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

    public function update(UserRequest $request, User $user, AuditService $audit, UserPermissionService $permissionService): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $oldRole = $user->role;
        $oldPermissions = $permissionService->storedKeys($user);
        $permissionKeys = $permissionService->forUpdate(
            $request->user(),
            $user,
            $data['role'],
            $data['permission_keys'] ?? [],
            $request->boolean('permission_form')
        );
        $oldPermissionComparison = $oldPermissions;
        $newPermissionComparison = $permissionKeys ?? $oldPermissions;
        sort($oldPermissionComparison);
        sort($newPermissionComparison);
        $permissionChanged = $oldRole !== $data['role'] || $oldPermissionComparison !== $newPermissionComparison;
        $reason = trim((string) ($data['reason'] ?? ''));
        unset($data['permission_keys'], $data['permission_form'], $data['reason']);
        if ($permissionKeys !== null) {
            $data['permissions_configured'] = true;
        } else {
            unset($data['permissions_configured']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->guardAdminContinuity($request->user(), $user, $data);
        if ($request->user()->is($user) && ! $request->user()->isSuperAdmin() && $oldRole !== $data['role']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'role' => 'Kendi rolünüzü değiştiremezsiniz.',
            ]);
        }
        if ($permissionChanged && mb_strlen($reason) < 5) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reason' => 'Rol veya yetki değişikliğinde en az 5 karakterlik gerekçe yazılmalıdır.',
            ]);
        }

        DB::transaction(function () use ($user, $data, $audit, $permissionService, $permissionKeys, $permissionChanged, $oldPermissions, $reason): void {
            $old = $user->getAttributes();
            $passwordReset = array_key_exists('password', $data);
            $user->update($data);
            if ($permissionKeys !== null) {
                $permissionChange = $permissionService->sync($user, $permissionKeys);
            } else {
                $permissionChange = ['old' => $oldPermissions, 'new' => $oldPermissions];
            }
            $event = $passwordReset ? 'password_reset' : 'user_updated';
            $audit->event($user, $event, $old, $user->fresh()->getAttributes(), $reason ?: 'Yönetici kullanıcı bilgilerini güncelledi.');
            if ($permissionChanged) {
                $audit->event($user, 'permissions_updated',
                    ['permissions' => $permissionChange['old']],
                    ['permissions' => $permissionChange['new']],
                    $reason
                );
            }
        });

        return redirect()->route('users.index')->with('success', 'Kullanıcı güncellendi.');
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
