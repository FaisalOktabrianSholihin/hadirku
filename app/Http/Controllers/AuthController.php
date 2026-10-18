<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    // ---- Karyawan: NIK (8 digit) + 6 digit depan No. KTP ----
    public function login(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'nik' => ['required', 'regex:/^\d{8}$/'],
            'password' => ['required', 'digits:6'],
        ], [
            'nik.regex' => 'NIK harus tepat 8 digit angka.',
            'password.digits' => 'Password adalah 6 digit depan No. KTP.',
        ]);

        $balik = function (string $pesan, ?string $nama = null) use ($d) {
            Aktivitas::catat('auth', 'login_gagal', "Login karyawan gagal (NIK {$d['nik']}): {$pesan}", 'warning', [], $nama ?? $d['nik'], 'karyawan');

            return back()->withInput(['nik' => $d['nik'], '_tab' => 'karyawan'])->withErrors(['nik' => $pesan]);
        };

        $k = Karyawan::where('nik', $d['nik'])->first();
        if (! $k || (string) $k->status_aktif !== '1' || ! hash_equals(substr(preg_replace('/\D/', '', (string) $k->no_ktp), 0, 6), $d['password'])) {
            return $balik('NIK atau password salah.');
        }

        $p = Pengaturan::where('nik', $k->nik)->first();
        if (! $p) {
            return $balik('Akun belum didaftarkan untuk presensi. Hubungi admin.', $k->nama);
        }
        if (! $p->aktif) {
            return $balik('Akun presensi nonaktif. Hubungi admin.', $k->nama);
        }

        $r->session()->regenerate();
        $r->session()->put('nik', $k->nik);
        Aktivitas::catat('auth', 'login', 'Karyawan login', 'info', [], $k->nama, 'karyawan');

        return redirect()->route('absen');
    }

    public function logout(Request $r): RedirectResponse
    {
        if ($nik = $r->session()->get('nik')) {
            Aktivitas::catat('auth', 'logout', 'Karyawan logout', 'info', [], Karyawan::where('nik', $nik)->value('nama') ?? $nik, 'karyawan');
        }
        $r->session()->forget(['nik', 'absen_nonce']);
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ---- Admin: email + password (tabel users) ----
    public function adminLogin(Request $r): RedirectResponse
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $balik = fn(string $pesan) => back()->withInput(['email' => $d['email'], '_tab' => 'admin'])->withErrors(['email' => $pesan]);

        $u = User::where('email', strtolower($d['email']))->first();

        if ($u && ! $u->aktif && Hash::check($d['password'], $u->password)) {
            Aktivitas::catat('auth', 'login_ditolak', "Login admin ditolak, akun nonaktif: {$u->email}", 'warning', [], $u->name, 'admin');

            return $balik('Akun Anda dinonaktifkan. Hubungi administrator.');
        }

        if (! Auth::attempt(['email' => strtolower($d['email']), 'password' => $d['password'], 'aktif' => true], $r->boolean('ingat'))) {
            Aktivitas::catat('auth', 'login_gagal', "Login admin gagal: {$d['email']}", 'warning', [], $d['email'], 'admin');

            return $balik('Email atau password salah.');
        }

        $user = $r->user();
        $awal = $user->halamanAwal();
        if (! $awal) {
            Auth::logout();
            Aktivitas::catat('auth', 'login_ditolak', "Login admin ditolak, role tanpa akses menu: {$d['email']}", 'warning', [], $user->name, 'admin');

            return $balik('Role Anda belum punya akses ke menu apa pun. Hubungi administrator.');
        }

        $r->session()->regenerate();
        $user->forceFill(['terakhir_login' => now()])->save();
        Aktivitas::catat('auth', 'login', 'Login ke panel admin (' . $user->namaRole() . ')', 'info', [], $user->name, 'admin');

        return redirect()->intended($awal);
    }

    public function adminLogout(Request $r): RedirectResponse
    {
        Aktivitas::catat('auth', 'logout', 'Logout dari panel admin');
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
