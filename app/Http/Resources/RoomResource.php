<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// =========================================================================
// API RESOURCE: ROOM RESOURCE (FORMATTER JSON DATA RUANGAN)
// =========================================================================
// Mengubah objek `Room` menjadi struktur JSON standar beserta informasi PIC penanggung jawabnya.
class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'room_name' => $this->room_name,
            'location' => $this->location,
            'description' => $this->description,
            // Kapasitas ruangan dalam satuan orang (integer)
            'capacity' => (int) $this->capacity,
            'pic_id' => $this->pic_id !== null ? (int) $this->pic_id : null,
            // Data PIC ruangan (hanya disertakan apabila relasi 'pic' sudah di-load dengan `with('pic')`)
            'pic' => $this->whenLoaded('pic', fn () => [
                'id' => (int) $this->pic->id,
                'name' => $this->pic->name,
                'email' => $this->pic->email,
                'whatsapp' => $this->pic->whatsapp,
            ]),
        ];
    }
}

