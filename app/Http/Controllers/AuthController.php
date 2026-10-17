<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\Pengaturan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function form(Request $r): View|RedirectResponse
    {
        return $r->session()->has('nik') ? redirect()->route('absen') : view('login', ['tab' => old('_tab', 'karyawan')]);
    }

    public function adminForm(): View
    {
        return view('login', ['tab' => 'admin']);
    }

    // ---- Karyawan: NIK (8 digit) + 4 digit terakhir No. KTP ----
    public function login(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'nik' => ['required', 'regex:/^\d{4,20}$/'],
            'password' => ['required', 'digits:4'],
        ], [
            'nik.regex' => 'NIK harus berupa angka.',
            'password.digits' => 'Password adalah 4 digit terakhir No. KTP.',
        ]);

        $balik = fn (string $pesan) => back()->withInput(['nik' => $d['nik'], '_tab' => 'karyawan'])->withErrors(['nik' => $pesan]);

        $k = Karyawan::where('nik', $d['nik'])->first();
        if (! $k || (string) $k->status_aktif !== '1' || ! hash_equals(substr((string) $k->no_ktp, -4), $d['password'])) {
            return $balik('NIK atau password salah.');
        }

        $p = Pengaturan::where('nik', $k->nik)->first();
        if (! $p) {
            return $balik('Akun belum didaftarkan untuk presensi. Hubungi admin.');
        }
        if (! $p->aktif) {
            return $balik('Akun presensi nonaktif. Hubungi admin.');
        }

        $r->session()->regenerate();
        $r->session()->put('nik', $k->nik);

        return redirect()->route('absen');
    }

    public function logout(Request $r): RedirectResponse
    {
        $r->session()->forget(['nik', 'absen_nonce']);
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ---- Admin: email + password (tabel users) ----
    public function adminLogin(Request $r): RedirectResponse
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::attempt($d, $r->boolean('ingat'))) {
            return back()->withInput(['email' => $d['email'], '_tab' => 'admin'])
                ->withErrors(['email' => 'Email atau password salah.']);
        }
        $r->session()->regenerate();

        return redirect()->intended(route('admin.index'));
    }

    public function adminLogout(Request $r): RedirectResponse
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
