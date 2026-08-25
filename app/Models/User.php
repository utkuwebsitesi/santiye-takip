<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'password', 'role', 'is_active', 'permissions_configured'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'permissions_configured' => 'boolean'];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $keys = $this->permissions_configured === true
            ? $this->permissions()->pluck('key')->all()
            : Permission::defaultKeysForRole($this->role);
        if (in_array($permission, $keys, true)) {
            return true;
        }

        $impliedBy = [
            'transactions.view' => ['transactions.manage'],
            'fuel.view' => ['fuel.manage'],
            'maintenance.view' => ['maintenance.manage'],
            'vehicles.view' => ['vehicles.manage', 'vehicles.create', 'vehicles.update', 'vehicles.delete'],
            'vehicles.create' => ['vehicles.manage'],
            'vehicles.update' => ['vehicles.manage'],
            'vehicles.delete' => ['vehicles.manage'],
            'tankers.view' => ['tankers.manage', 'tankers.create', 'tankers.update', 'tankers.delete'],
            'tankers.create' => ['tankers.manage'],
            'tankers.update' => ['tankers.manage'],
            'tankers.delete' => ['tankers.manage'],
            'reports.view' => ['reports.cash.pdf', 'reports.cash.excel', 'reports.fuel.pdf', 'reports.fuel.excel'],
            'notifications.view' => ['system.manage'],
            'audit.view' => ['users.manage'],
        ];

        return collect($impliedBy[$permission] ?? [])->intersect($keys)->isNotEmpty();
    }

    /** @param array<int, string> $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function effectivePermissionKeys(): array
    {
        return array_values(array_filter(
            array_keys(Permission::catalog()),
            fn (string $permission): bool => $this->hasPermission($permission)
        ));
    }
}
