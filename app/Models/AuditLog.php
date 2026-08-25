<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Denetim kayıtları değiştirilemez.'));
        static::deleting(fn () => throw new \LogicException('Denetim kayıtları silinemez.'));
    }

    protected $fillable = [
        'auditable_type', 'auditable_id', 'event', 'old_values',
        'new_values', 'reason', 'user_id', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return self::eventLabelFor($this->event);
    }

    public static function eventLabelFor(string $event): string
    {
        return match ($event) {
            'created' => 'Oluşturuldu',
            'updated' => 'Düzeltildi',
            'deleted' => 'Silindi',
            'document_changed' => 'Belge değiştirildi',
            'password_changed' => 'Parola değiştirildi',
            'password_reset' => 'Parola sıfırlandı',
            'user_updated' => 'Kullanıcı güncellendi',
            'permissions_updated' => 'Yetkiler güncellendi',
            'user_activated' => 'Kullanıcı etkinleştirildi',
            'user_deactivated' => 'Kullanıcı pasifleştirildi',
            default => 'Sistem işlemi',
        };
    }

    public function recordTypeLabel(): string
    {
        return self::recordTypeLabelFor($this->auditable_type);
    }

    public static function recordTypeLabelFor(string $type): string
    {
        return match (class_basename($type)) {
            'Transaction' => 'Kasa hareketi',
            'FuelEntry' => 'Yakıt kaydı',
            'MaintenanceEntry' => 'Bakım kaydı',
            'Vehicle' => 'Araç / makine',
            'User' => 'Kullanıcı',
            'Tanker' => 'Tanker',
            'TankerMovement' => 'Tanker hareketi',
            'TransactionCategory' => 'Kategori',
            'NavigationItem' => 'Menü bölümü',
            'AppSetting' => 'Sistem ayarı',
            default => 'Kayıt',
        };
    }

    public function readableChanges(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $ignored = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by'];
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($this->event === 'updated' && $oldValue == $newValue) {
                continue;
            }

            $changes[] = [
                'field' => $this->fieldLabel($key),
                'old' => $this->formatValue($key, $oldValue),
                'new' => $this->formatValue($key, $newValue),
            ];
        }

        return $changes;
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'type' => 'İşlem türü',
            'category' => 'Kategori',
            'description' => 'Açıklama',
            'amount' => 'Tutar',
            'affects_cash' => 'Kasayı etkiler',
            'occurred_on' => 'İşlem tarihi',
            'document_path', 'receipt_path' => 'Belge / fiş',
            'vehicle_id' => 'Araç / makine numarası',
            'tanker_id' => 'Tanker numarası',
            'transaction_id' => 'Kasa hareketi numarası',
            'fuel_date' => 'Yakıt tarihi',
            'fuel_time' => 'Yakıt saati',
            'liters' => 'Litre',
            'unit_price', 'unit_cost', 'average_unit_cost' => 'Litre fiyatı',
            'total_amount', 'cost' => 'Toplam tutar',
            'meter_value' => 'Kilometre',
            'operating_hours' => 'Çalışma saati',
            'station' => 'Akaryakıt istasyonu',
            'notes' => 'Not',
            'maintenance_date' => 'Bakım tarihi',
            'maintenance_type' => 'Bakım türü',
            'service_provider' => 'Servis / usta',
            'next_maintenance_date' => 'Sonraki bakım tarihi',
            'next_meter_value' => 'Sonraki bakım kilometresi',
            'next_operating_hours' => 'Sonraki bakım çalışma saati',
            'name' => 'Ad',
            'username' => 'Kullanıcı adı',
            'role' => 'Yetki',
            'permissions' => 'Yetki listesi',
            'is_active' => 'Durum',
            'plate' => 'Plaka',
            'code' => 'Makine kodu',
            'tracking_unit' => 'Takip birimi',
            'tracks_meters' => 'Sayaç takibi',
            'stock_liters' => 'Stok litresi',
            'movement_date' => 'Hareket tarihi',
            'movement_time' => 'Hareket saati',
            'supplier' => 'Tedarikçi',
            'balance_after' => 'İşlem sonrası stok',
            'label' => 'Menü adı',
            'sort_order' => 'Sıralama',
            'is_enabled' => 'Menü durumu',
            'minimum_role' => 'En düşük yetki',
            'key' => 'Ayar anahtarı',
            'value' => 'Ayar değeri',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'permissions') {
            $labels = Permission::catalog();

            return collect((array) $value)
                ->map(fn (mixed $permission): string => $labels[(string) $permission] ?? (string) $permission)
                ->filter()
                ->join(', ') ?: '—';
        }

        if (in_array($field, ['is_active', 'affects_cash', 'tracks_meters', 'is_enabled'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Evet' : 'Hayır';
        }

        if (in_array($field, ['amount', 'total_amount', 'cost'], true)) {
            return number_format((float) $value, 2, ',', '.').' ₺';
        }

        if (in_array($field, ['unit_price', 'unit_cost', 'average_unit_cost'], true)) {
            return number_format((float) $value, 4, ',', '.').' ₺';
        }

        if (in_array($field, ['liters', 'stock_liters', 'balance_after'], true)) {
            return number_format((float) $value, 3, ',', '.').' L';
        }

        if (in_array($field, ['meter_value', 'next_meter_value'], true)) {
            return number_format((float) $value, 1, ',', '.').' km';
        }

        if (in_array($field, ['operating_hours', 'next_operating_hours'], true)) {
            return number_format((float) $value, 1, ',', '.').' saat';
        }

        if (in_array($field, ['occurred_on', 'fuel_date', 'maintenance_date', 'next_maintenance_date', 'movement_date'], true)) {
            return Carbon::parse($value)->format('d.m.Y');
        }

        return match ((string) $value) {
            'income' => 'Gelir',
            'expense' => 'Gider',
            'vehicle' => 'Araç',
            'machine' => 'Makine',
            'km' => 'Kilometre',
            'hour' => 'Çalışma saati',
            'personnel' => 'Personel',
            'admin' => 'Şirket yöneticisi',
            'super_admin' => 'Sistem yöneticisi',
            'purchase' => 'Tankere alım',
            'issue' => 'Araç / makineye verilen',
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
