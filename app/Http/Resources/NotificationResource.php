<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// =========================================================================
// API RESOURCE: NOTIFICATION RESOURCE (FORMATTER JSON NOTIFIKASI)
// =========================================================================
// Mengubah objek `Notification` menjadi struktur JSON untuk ditampilkan di halaman notifikasi aplikasi.
class NotificationResource extends JsonResource
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
            // ID agenda atau pengaduan terkait (salah satu bisa bernilai null tergantung jenis notifikasinya)
            'event_id' => $this->event_id,
            'complaint_id' => $this->complaint_id,
            'user_id' => $this->user_id,
            // Isi pesan notifikasi
            'message' => $this->message,
            // Status baca dikonversi tegas ke boolean (true = sudah dibaca, false = belum dibaca)
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
            // Menyertakan detail objek agenda atau pengaduan jika relasinya sudah di-load
            'event' => new EventResource($this->whenLoaded('event')),
            'complaint' => new ComplaintResource($this->whenLoaded('complaint')),
        ];
    }
}

