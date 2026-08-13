<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['income', 'expense'])->index();
            $table->string('category')->index();
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on')->index();
            $table->string('document_path')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['vehicle', 'machine'])->index();
            $table->string('name');
            $table->string('plate')->nullable()->unique();
            $table->string('code')->nullable()->unique();
            $table->enum('tracking_unit', ['km', 'hour'])->default('km');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fuel_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->date('fuel_date')->index();
            $table->decimal('liters', 10, 3);
            $table->decimal('unit_price', 10, 3);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('meter_value', 12, 1)->nullable();
            $table->string('station')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vehicle_id', 'fuel_date']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->enum('event', ['updated', 'deleted']);
            $table->json('old_values');
            $table->json('new_values')->nullable();
            $table->text('reason');
            $table->foreignId('user_id')->constrained('users');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('fuel_entries');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('transactions');
    }
};
