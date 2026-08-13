<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->boolean('affects_cash')->default(true)->index()->after('amount');
        });

        DB::table('transactions')
            ->whereIn('id', DB::table('fuel_entries')->whereNotNull('transaction_id')->select('transaction_id'))
            ->update(['affects_cash' => false]);
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn('affects_cash'));
    }
};
