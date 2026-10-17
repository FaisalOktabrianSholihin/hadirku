<?php

namespace App\Http\Middleware;

use App\Models\Pengaturan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKaryawan
{
    public function handle(Request $request, Closure $next): Response
    {
        $nik = $request->session()->get('nik');
        $ok = $nik && Pengaturan::where('nik', $nik)->where('aktif', true)->exists();

        if (! $ok) {
            $request->session()->forget('nik');
            return $request->expectsJson()
                ? response()->json(['message' => 'Sesi berakhir, silakan login ulang.'], 401)
                : redirect()->route('login');
        }

        return $next($request);
    }
}
