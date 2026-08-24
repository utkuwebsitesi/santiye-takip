<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['key', 'label', 'group', 'description', 'sort_order'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')->withTimestamps();
    }

    /** @return array<string, string> */
    public static function catalog(): array
    {
        return [
            'dashboard.view' => 'Gösterge panelini görüntüleme',
            'transactions.view' => 'Kasa hareketlerini görüntüleme',
            'transactions.create' => 'Gelir / gider kaydı ekleme',
            'transactions.manage' => 'Kasa kayıtlarını düzeltme / silme',
            'fuel.view' => 'Yakıt raporunu görüntüleme',
            'fuel.create' => 'Araç veya makine yakıt kaydı ekleme',
            'fuel.manage' => 'Yakıt kayıtlarını düzeltme / silme',
            'tankers.view' => 'Tanker stoklarını görüntüleme',
            'tankers.purchase' => 'Tankere yakıt alımı ekleme',
            'tankers.manage' => 'Tanker ekleme / düzenleme / silme',
            'maintenance.view' => 'Bakım / onarım kayıtlarını görüntüleme',
            'maintenance.create' => 'Bakım / onarım kaydı ekleme',
            'maintenance.manage' => 'Bakım kayıtlarını düzeltme / silme',
            'vehicles.view' => 'Araç ve makineleri görüntüleme',
            'vehicles.manage' => 'Araç / makine ekleme / düzenleme / silme',
            'reports.view' => 'Gelişmiş raporları görüntüleme',
            'notifications.view' => 'Bildirimleri görüntüleme',
            'audit.view' => 'Düzeltme / silme geçmişini görüntüleme',
            'users.manage' => 'Kullanıcı ve kullanıcı yetkilerini yönetme',
            'system.manage' => 'Sistem ayarlarını yönetme',
        ];
    }

    /** @return array<int, string> */
    public static function defaultKeysForRole(string $role): array
    {
        $all = array_keys(self::catalog());

        if ($role === 'super_admin') {
            return $all;
        }

        if ($role === 'admin') {
            return array_values(array_filter($all, fn (string $key): bool => $key !== 'system.manage'));
        }

        return [
            'dashboard.view', 'transactions.view', 'transactions.create',
            'fuel.view', 'fuel.create', 'tankers.view', 'tankers.purchase',
            'maintenance.view', 'maintenance.create', 'reports.view',
            'notifications.view',
        ];
    }
}
