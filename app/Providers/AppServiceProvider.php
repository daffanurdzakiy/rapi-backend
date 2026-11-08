<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('complaints')) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_note')) {
                    \Illuminate\Support\Facades\Schema::table('complaints', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->text('repair_note')->nullable()->after('status_repair');
                    });
                }
                if (! \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_photo_path')) {
                    \Illuminate\Support\Facades\Schema::table('complaints', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->string('repair_photo_path', 2048)->nullable()->after('repair_note');
                    });
                }
                if (! \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'repair_photo_data')) {
                    \Illuminate\Support\Facades\Schema::table('complaints', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->longText('repair_photo_data')->nullable()->after('repair_photo_path');
                    });
                }
                if (! \Illuminate\Support\Facades\Schema::hasColumn('complaints', 'photo_data')) {
                    \Illuminate\Support\Facades\Schema::table('complaints', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->longText('photo_data')->nullable()->after('photo_path');
                    });
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika database belum siap/terhubung
        }
    }
}
