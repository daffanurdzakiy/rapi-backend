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
        Schema::create('events', function (Blueprint $table) {
        $table->id();

        // User yang mengajukan peminjaman
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        // Ruangan yang dipinjam
        $table->foreignId('room_id')->constrained()->cascadeOnDelete();

        // Nama kegiatan
        $table->string('title');

        // Tanggal kegiatan
        $table->date('event_date');

        // Jam mulai
        $table->time('start_time');

        // Jam selesai
        $table->time('end_time');

        // Status peminjaman
        $table->enum('status', [
            'Pending',
            'Disetujui',
            'Ditolak',
            'Dipindahkan'
        ])->default('Pending');

        // Alasan jika dipindahkan
        $table->text('move_reason')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
