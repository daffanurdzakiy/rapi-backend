<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\NationalHolidayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// =========================================================================
// API CONTROLLER: CALENDAR CONTROLLER (KALENDER AGENDA PUBLIK)
// =========================================================================
// Menyediakan data jadwal peminjaman ruangan yang SUDAH DISETUJUI ('Disetujui')
// dan daftar Hari Libur Nasional resmi SKB 3 Menteri secara publik tanpa wajib login.
//
// PERTANYAAN PENGUJI YANG SERING DITANYAKAN:
// Q: "Mengapa endpoint ini bisa diakses tanpa token login (Bearer Token)?"
// A: "Karena kalender sekolah berfungsi sebagai papan pengumuman digital publik.
//     Siswa, guru, atau tamu sekolah dapat melihat ketersediaan ruangan sebelum
//     mengajukan permohonan peminjaman ruangan."
//
// Q: "Bagaimana cara mencegah N+1 Query Problem saat memuat agenda kalender?"
// A: "Kami menerapkan Eager Loading menggunakan method `with(['room.pic', 'user'])`.
//     Dengan ini, data relasi Ruangan, PIC, dan Pemohon diambil sekaligus dalam
//     query efisien, bukan dipanggil berulang-ulang di dalam loop."
// =========================================================================
class CalendarController extends ApiController
{
    /**
     * Mengambil daftar agenda peminjaman ruangan yang disetujui beserta Hari Libur Nasional.
     * Endpoint: GET /api/calendar
     * Parameter URL (Query String):
     * - `month`: Format 'YYYY-MM' (contoh: '2026-11') -> Default: bulan ini
     * - `start_date` & `end_date`: Format 'YYYY-MM-DD' untuk rentang tanggal kustom
     */
    public function index(Request $request, NationalHolidayService $holidayService): JsonResponse
    {
        // Agenda yang menunggu keputusan pembatalan tetap menggunakan ruangan.
        $query = Event::with(['room.pic', 'user'])
            ->whereIn('status', ['Disetujui', 'Menunggu Pembatalan']);

        // 2. Evaluasi filter tanggal: Apakah client meminta rentang khusus atau bulan kalender
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
            $query->whereBetween('event_date', [$startDate, $endDate]);
            $monthString = $startDate->format('Y-m');
            // Buffer 7 hari sebelum & sesudah untuk memastikan data kalender utuh
            $holidayStart = $startDate->copy()->subDays(7);
            $holidayEnd = $endDate->copy()->addDays(7);
        } else {
            $requestedMonth = $request->input('month', now()->format('Y-m'));
            $month = preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
                ? Carbon::createFromFormat('Y-m', $requestedMonth)->startOfMonth()
                : now()->startOfMonth();
            $monthString = $month->format('Y-m');

            // Ambil agenda dari tanggal 1 hingga hari terakhir bulan aktif
            $query->whereBetween('event_date', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ]);

            // Ambil rentang hari libur termasuk sel tanggal di awal minggu pertama dan akhir minggu terakhir
            $holidayStart = $month->copy()->startOfMonth()->subDays(7);
            $holidayEnd = $month->copy()->endOfMonth()->addDays(7);
        }

        // 3. Urutkan berdasarkan tanggal kegiatan dan jam mulai paling pagi
        $events = $query->orderBy('event_date')->orderBy('start_time')->get();

        // 4. Transformasi data: Kelompokkan agenda berdasarkan string tanggal 'YYYY-MM-DD'
        //    Format Map/Key-Value ini memudahkan Flutter untuk langsung lookup O(1) saat menggambar titik di kalender
        $grouped = $events->groupBy(function (Event $event) {
            return $event->event_date instanceof \DateTimeInterface
                ? $event->event_date->format('Y-m-d')
                : Carbon::parse($event->event_date)->format('Y-m-d');
        })->map(fn ($dayEvents) => EventResource::collection($dayEvents));

        // 5. Mengambil daftar Hari Libur Nasional resmi SKB 3 Menteri untuk rentang kalender aktif
        $holidays = $holidayService->getHolidaysForRange($holidayStart, $holidayEnd);

        // 6. Kembalikan format respons JSON standar API RAPI
        return $this->sendResponse([
            'month' => $monthString,
            'total_events' => $events->count(),
            'events' => EventResource::collection($events),
            'events_by_date' => $grouped,
            'holidays' => $holidays,
        ], 'Data kalender agenda berhasil dimuat.');
    }

    /**
     * Menampilkan detail satu agenda kegiatan yang telah disetujui.
     * Endpoint: GET /api/calendar/events/{event}
     */
    public function show(Event $event): JsonResponse
    {
        // Agenda yang menunggu keputusan pembatalan tetap menggunakan ruangan.
        if (! in_array($event->status, ['Disetujui', 'Menunggu Pembatalan'], true)) {
            return $this->sendError('Agenda belum disetujui atau tidak dapat diakses secara publik.', null, 403);
        }

        $event->load(['room.pic', 'user']);

        return $this->sendResponse(
            new EventResource($event),
            'Detail agenda berhasil dimuat.',
        );
    }
}
