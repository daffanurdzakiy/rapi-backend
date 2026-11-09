<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMobileToFlutterApp
{
    /**
     * Redirect mobile browsers to the compiled Flutter Web app (/m/).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query('desktop') === '1') {
            $request->session()->put('force_desktop', true);
        } elseif ($request->query('mobile') === '1') {
            $request->session()->forget('force_desktop');
            return redirect('/m/');
        }

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
            if (preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone|webOS|BlackBerry|Opera Mini|IEMobile/i', $userAgent)) {
                return redirect('/m/');
            }
        }

        return $next($request);
    }
}

