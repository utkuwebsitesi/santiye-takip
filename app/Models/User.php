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

        if ($this->permissions_configured !== true) {
            return in_array($permission, Permission::defaultKeysForRole($this->role), true);
        }

        $keys = $this->permissions()->pluck('key')->all();
        if (in_array($permission, $keys, true)) {
            return true;
        }

        $impliedBy = [
            'transactions.view' => ['transactions.manage'],
            'fuel.view' => ['fuel.manage'],
            'tankers.view' => ['tankers.manage'],
            'maintenance.view' => ['maintenance.manage'],
            'vehicles.view' => ['vehicles.manage'],
            'notifications.view' => ['system.manage'],
            'audit.view' => ['users.manage'],
        ];

        return collect($impliedBy[$permission] ?? [])->intersect($keys)->isNotEmpty();
    }
}
