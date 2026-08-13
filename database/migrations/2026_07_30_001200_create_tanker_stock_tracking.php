<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tankers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('stock_liters', 12, 3)->default(0);
            $table->decimal('average_unit_cost', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('fuel_entries', function (Blueprint $table): void {
            $table->foreignId('tanker_id')->nullable()->after('vehicle_id')
                ->constrained('tankers')->restrictOnDelete();
        });

        Schema::create('tanker_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tanker_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['purchase', 'issue'])->index();
            $table->date('movement_date')->index();
            $table->time('movement_time')->nullable();
            $table->decimal('liters', 12, 3);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('balance_after', 12, 3);
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->restrictOnDelete();
            $table->foreignId('fuel_entry_id')->nullable()->unique()->constrained('fuel_entries')->restrictOnDelete();
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tanker_id', 'movement_date']);
        });

        $now = now();
        DB::table('tankers')->insert([
            ['name' => 'Tanker 1', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('navigation_items')->insert([
            'key' => 'tankers', 'label' => 'Tanker Stokları', 'sort_order' => 35,
            'is_enabled' => true, 'minimum_role' => 'personnel',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('navigation_items')->where('key', 'tankers')->delete();
        Schema::dropIfExists('tanker_movements');
        Schema::table('fuel_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('tanker_id'));
        Schema::dropIfExists('tankers');
    }
};
