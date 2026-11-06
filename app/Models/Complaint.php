<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// =========================================================================
// MODEL ELOQUENT: COMPLAINT (PENGADUAN SARANA & PRASARANA)
// =========================================================================
// Merepresentasikan tabel `complaints` di database untuk menyimpan laporan kerusakan ruangan.
class Complaint extends Model
{
    // Daftar kolom yang diizinkan untuk diisi secara massal (Mass Assignment) saat Complaint::create() atau update()
    // Jika menambah kolom baru di tabel `complaints` (misal: 'priority'), tambahkan namanya ke array $fillable ini.
    protected $fillable = ['room_id', 'reporter_id', 'description', 'photo_path', 'photo_data', 'status_repair', 'repair_note'];

    protected $hidden = ['photo_data'];

    /** Get the room reported as damaged. */
    // Relasi Many-to-One (BelongsTo): Setiap pengaduan merujuk pada satu Ruangan (Room) yang dilaporkan rusak
    public function room(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Room::class); }

    /** Get the user who filed this complaint. */
    // Relasi Many-to-One (BelongsTo): Setiap pengaduan diajukan oleh satu User pelapor (menggunakan foreign key 'reporter_id')
    public function reporter(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
}
