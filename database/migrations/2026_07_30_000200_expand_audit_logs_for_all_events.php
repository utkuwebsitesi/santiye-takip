<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('event', 60)->change();
            $table->json('old_values')->nullable()->change();
            $table->text('reason')->nullable()->change();
            $table->string('ip_address', 45)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('audit_logs')->whereNotIn('event', ['updated', 'deleted'])->delete();
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->enum('event', ['updated', 'deleted'])->change();
            $table->json('old_values')->nullable(false)->change();
            $table->text('reason')->nullable(false)->change();
        });
    }
};
