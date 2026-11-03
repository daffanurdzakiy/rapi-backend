<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// =========================================================================
// API RESOURCE: SESSION TIME RESOURCE (FORMATTER JSON SESI WAKTU)
// =========================================================================
// Mengubah objek `SessionTime` menjadi format JSON untuk pilihan jam sesi di form peminjaman ruangan.
class SessionTimeResource extends JsonResource
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
            'session_name' => $this->session_name,
            // Memotong format waktu menjadi 'HH:MM' (5 karakter pertama)
            'time' => substr($this->time, 0, 5),
            'is_free' => (bool) $this->is_free,
        ];
    }
}

