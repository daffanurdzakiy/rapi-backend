<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// =========================================================================
// MODEL ELOQUENT: EVENT (AGENDA / PEMINJAMAN RUANGAN)
// =========================================================================
// Merepresentasikan tabel `events` di database yang mencatat jadwal peminjaman ruangan.
/**
 * @property int $id
 */
class Event extends Model
{
    // Daftar atribut/kolom yang boleh diisi secara massal (Mass Assignment)
    // Tambahkan nama kolom baru di sini jika tabel `events` memiliki tambahan field.
    protected $fillable = ['user_id', 'room_id', 'title', 'event_date', 'start_time', 'end_time', 'status', 'approved_by', 'move_reason', 'rejection_reason'];

    protected $appends = ['execution_status'];

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

    /**
     * Menghitung status pelaksanaan agenda secara otomatis:
     * - Jika 'Disetujui':
     *   - Tanggal/jam pelaksanaan belum dimulai -> 'Belum Terlaksana'
     *   - Sedang berlangsung hari ini (di antara start_time & end_time) -> 'Sedang Berlangsung'
     *   - Jam selesai sudah lewat -> 'Sudah Terlaksana'
     * - Jika 'Ditolak' -> 'Ditolak'
     * - Jika 'Pending' -> 'Pending'
     * - Status lainnya (misal 'Dipindahkan') -> tetap sesuai status aslinya.
     */
    public function getExecutionStatusAttribute(): string
    {
        if ($this->status !== 'Disetujui') {
            return $this->status ?? 'Pending';
        }

        try {
            $dateStr = $this->event_date instanceof \DateTimeInterface
                ? $this->event_date->format('Y-m-d')
                : substr((string) $this->event_date, 0, 10);

            $startTime = substr((string) $this->start_time, 0, 8);
            $endTime = substr((string) $this->end_time, 0, 8);

            if (strlen($startTime) === 5) {
                $startTime .= ':00';
            }
            if (strlen($endTime) === 5) {
                $endTime .= ':00';
            }

            $start = Carbon::parse("{$dateStr} {$startTime}", 'Asia/Jakarta');
            $end = Carbon::parse("{$dateStr} {$endTime}", 'Asia/Jakarta');
            $now = now('Asia/Jakarta');

            if ($now < $start) {
                return 'Belum Terlaksana';
            }

            if ($now > $end) {
                return 'Sudah Terlaksana';
            }

            return 'Sedang Berlangsung';
        } catch (\Throwable $e) {
            return 'Disetujui';
        }
    }

    /** Kelas CSS badge untuk status pelaksanaan agenda. */
    public function getExecutionStatusBadgeClassAttribute(): string
    {
        return match ($this->execution_status) {
            'Sedang Berlangsung' => 'badge-ongoing',
            'Belum Terlaksana' => 'badge-upcoming',
            'Sudah Terlaksana' => 'badge-completed',
            'Ditolak' => 'badge-rejected',
            'Pending' => 'badge-pending',
            default => '',
        };
    }
}
