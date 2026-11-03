<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
          Schema::create('complaints', function (Blueprint $table) {
        $table->id();

        // Ruangan yang dilaporkan
        $table->foreignId('room_id')->constrained()->cascadeOnDelete();

        // Guru/organisasi yang membuat laporan
        $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();

        // Isi laporan
        $table->text('description');

        // Foto kerusakan (opsional)
        $table->string('photo_path')->nullable();

        // Status laporan
        $table->enum('status_repair', [
            'Belum Diperbaiki',
            'Menunggu Spare Part',
            'Sudah Diperbaiki',
        ])->default('Belum Diperbaiki');

        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
