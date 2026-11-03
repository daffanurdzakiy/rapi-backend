<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// =========================================================================
// API CONTROLLER: ROOM CONTROLLER (INFORMASI DATA RUANGAN)
// =========================================================================
// Menyediakan daftar ruangan beserta fitur pencarian kata kunci (`?q=...`) dan detail ruangan.
class RoomController extends ApiController
{
    /**
     * Display a listing of rooms.
     */
    // Endpoint: GET /api/rooms
    // Mengambil daftar seluruh ruangan diurutkan berdasarkan nama ruangan secara alfabetis (A-Z)
    public function index(Request $request): JsonResponse
    {
        // Ambil parameter query pencarian `?q=` jika dikirim oleh client
        $search = trim((string) $request->query('q', ''));

        // Muat data ruangan beserta relasi PIC penanggung jawabnya (`pic`)
        // Jika parameter pencarian `q` diisi, cari kecocokan pada nama ruangan, lokasi, deskripsi, atau nama PIC
        $rooms = Room::with('pic')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($roomQuery) use ($search): void {
                    $roomQuery->where('room_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('pic', fn ($picQuery) => $picQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('room_name')
            ->get();

        return $this->sendResponse(
            RoomResource::collection($rooms),
            'Daftar ruangan berhasil dimuat.',
        );
    }

    /**
     * Display the specified room.
     */
    // Endpoint: GET /api/rooms/{room}
    // Menampilkan detail informasi satu ruangan beserta data PIC-nya
    public function show(Room $room): JsonResponse
    {
        $room->load('pic');

        return $this->sendResponse(
            new RoomResource($room),
            'Detail ruangan berhasil dimuat.',
        );
    }
}

