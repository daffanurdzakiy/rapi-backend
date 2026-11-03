<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SessionTimeResource;
use App\Models\SessionTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// =========================================================================
// API CONTROLLER: SESSION CONTROLLER (DAFTAR SESI WAKTU REGULER)
// =========================================================================
// Mengambil daftar jam sesi standar sekolah (Sesi 1 s/d Sesi 14) untuk pilihan waktu peminjaman ruangan.
class SessionController extends ApiController
{
    /**
     * Get regular session times available for room bookings.
     */
    // Endpoint: GET /api/sessions
    public function index(Request $request): JsonResponse
    {
        // Hanya mengambil sesi reguler (`is_free = false`) diurutkan berdasarkan jam (`time`)
        $sessions = SessionTime::where('is_free', false)
            ->orderBy('time')
            ->get();

        return $this->sendResponse(
            SessionTimeResource::collection($sessions),
            'Daftar sesi waktu peminjaman berhasil dimuat.',
        );
    }
}

