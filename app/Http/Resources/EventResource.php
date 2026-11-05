<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// =========================================================================
// API RESOURCE: EVENT RESOURCE (FORMATTER JSON AGENDA PEMINJAMAN RUANGAN)
// =========================================================================
// Kelas ini mengubah Model `Event` menjadi format JSON standar untuk dikonsumsi aplikasi Mobile/Client.
// Ubah di sini Jika ingin menambah/mengubah format tanggal, jam, atau data relasi pada JSON agenda.
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // Memformat tanggal agenda menjadi 'YYYY-MM-DD' (contoh: '2026-11-03')
            'event_date' => $this->event_date instanceof \DateTimeInterface
                ? $this->event_date->format('Y-m-d')
                : Carbon::parse($this->event_date)->format('Y-m-d'),
            // Memotong string waktu dari 'HH:MM:SS' menjadi 5 karakter 'HH:MM' (contoh: '08:00')
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            // Status pengajuan: 'Pending', 'Disetujui', atau 'Ditolak'
            'status' => $this->status,
            'execution_status' => $this->execution_status,
            'status_label' => $this->execution_status,
            'calendar_color_type' => $this->calendar_color_type,
            // Catatan alasan pemindahan jadwal (jika pernah dipindah oleh Admin) atau alasan penolakan dari PIC/Admin
            'move_reason' => $this->move_reason,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            // Data ruangan yang dipinjam (muncul jika di-load dengan `with('room')`)
            'room' => new RoomResource($this->whenLoaded('room')),
            // Data pemohon/pengaju peminjaman ruangan (muncul jika di-load dengan `with('user')`)
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'whatsapp' => $this->user->whatsapp,
                'role' => $this->user->role?->role_name,
            ]),
            // Data PIC/Admin yang menyetujui agenda (muncul jika di-load dengan `with('approver')`)
            'approver' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
        ];
    }
}

