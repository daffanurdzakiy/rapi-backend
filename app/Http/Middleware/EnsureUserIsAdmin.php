<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// =========================================================================
// MIDDLEWARE: ENSURE USER IS ADMIN (PROTEKSI HALAMAN KHUSUS ADMIN)
// =========================================================================
// Middleware ini bertugas memeriksa apakah pengguna yang sedang mengakses route memiliki role 'Admin'.
// Jika ingin agar role lain (misal 'PIC') juga diizinkan melewati middleware ini,
// ubah kondisi pengecekan di bawah menggunakan `in_array($request->user()?->role?->role_name, ['Admin', 'PIC'], true)`.
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Jika pengguna belum login atau nama rolenya bukan 'Admin', tolak akses dengan HTTP 403 Forbidden
        if ($request->user()?->role?->role_name !== 'Admin') {
            abort(403);
        }

        // Jika benar Admin, lanjutkan request ke Controller yang dituju
        return $next($request);
    }
}
