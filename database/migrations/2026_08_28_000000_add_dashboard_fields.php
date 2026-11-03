<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Add contact and descriptive fields required by the UKK workflow. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->string('whatsapp', 25)->nullable()->after('email'); });
        Schema::table('rooms', function (Blueprint $table) { $table->text('description')->nullable()->after('location'); });
    }

    /** Remove fields added by this migration. */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) { $table->dropColumn('description'); });
        Schema::table('users', function (Blueprint $table) { $table->dropColumn('whatsapp'); });
    }
};
