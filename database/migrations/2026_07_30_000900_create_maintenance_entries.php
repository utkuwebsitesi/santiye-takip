<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions')->restrictOnDelete();
            $table->date('maintenance_date')->index();
            $table->string('maintenance_type', 100)->index();
            $table->string('service_provider', 150)->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('meter_value', 12, 1)->nullable();
            $table->date('next_maintenance_date')->nullable()->index();
            $table->decimal('next_meter_value', 12, 1)->nullable();
            $table->text('description');
            $table->string('document_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vehicle_id', 'maintenance_date']);
        });

        DB::table('transaction_categories')->insertOrIgnore([
            'type' => 'expense', 'name' => 'Bakım / Onarım', 'is_active' => true,
            'sort_order' => 85, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('navigation_items')->insertOrIgnore([
            'key' => 'maintenance', 'label' => 'Bakım / Onarım', 'sort_order' => 45,
            'is_enabled' => true, 'minimum_role' => 'personnel',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('navigation_items')->where('key', 'maintenance')->delete();
        Schema::dropIfExists('maintenance_entries');
    }
};
