<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// =========================================================================
// API RESOURCE: USER RESOURCE (FORMATTER JSON PROFIL PENGGUNA)
// =========================================================================
// Mengubah objek `User` menjadi JSON saat login atau melihat profil (/api/me).
// Password tidak pernah disertakan demi keamanan.
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'role_id' => $this->role_id,
            // Informasi role pengguna (contoh: id = 2, name = 'PIC')
            'role' => [
                'id' => $this->role?->id,
                'name' => $this->role?->role_name,
            ],
            // Daftar ruangan yang dikelola jika user adalah PIC (muncul bila relasi 'assignedRooms' di-load)
            'assigned_rooms' => RoomResource::collection($this->whenLoaded('assignedRooms')),
        ];
    }
}

