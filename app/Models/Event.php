<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// =========================================================================
// MODEL ELOQUENT: EVENT (AGENDA / PEMINJAMAN RUANGAN)
// =========================================================================
// Merepresentasikan tabel `events` di database yang mencatat jadwal peminjaman ruangan.
class Event extends Model
{
    // Daftar atribut/kolom yang boleh diisi secara massal (Mass Assignment)
    // Tambahkan nama kolom baru di sini jika tabel `events` memiliki tambahan field.
    protected $fillable = ['user_id', 'room_id', 'title', 'event_date', 'start_time', 'end_time', 'status', 'approved_by', 'move_reason', 'rejection_reason'];

    /** Get the applicant who owns this booking. */
    // Relasi BelongsTo ke model User: Mengambil data pemohon/pengaju peminjaman ruangan (foreign key: user_id)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the room reserved by this booking. */
    // Relasi BelongsTo ke model Room: Mengambil data ruangan yang dipinjam pada agenda ini (foreign key: room_id)
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Get the administrator or PIC who approved this booking. */
    // Relasi BelongsTo ke model User: Mengambil data PIC atau Admin yang menyetujui agenda (foreign key: approved_by)
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Cast calendar date into a date object for safe formatting. */
    // Konversi otomatis (casting) kolom `event_date` menjadi objek tanggal (Carbon) agar mudah diformat (misal: ->format('Y-m-d'))
    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }
}
