<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('users')->where('role', 'super_admin')->exists()) {
            $adminId = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
            if ($adminId) {
                DB::table('users')->where('id', $adminId)->update(['role' => 'super_admin']);
            }
        }
    }

    public function down(): void
    {
        // Rol geri alınmaz; hesap kilitlenmesi riski yaratmamak için bilinçli olarak boş.
    }
};
