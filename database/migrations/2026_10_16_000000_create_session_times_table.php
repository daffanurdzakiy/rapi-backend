<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_times', function (Blueprint $table) {
            $table->id();
            $table->string('session_name', 50)->unique();
            $table->time('time')->unique();
            $table->timestamps();
        });

        $sessions = [
            ['Sesi 1', '07:15'],
            ['Sesi 2', '08:00'],
            ['Sesi 3', '08:45'],
            ['Sesi 4', '09:30'],
            ['Sesi 5', '10:15'],
            ['Sesi 6', '11:00'],
            ['Sesi 7', '11:45'],
            ['Sesi 8', '12:30'],
            ['Sesi 9', '13:15'],
            ['Sesi 10', '14:00'],
            ['Sesi 11', '14:45'],
            ['Sesi 12', '15:30'],
            ['Sesi 13', '16:15'],
            ['Sesi 14', '17:00'],
        ];

        foreach ($sessions as [$name, $time]) {
            DB::table('session_times')->insert([
                'session_name' => $name,
                'time' => $time,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('session_times');
    }
};
