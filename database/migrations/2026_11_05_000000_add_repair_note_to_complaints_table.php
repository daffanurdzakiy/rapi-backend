<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaints') && ! Schema::hasColumn('complaints', 'repair_note')) {
            Schema::table('complaints', function (Blueprint $table) {
                $table->text('repair_note')->nullable()->after('status_repair');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('complaints') && Schema::hasColumn('complaints', 'repair_note')) {
            Schema::table('complaints', function (Blueprint $table) {
                $table->dropColumn('repair_note');
            });
        }
    }
};
