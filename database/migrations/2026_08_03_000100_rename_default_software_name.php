<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')
            ->where('key', 'software_name')
            ->where('value', 'Şantiye360')
            ->update([
                'value' => 'Şantiye Takip',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->where('key', 'software_name')
            ->where('value', 'Şantiye Takip')
            ->update([
                'value' => 'Şantiye360',
                'updated_at' => now(),
            ]);
    }
};
