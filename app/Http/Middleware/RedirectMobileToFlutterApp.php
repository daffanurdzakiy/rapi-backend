<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// ============================================================================
// MIDDLEWARE: RedirectMobileToFlutterApp
// TUJUAN & FUNGSI:
// 1. Mendeteksi apakah pengunjung website menggunakan perangkat mobile (HP/Tablet)
//    berdasarkan string HTTP User-Agent browser.
// 2. Mengalihkan (redirect) pengguna mobile ke antarmuka aplikasi Flutter Web (/m/)
//    agar mendapatkan pengalaman pengguna (UX) native yang optimal.
//
// ATURAN & KEBIJAKAN PENTING (DAPAT DIJELASKAN KEPADA PENGUJI):
// 1. Khusus Role Admin:
//    - Admin TIDAK PERNAH dialihkan ke /m/ karena menu master data tingkat lanjut
//      (CRUD Akun Pengguna, Master Ruangan, dsb) memang dikhususkan di Web Utama.
// 2. Pengecualian Route Teknis:
//    - Route API (/api/...), asset mobile (/m/...), streaming foto bukti kerusakan
//      dan foto perbaikan (pengaduan/{id}/foto...) TIDAK BOLEH dialihkan agar transfer
//      data dan pemuatan gambar tidak terputus atau loop redirect.
// 3. Fitur Override Session ('force_desktop'):
//    - Parameter ?desktop=1 memungkinkan pengguna tetap membuka versi desktop di HP.
//    - Parameter ?mobile=1 mengembalikan ke versi mobile.
// ============================================================================
class RedirectMobileToFlutterApp
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Dukungan toggle manual melalui query parameter (?desktop=1 atau ?mobile=1)
        if ($request->query('desktop') === '1') {
            $request->session()->put('force_desktop', true);
        } elseif ($request->query('mobile') === '1') {
            $request->session()->forget('force_desktop');
            return redirect('/m/');
        }

        // 2. Evaluasi kondisi pengalihan otomatis ke Flutter Web (/m/)
        if (
            $request->isMethod('GET') &&
            ! $request->session()->get('force_desktop', false) &&
            $request->user()?->role?->role_name !== 'Admin' &&
            ! $request->is(
                'api/*',
                'm',
                'm/*',
                'init-storage',
                'admin/*',
                'ruangan*',
                'pengaduan/*/foto',
                'pengaduan/*/foto-perbaikan',
                'pic/laporan-pengaduan/*/foto'
            )
        ) {
            $userAgent = $request->userAgent() ?? '';
            // Regex mendeteksi signature browser mobile Android, iPhone, iPad, dsb.
            if (preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone|webOS|BlackBerry|Opera Mini|IEMobile/i', $userAgent)) {
                return redirect('/m/');
            }
        }

        return $next($request);
    }
}
