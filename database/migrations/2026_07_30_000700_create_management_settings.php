<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_categories', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['income', 'expense'])->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['type', 'name']);
        });

        Schema::create('navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->string('minimum_role', 30)->default('personnel');
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            ['key' => 'software_name', 'value' => 'Şantiye Takip', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'software_tagline', 'value' => 'Kasa & Yakıt Yönetimi', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'company_name', 'value' => 'Şirket Adı', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $categories = [
            ['income', 'Hakediş'], ['income', 'Tahsilat'], ['income', 'Diğer Gelir'],
            ['expense', 'Malzeme'], ['expense', 'Yemek'], ['expense', 'Personel'], ['expense', 'Yakıt'], ['expense', 'Diğer Gider'],
        ];
        foreach ($categories as $order => [$type, $name]) {
            DB::table('transaction_categories')->insert([
                'type' => $type, 'name' => $name, 'is_active' => true, 'sort_order' => $order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $items = [
            ['dashboard', 'Gösterge Paneli', 10, 'personnel'],
            ['transaction_create', 'Gelir / Gider Ekle', 20, 'personnel'],
            ['transactions', 'Kasa Hareketleri', 30, 'personnel'],
            ['fuel_report', 'Yakıt Raporu', 40, 'personnel'],
            ['reports', 'Gelişmiş Raporlar', 50, 'personnel'],
            ['password', 'Parolamı Değiştir', 60, 'personnel'],
            ['vehicles', 'Araç ve Makineler', 70, 'admin'],
            ['audit', 'Düzeltme / Silme Geçmişi', 80, 'admin'],
            ['users', 'Kullanıcı Yönetimi', 90, 'admin'],
            ['system_management', 'Sistem Yönetimi', 100, 'super_admin'],
        ];
        foreach ($items as [$key, $label, $order, $role]) {
            DB::table('navigation_items')->insert([
                'key' => $key, 'label' => $label, 'sort_order' => $order,
                'is_enabled' => true, 'minimum_role' => $role,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('transaction_categories');
        Schema::dropIfExists('app_settings');
    }
};
