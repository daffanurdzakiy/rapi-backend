<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Controller khusus Admin untuk menampilkan & mencetak Laporan Peminjaman Ruangan (Harian, Mingguan, Bulanan).
class ReportController extends Controller
{
    // Menyaring data agenda peminjaman ruangan berdasarkan periode waktu yang dipilih Admin.
    public function index(Request $request): View
    {
        // Validasi filter periode laporan Admin ('harian', 'mingguan', 'bulanan').
        $filters = $request->validate([
            'periode' => ['sometimes', 'required', 'in:harian,mingguan,bulanan'],
            'tanggal' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $periode = $filters['periode'] ?? null;
        $tanggal = Carbon::parse($filters['tanggal'] ?? today()->toDateString());
        $awal = null;
        $akhir = null;

        // Menghitung batas tanggal awal dan akhir sesuai periode yang dipilih.
        if ($periode === 'harian') {
            $awal = $tanggal->copy()->startOfDay();
            $akhir = $tanggal->copy()->endOfDay();
        } elseif ($periode === 'mingguan') {
            $awal = $tanggal->copy()->startOfWeek(Carbon::MONDAY);
            $akhir = $tanggal->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($periode === 'bulanan') {
            $awal = $tanggal->copy()->startOfMonth();
            $akhir = $tanggal->copy()->endOfMonth();
        }

        $events = collect();

        if ($awal && $akhir) {
            $events = Event::with(['room', 'user'])
                ->whereDate('event_date', '>=', $awal->toDateString())
                ->whereDate('event_date', '<=', $akhir->toDateString())
                ->orderBy('event_date')
                ->orderBy('start_time')
                ->get();
        }

        return view('admin.reports.index', [
            'periode' => $periode,
            'tanggal' => $tanggal,
            'awal' => $awal,
            'akhir' => $akhir,
            'events' => $events,
        ]);
    }
}
