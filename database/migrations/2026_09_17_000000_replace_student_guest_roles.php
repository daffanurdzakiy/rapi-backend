<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'role_name');

        foreach ([
            'Waka/Admin' => 'Admin',
            'PIC Ruang' => 'PIC',
            'Guru/Organisasi' => 'Guru',
        ] as $oldName => $newName) {
            if (isset($roleIds[$oldName])) {
                $newRoleId = $roleIds[$newName] ?? DB::table('roles')->insertGetId([
                    'role_name' => $newName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('users')->where('role_id', $roleIds[$oldName])->update(['role_id' => $newRoleId]);
                DB::table('roles')->where('id', $roleIds[$oldName])->delete();
            }
        }

        DB::table('roles')->where('role_name', 'Siswa/Tamu')->delete();
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('role_name', ['Admin', 'PIC', 'Guru', 'Organisasi'])->delete();
    }
};