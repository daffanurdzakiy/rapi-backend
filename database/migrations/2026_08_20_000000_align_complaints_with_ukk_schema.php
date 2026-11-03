<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('complaints', 'user_id')) {
            return;
        }

        DB::statement('ALTER TABLE complaints DROP FOREIGN KEY complaints_user_id_foreign');
        DB::statement('ALTER TABLE complaints DROP INDEX complaints_user_id_foreign');
        DB::statement("ALTER TABLE complaints MODIFY status ENUM('Menunggu', 'Diproses', 'Selesai', 'Belum Diperbaiki', 'Menunggu Spare Part', 'Sudah Diperbaiki') NOT NULL DEFAULT 'Menunggu'");
        DB::table('complaints')->where('status', 'Menunggu')->update(['status' => 'Belum Diperbaiki']);
        DB::table('complaints')->where('status', 'Diproses')->update(['status' => 'Menunggu Spare Part']);
        DB::table('complaints')->where('status', 'Selesai')->update(['status' => 'Sudah Diperbaiki']);
        DB::statement("ALTER TABLE complaints CHANGE user_id reporter_id BIGINT UNSIGNED NOT NULL, CHANGE photo photo_path VARCHAR(255) NULL, CHANGE status status_repair ENUM('Belum Diperbaiki', 'Menunggu Spare Part', 'Sudah Diperbaiki') NOT NULL DEFAULT 'Belum Diperbaiki', ADD CONSTRAINT complaints_reporter_id_foreign FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE complaints DROP FOREIGN KEY complaints_reporter_id_foreign');
        DB::statement("ALTER TABLE complaints CHANGE reporter_id user_id BIGINT UNSIGNED NOT NULL, CHANGE photo_path photo VARCHAR(255) NULL, CHANGE status_repair status ENUM('Menunggu', 'Diproses', 'Selesai') NOT NULL DEFAULT 'Menunggu', ADD CONSTRAINT complaints_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    }
};
