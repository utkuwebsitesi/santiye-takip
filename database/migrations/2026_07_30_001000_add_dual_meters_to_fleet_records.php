<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table): void {
            $table->decimal('operating_hours', 12, 1)->nullable()->after('meter_value');
        });
        Schema::table('maintenance_entries', function (Blueprint $table): void {
            $table->decimal('operating_hours', 12, 1)->nullable()->after('meter_value');
            $table->decimal('next_operating_hours', 12, 1)->nullable()->after('next_meter_value');
        });

        DB::table('vehicles')->where('tracking_unit', 'hour')->pluck('id')->each(function ($vehicleId): void {
            DB::table('fuel_entries')->where('vehicle_id', $vehicleId)->whereNotNull('meter_value')
                ->update(['operating_hours' => DB::raw('meter_value'), 'meter_value' => null]);
            DB::table('maintenance_entries')->where('vehicle_id', $vehicleId)->whereNotNull('meter_value')
                ->update(['operating_hours' => DB::raw('meter_value'), 'meter_value' => null]);
            DB::table('maintenance_entries')->where('vehicle_id', $vehicleId)->whereNotNull('next_meter_value')
                ->update(['next_operating_hours' => DB::raw('next_meter_value'), 'next_meter_value' => null]);
        });
    }

    public function down(): void
    {
        DB::table('vehicles')->where('tracking_unit', 'hour')->pluck('id')->each(function ($vehicleId): void {
            DB::table('fuel_entries')->where('vehicle_id', $vehicleId)->whereNotNull('operating_hours')
                ->update(['meter_value' => DB::raw('operating_hours')]);
            DB::table('maintenance_entries')->where('vehicle_id', $vehicleId)->whereNotNull('operating_hours')
                ->update(['meter_value' => DB::raw('operating_hours')]);
            DB::table('maintenance_entries')->where('vehicle_id', $vehicleId)->whereNotNull('next_operating_hours')
                ->update(['next_meter_value' => DB::raw('next_operating_hours')]);
        });

        Schema::table('fuel_entries', fn (Blueprint $table) => $table->dropColumn('operating_hours'));
        Schema::table('maintenance_entries', fn (Blueprint $table) => $table->dropColumn(['operating_hours', 'next_operating_hours']));
    }
};
