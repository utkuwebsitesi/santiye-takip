<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Keeps permission assignment rules identical for the web and mobile APIs.
 * A missing permission form never falls back to a role's full default set.
 */
class UserPermissionService
{
    /** @return array<int, string> */
    public function forCreate(User $actor, string $role, ?array $requestedKeys, bool $formProvided): array
    {
        if (! $formProvided) {
            throw ValidationException::withMessages([
                'permission_form' => 'Kullanıcı yetkileri açıkça seçilmelidir.',
            ]);
        }

        return $this->resolve($actor, $role, $requestedKeys ?? []);
    }

    /** @return array<int, string>|null */
    public function forUpdate(User $actor, User $target, string $role, ?array $requestedKeys, bool $formProvided): ?array
    {
        // Legacy clients that do not send the form must preserve, not broaden,
        // the existing assignment.
        if (! $formProvided) {
            return null;
        }

        $keys = $this->resolve($actor, $role, $requestedKeys ?? []);
        if (! $actor->isSuperAdmin() && $actor->is($target)) {
            $current = $this->storedKeys($target);
            if ($this->normalize($current) !== $this->normalize($keys)) {
                throw ValidationException::withMessages([
                    'permission_keys' => 'Kendi yetkilerinizi değiştiremezsiniz.',
                ]);
            }
        }

        return $keys;
    }

    /** @return array{old: array<int, string>, new: array<int, string>} */
    public function sync(User $target, array $keys): array
    {
        $old = $this->storedKeys($target);
        $ids = Permission::query()->whereIn('key', $keys)->pluck('id')->all();
        $target->permissions()->sync($ids);
        $new = $this->storedKeys($target->fresh());

        return ['old' => $this->normalize($old), 'new' => $this->normalize($new)];
    }

    /** @return array<int, string> */
    public function storedKeys(User $user): array
    {
        return $user->permissions_configured === true
            ? $user->permissions()->pluck('key')->all()
            : Permission::defaultKeysForRole($user->role);
    }

    /** @param array<int, string> $keys @return array<int, string> */
    private function resolve(User $actor, string $role, array $keys): array
    {
        $keys = $this->normalize($keys);
        if ($role === 'super_admin' && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'Sistem yöneticisi yetkisi verilemez.',
            ]);
        }

        if (! $actor->isSuperAdmin()) {
            $owned = array_flip($actor->effectivePermissionKeys());
            $forbidden = array_values(array_diff($keys, array_keys($owned)));
            if ($forbidden !== []) {
                throw ValidationException::withMessages([
                    'permission_keys' => 'Sahip olmadığınız yetkileri başka bir kullanıcıya veremezsiniz.',
                ]);
            }
        }

        if ($role !== 'admin') {
            $keys = array_values(array_filter($keys, fn (string $key): bool => $key !== 'users.manage'));
        }
        if (! $actor->isSuperAdmin()) {
            $keys = array_values(array_filter($keys, fn (string $key): bool => $key !== 'system.manage'));
        }

        return $keys;
    }

    /** @param array<int, string> $keys @return array<int, string> */
    private function normalize(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, 'is_string')));
        sort($keys);

        return $keys;
    }
}
