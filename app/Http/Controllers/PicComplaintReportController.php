<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Controller khusus Role PIC untuk menampilkan & mencetak Laporan Pengaduan Kerusakan Fasilitas (Harian, Mingguan, Bulanan).
class PicComplaintReportController extends Controller
{
    // Menampilkan foto bukti pengaduan pada halaman laporan PIC.
    public function showPhoto(Request $request, Complaint $complaint): StreamedResponse
    {
        $user = $request->user();
        $role = $user->role?->role_name;

        // Boleh dilihat oleh Admin atau PIC yang bertanggung jawab atas ruangan tersebut
        $canView = $role === 'Admin'
            || ($role === 'PIC' && $complaint->room()->where('pic_id', $user->id)->exists());

        abort_unless($canView, 403);
        abort_unless($complaint->photo_path && Storage::disk('public')->exists($complaint->photo_path), 404);

        $disk = Storage::disk('public');
        $mime = 'image/jpeg';
        try {
            $mime = $disk->mimeType($complaint->photo_path) ?: 'image/jpeg';
        } catch (\Throwable $e) {
            $mime = 'image/jpeg';
        }

        return $disk->response(
            $complaint->photo_path,
            headers: [
                'Content-Type' => $mime,
                'Cache-Control' => 'no-cache, private',
            ],
            disposition: 'inline',
        );
    }

    // Menyaring data pengaduan di ruangan milik PIC berdasarkan periode (harian, mingguan, atau bulanan).
    public function index(Request $request): View
    {
        abort_unless($request->user()->role?->role_name === 'PIC', 403);

        // Validasi filter periode laporan PIC.
        $filters = $request->validate([
            'periode' => ['sometimes', 'required', 'in:harian,mingguan,bulanan'],
            'tanggal' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $periode = $filters['periode'] ?? null;
        $tanggal = Carbon::parse($filters['tanggal'] ?? today()->toDateString());
        $awal = null;
        $akhir = null;

        // Menentukan rentang tanggal awal dan akhir sesuai periode yang dipilih.
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

        $complaints = collect();

        if ($awal && $akhir) {
            $complaints = Complaint::with(['room', 'reporter'])
                ->whereHas('room', fn ($query) => $query->where('pic_id', $request->user()->id))
                ->whereDate('created_at', '>=', $awal->toDateString())
                ->whereDate('created_at', '<=', $akhir->toDateString())
                ->orderBy('created_at')
                ->get();
        }

        return view('pic.complaint-reports.index', [
            'periode' => $periode,
            'tanggal' => $tanggal,
            'awal' => $awal,
            'akhir' => $akhir,
            'complaints' => $complaints,
        ]);
    }
}
