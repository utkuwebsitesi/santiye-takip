<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table): void {
            $table->time('fuel_time')->nullable()->after('fuel_date');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_entries', fn (Blueprint $table) => $table->dropColumn('fuel_time'));
    }
};
