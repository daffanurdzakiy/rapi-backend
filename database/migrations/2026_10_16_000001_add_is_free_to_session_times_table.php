<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_times', function (Blueprint $table) {
            $table->boolean('is_free')->default(false);
        });

        DB::table('session_times')
            ->whereNotIn('session_name', array_map(
                fn (int $number) => 'Sesi '.$number,
                range(1, 14),
            ))
            ->update(['is_free' => true]);
    }

    public function down(): void
    {
        Schema::table('session_times', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
