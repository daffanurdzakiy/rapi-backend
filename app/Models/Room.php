<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// =========================================================================
// MODEL ELOQUENT: ROOM (DATA RUANGAN)
// =========================================================================
// Merepresentasikan tabel `rooms` di database yang menyimpan informasi ruangan beserta PIC penanggung jawabnya.
class Room extends Model
{
    // Kolom-kolom tabel `rooms` yang diizinkan untuk diisi secara massal (Mass Assignment)
    // Jika ingin menambahkan atribut ruangan baru (misal: 'floor' atau 'facilities'), daftarkan di sini.
    protected $fillable = ['room_name', 'location', 'description', 'capacity', 'pic_id'];

    /** Get the PIC assigned to this room. */
    // Relasi BelongsTo ke model User: Mengambil data pengguna yang ditugaskan sebagai PIC (Person In Charge) ruangan ini
    public function pic(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'pic_id'); }

    /** Get all bookings made for this room. */
    // Relasi HasMany ke model Event: Satu ruangan dapat memiliki banyak jadwal agenda peminjaman
    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Event::class); }

    /** Get all facility reports filed for this room. */
    // Relasi HasMany ke model Complaint: Satu ruangan dapat memiliki banyak laporan pengaduan kerusakan
    public function complaints(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Complaint::class); }
}
