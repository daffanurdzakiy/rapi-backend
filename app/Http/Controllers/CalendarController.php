<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\NationalHolidayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Controller untuk menampilkan Kalender Peminjaman Ruangan (baik untuk Publik/Tamu maupun User yang sudah Login).
class CalendarController extends Controller
{
    /** Show an accessible read-only monthly calendar for guests and every role. */
    public function index(Request $request, NationalHolidayService $holidayService): View
    {
        // Mengambil parameter bulan dari URL (?month=YYYY-MM), default ke bulan saat ini di timezone Asia/Jakarta.
        $requestedMonth = $request->input('month', now('Asia/Jakarta')->format('Y-m'));
        $month = preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
            ? Carbon::createFromFormat('Y-m', $requestedMonth, 'Asia/Jakarta')->startOfMonth()
            : now('Asia/Jakarta')->startOfMonth();

        $calendarStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        // Kalender publik hanya menampilkan agenda yang statusnya 'Disetujui',
        // dengan relasi room dan user.role untuk pewarnaan peminjam (Guru/Organisasi: Hijau, Admin: Kuning).
        $events = Event::with(['room', 'user.role'])
            ->where('status', 'Disetujui')
            ->whereBetween('event_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn ($event) => $event->event_date->format('Y-m-d'));

        // Mengambil jadwal hari libur nasional otomatis (berwarna merah)
        $holidays = $holidayService->getHolidaysForRange($calendarStart, $calendarEnd);

        // Jika sudah login tampilkan view 'calendar', jika belum login (tamu/siswa) tampilkan 'calendar.public'.
        return view($request->user() ? 'calendar' : 'calendar.public', [
            'month' => $month,
            'events' => $events,
            'holidays' => $holidays,
            'calendarStart' => $calendarStart,
            'calendarEnd' => $calendarEnd,
            'approvedCount' => $events->sum(fn ($dayEvents) => $dayEvents->count()),
            'calendarRoute' => $request->user() ? 'calendar' : 'calendar.public',
        ]);
    }
}
