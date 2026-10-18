<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Pemakaian: ->middleware('izin:master.user') atau any-of: 'izin:laporan.report,pengaturan.keterangan' */
class CekIzin
{
    public function handle(Request $request, Closure $next, string ...$kunci): Response
    {
        $u = $request->user();

        if (! $u || ! $u->aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() || $request->is('admin/api/*')
                ? response()->json(['message' => 'Akun tidak aktif, silakan login ulang.'], 401)
                : redirect()->route('admin.login')->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi administrator.'])->withInput(['_tab' => 'admin']);
        }

        if (! $u->bolehAkses(...$kunci)) {
            return $request->expectsJson() || $request->is('admin/api/*')
                ? response()->json(['message' => 'Anda tidak memiliki izin untuk fitur ini.'], 403)
                : response()->view('errors.403', ['awal' => $u->halamanAwal()], 403);
        }

        return $next($request);
    }
}
