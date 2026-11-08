<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                if (! Schema::hasColumn('complaints', 'repair_photo_path')) {
                    $table->string('repair_photo_path', 2048)->nullable()->after('repair_note');
                }
                if (! Schema::hasColumn('complaints', 'repair_photo_data')) {
                    $table->longText('repair_photo_data')->nullable()->after('repair_photo_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                if (Schema::hasColumn('complaints', 'repair_photo_data')) {
                    $table->dropColumn('repair_photo_data');
                }
                if (Schema::hasColumn('complaints', 'repair_photo_path')) {
                    $table->dropColumn('repair_photo_path');
                }
            });
        }
    }
};
