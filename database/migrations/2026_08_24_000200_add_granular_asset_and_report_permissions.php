<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['tankers.create', 'Tanker ekleyebilme', 'Tanker', 110],
            ['tankers.update', 'Tanker düzenleyebilme', 'Tanker', 111],
            ['tankers.delete', 'Tanker silebilme', 'Tanker', 112],
            ['vehicles.create', 'Araç / makine ekleyebilme', 'Filo', 120],
            ['vehicles.update', 'Araç / makine düzenleyebilme', 'Filo', 121],
            ['vehicles.delete', 'Araç / makine silebilme', 'Filo', 122],
            ['reports.cash.pdf', 'Kasa hareket raporu PDF alabilme', 'Raporlar', 130],
            ['reports.cash.excel', 'Kasa hareket raporu Excel alabilme', 'Raporlar', 131],
            ['reports.fuel.pdf', 'Yakıt raporu PDF alabilme', 'Raporlar', 132],
            ['reports.fuel.excel', 'Yakıt raporu Excel alabilme', 'Raporlar', 133],
        ];

        foreach ($permissions as [$key, $label, $group, $sortOrder]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'label' => $label,
                    'group' => $group,
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_column($permissions, 0))
            ->pluck('id', 'key');
        $grant = function (int $userId, array $keys) use ($permissionIds): void {
            $rows = [];
            foreach ($keys as $key) {
                if (isset($permissionIds[$key])) {
                    $rows[] = [
                        'user_id' => $userId,
                        'permission_id' => $permissionIds[$key],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if ($rows !== []) {
                DB::table('user_permissions')->insertOrIgnore($rows);
            }
        };

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            $existing = DB::table('user_permissions as up')
                ->join('permissions as p', 'p.id', '=', 'up.permission_id')
                ->where('up.user_id', $user->id)
                ->pluck('p.key')
                ->all();
            $keys = [];
            if (in_array('vehicles.manage', $existing, true)) {
                $keys = array_merge($keys, ['vehicles.create', 'vehicles.update', 'vehicles.delete']);
            }
            if (in_array('tankers.manage', $existing, true)) {
                $keys = array_merge($keys, ['tankers.create', 'tankers.update', 'tankers.delete']);
            }
            if (in_array($user->role, ['admin', 'super_admin'], true)) {
                $keys = array_merge($keys, ['reports.cash.pdf', 'reports.cash.excel', 'reports.fuel.pdf', 'reports.fuel.excel']);
            }
            $grant((int) $user->id, array_values(array_unique($keys)));
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'tankers.create', 'tankers.update', 'tankers.delete',
            'vehicles.create', 'vehicles.update', 'vehicles.delete',
            'reports.cash.pdf', 'reports.cash.excel', 'reports.fuel.pdf', 'reports.fuel.excel',
        ])->delete();
    }
};
