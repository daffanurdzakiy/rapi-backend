<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// =========================================================================
// API RESOURCE: COMPLAINT RESOURCE (FORMATTER JSON PENGADUAN)
// =========================================================================
// Kelas ini mengubah Model `Complaint` menjadi struktur JSON yang rapi dan konsisten untuk dikirim ke aplikasi Flutter/Client.
// Jika ingin mengubah atau mengatur penambahan field baru pada response JSON pengaduan, tambahkan di dalam array `toArray()`.
class ComplaintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Memastikan ID dikembalikan sebagai tipe integer (angka bulat) di JSON
            'id' => (int) $this->id,
            'room_id' => (int) $this->room_id,
            'reporter_id' => (int) $this->reporter_id,
            'description' => $this->description,
            'photo_path' => $this->photo_path,
            // URL publik langsung ke folder storage (jika symlink storage:link tersedia)
            'photo_url' => $this->photo_path ? asset('storage/' . $this->photo_path) : null,
            // URL endpoint API khusus untuk streaming foto bukti dengan pengecekan hak akses (Sanctum)
            'photo_api_url' => $this->photo_path ? url('/api/complaints/' . $this->id . '/photo') : null,
            // Status perbaikan: 'Belum Diperbaiki', 'Menunggu Spare Part', atau 'Sudah Diperbaiki'
            'status_repair' => $this->status_repair,
            // Format waktu standar internasional ISO-8601 agar mudah di-parse oleh Flutter (DateTime.parse)
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Menyertakan detail ruangan hanya jika relasi 'room' sudah di-load (Eager Loading `with('room')`)
            'room' => new RoomResource($this->whenLoaded('room')),
            // Menyertakan ringkasan data pelapor hanya jika relasi 'reporter' sudah di-load
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => (int) $this->reporter->id,
                'name' => $this->reporter->name,
                'email' => $this->reporter->email,
                'whatsapp' => $this->reporter->whatsapp,
                'role' => $this->reporter->role?->role_name,
            ]),
        ];
    }
}

