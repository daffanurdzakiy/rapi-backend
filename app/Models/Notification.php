<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// =========================================================================
// MODEL ELOQUENT: NOTIFICATION (NOTIFIKASI PENGGUNA)
// =========================================================================
// Merepresentasikan tabel `notifications` untuk menyimpan pemberitahuan terkait agenda atau pengaduan.
class Notification extends Model
{
    // Daftar kolom yang dapat diisi secara massal (Mass Assignment)
    // `is_read` menandakan apakah notifikasi sudah dibaca (true/1) atau belum (false/0)
    protected $fillable = ['event_id', 'complaint_id', 'user_id', 'message', 'is_read'];

    /** Get the related event mutation. */
    // Relasi BelongsTo ke model Event: Menghubungkan notifikasi dengan agenda peminjaman ruangan terkait (bisa bernilai null jika notifikasi berasal dari pengaduan)
    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Event::class); }

    /** Get the related facility complaint. */
    // Relasi BelongsTo ke model Complaint: Menghubungkan notifikasi dengan laporan pengaduan terkait (bisa bernilai null jika notifikasi berasal dari agenda)
    public function complaint(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Complaint::class); }
}
