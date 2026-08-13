<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
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
        return view('users.create');
    }

    public function store(UserRequest $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        DB::transaction(function () use ($data, $audit): void {
            $user = User::create($data);
            $audit->created($user, 'Kullanıcı oluşturuldu.');
        });

        return redirect()->route('users.index')->with('success', 'Kullanıcı oluşturuldu.');
    }

    public function edit(User $user): View
    {
        abort_if($user->isSuperAdmin() && ! request()->user()->isSuperAdmin(), 403);

        return view('users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->guardAdminContinuity($request->user(), $user, $data);

        DB::transaction(function () use ($user, $data, $audit): void {
            $old = $user->getAttributes();
            $passwordReset = array_key_exists('password', $data);
            $user->update($data);
            $event = $passwordReset ? 'password_reset' : 'user_updated';
            $audit->event($user, $event, $old, $user->fresh()->getAttributes(), 'Yönetici kullanıcı bilgilerini güncelledi.');
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
