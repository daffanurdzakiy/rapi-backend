<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// =========================================================================
// API CONTROLLER: CALENDAR CONTROLLER (KALENDER AGENDA PUBLIK)
// =========================================================================
// Menyediakan daftar jadwal peminjaman ruangan yang SUDAH DISETUJUI ('Disetujui')
// agar dapat dilihat secara publik di halaman kalender sebelum maupun sesudah login.
class CalendarController extends ApiController
{
    /**
     * Get approved calendar events for monthly or custom range view.
     * Publicly accessible without authentication.
     */
    // Endpoint: GET /api/calendar
    // Parameter opsional: `?month=YYYY-MM` atau rentang `?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
    public function index(Request $request): JsonResponse
    {
        // Hanya mengambil agenda yang berstatus 'Disetujui' beserta informasi ruangan, PIC, dan pemohonnya
        // Jika ingin mengubah atau mengatur agenda berstatus 'Pending' juga tampil di kalender publik,
        // ubah `->where('status', 'Disetujui')` menjadi `->whereIn('status', ['Pending', 'Disetujui'])`.
        $query = Event::with(['room.pic', 'user'])
            ->where('status', 'Disetujui');

        // Jika client mengirimkan filter rentang tanggal spesifik (`start_date` dan `end_date`)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
            $query->whereBetween('event_date', [$startDate, $endDate]);
            $monthString = $startDate->format('Y-m');
        } else {
            // Jika tidak ada rentang tanggal, gunakan filter bulan (`month` dengan format YYYY-MM, default: bulan ini)
            $requestedMonth = $request->input('month', now()->format('Y-m'));
            $month = preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
                ? Carbon::createFromFormat('Y-m', $requestedMonth)->startOfMonth()
                : now()->startOfMonth();
            $monthString = $month->format('Y-m');

            // Filter agenda dari tanggal 1 hingga akhir bulan tersebut
            $query->whereBetween('event_date', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ]);
        }

        // Urutkan berdasarkan tanggal agenda dan jam mulai paling pagi
        $events = $query->orderBy('event_date')->orderBy('start_time')->get();

        // Kelompokkan daftar agenda berdasarkan tanggal ('YYYY-MM-DD') untuk memudahkan pembuatan titik penanda (markers) di kalender Flutter
        $grouped = $events->groupBy(function (Event $event) {
            return $event->event_date instanceof \DateTimeInterface
                ? $event->event_date->format('Y-m-d')
                : Carbon::parse($event->event_date)->format('Y-m-d');
        })->map(fn ($dayEvents) => EventResource::collection($dayEvents));

        return $this->sendResponse([
            'month' => $monthString,
            'total_events' => $events->count(),
            'events' => EventResource::collection($events),
            'events_by_date' => $grouped,
        ], 'Data kalender agenda berhasil dimuat.');
    }

    /**
     * Show details of a specific approved event.
     * Publicly accessible without authentication.
     */
    // Endpoint: GET /api/calendar/events/{event}
    // Menampilkan rincian satu agenda yang dipilih dari kalender publik
    public function show(Event $event): JsonResponse
    {
        // Keamanan: Pastikan agenda yang belum disetujui tidak bisa dilihat lewat endpoint publik
        if ($event->status !== 'Disetujui') {
            return $this->sendError('Agenda belum disetujui atau tidak dapat diakses secara publik.', null, 403);
        }

        $event->load(['room.pic', 'user']);

        return $this->sendResponse(
            new EventResource($event),
            'Detail agenda berhasil dimuat.',
        );
    }
}

