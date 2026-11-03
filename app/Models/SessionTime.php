<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// =========================================================================
// MODEL ELOQUENT: SESSION TIME (SESI WAKTU PEMINJAMAN RUANGAN)
// =========================================================================
// Merepresentasikan tabel `session_times` yang menyimpan daftar jam sesi standar sekolah (Sesi 1-14) maupun waktu bebas.
class SessionTime extends Model
{
    // Daftar kolom yang dapat diisi secara massal (Mass Assignment)
    protected $fillable = ['session_name', 'time', 'is_free'];

    // Konversi otomatis (casting) kolom `is_free` dari angka (0/1) di database menjadi tipe boolean (true/false) di PHP
    protected function casts(): array
    {
        return ['is_free' => 'boolean'];
    }
}
