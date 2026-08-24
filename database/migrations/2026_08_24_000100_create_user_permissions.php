<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('label', 150);
            $table->string('group', 50)->index();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('permissions_configured')->nullable()->after('is_active');
        });

        $catalog = [
            ['dashboard.view', 'Gösterge panelini görüntüleme', 'Görüntüleme'],
            ['transactions.view', 'Kasa hareketlerini görüntüleme', 'Kasa'],
            ['transactions.create', 'Gelir / gider kaydı ekleme', 'Kasa'],
            ['transactions.manage', 'Kasa kayıtlarını düzeltme / silme', 'Kasa'],
            ['fuel.view', 'Yakıt raporunu görüntüleme', 'Yakıt'],
            ['fuel.create', 'Araç veya makine yakıt kaydı ekleme', 'Yakıt'],
            ['fuel.manage', 'Yakıt kayıtlarını düzeltme / silme', 'Yakıt'],
            ['tankers.view', 'Tanker stoklarını görüntüleme', 'Tanker'],
            ['tankers.purchase', 'Tankere yakıt alımı ekleme', 'Tanker'],
            ['tankers.manage', 'Tanker ekleme / düzenleme / silme', 'Tanker'],
            ['maintenance.view', 'Bakım / onarım kayıtlarını görüntüleme', 'Bakım'],
            ['maintenance.create', 'Bakım / onarım kaydı ekleme', 'Bakım'],
            ['maintenance.manage', 'Bakım kayıtlarını düzeltme / silme', 'Bakım'],
            ['vehicles.view', 'Araç ve makineleri görüntüleme', 'Filo'],
            ['vehicles.manage', 'Araç / makine ekleme / düzenleme / silme', 'Filo'],
            ['reports.view', 'Gelişmiş raporları görüntüleme', 'Raporlar'],
            ['notifications.view', 'Bildirimleri görüntüleme', 'Görüntüleme'],
            ['audit.view', 'Düzeltme / silme geçmişini görüntüleme', 'Yönetim'],
            ['users.manage', 'Kullanıcı ve kullanıcı yetkilerini yönetme', 'Yönetim'],
            ['system.manage', 'Sistem ayarlarını yönetme', 'Sistem'],
        ];

        foreach ($catalog as $sort => [$key, $label, $group]) {
            DB::table('permissions')->insert([
                'key' => $key, 'label' => $label, 'group' => $group,
                'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'key');
        $defaultKeys = [
            'personnel' => [
                'dashboard.view', 'transactions.view', 'transactions.create',
                'fuel.view', 'fuel.create', 'tankers.view', 'tankers.purchase',
                'maintenance.view', 'maintenance.create', 'reports.view', 'notifications.view',
            ],
            'admin' => array_values(array_filter(array_keys(App\Models\Permission::catalog()), fn (string $key): bool => $key !== 'system.manage')),
            'super_admin' => array_keys(App\Models\Permission::catalog()),
        ];

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            foreach ($defaultKeys[$user->role] ?? $defaultKeys['personnel'] as $key) {
                if (isset($permissionIds[$key])) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $user->id, 'permission_id' => $permissionIds[$key],
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            DB::table('users')->where('id', $user->id)->update(['permissions_configured' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('permissions_configured');
        });
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permissions');
    }
};
