<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Akun yang boleh login ke panel admin */
class UserController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $dir = strtolower((string) $r->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $urut = ['nama' => 'users.name', 'email' => 'users.email', 'role' => 'roles.nama', 'aktif' => 'users.aktif', 'login' => 'users.terakhir_login'];
        $kolom = $urut[$r->query('sort')] ?? 'users.name';

        $page = User::query()->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('users.name', 'like', "%{$q}%")->orWhere('users.email', 'like', "%{$q}%")))
            ->orderByRaw("{$kolom} {$dir}")->orderBy('users.id')
            ->select('users.*', 'roles.nama as role_nama')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($u) => [
                'id' => $u->id, 'nama' => $u->name, 'email' => $u->email, 'role_id' => $u->role_id,
                'role' => $u->role_nama ?: 'Tanpa role', 'aktif' => (bool) $u->aktif, 'saya' => (int) $u->id === (int) auth()->id(),
                'login' => $u->terakhir_login?->format('d/m/Y H:i'),
            ])->values(),
            'meta' => ['halaman' => $page->currentPage(), 'terakhir' => $page->lastPage(), 'total' => $page->total()],
            'roles' => Role::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /** Pastikan setelah perubahan masih ada minimal 1 Super Admin aktif */
    private function jagaSuper(?User $lama, ?int $roleBaru, bool $aktifBaru, bool $hapus = false): void
    {
        $super = (int) Role::where('slug', 'super_admin')->value('id');
        if (! $lama || (int) $lama->role_id !== $super || ! $lama->aktif) {
            return; // user ini bukan super admin aktif, aman
        }
        $masihSuper = ! $hapus && (int) $roleBaru === $super && $aktifBaru;
        if (! $masihSuper && ! User::where('role_id', $super)->where('aktif', true)->where('id', '!=', $lama->id)->exists()) {
            throw ValidationException::withMessages(['role_id' => 'Minimal harus ada 1 Super Admin yang aktif.']);
        }
    }

    public function simpan(Request $r): JsonResponse
    {
        $id = $r->input('id');
        $d = $r->validate([
            'id' => 'nullable|integer|exists:users,id',
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'role_id' => 'required|integer|exists:roles,id',
            'aktif' => 'required|boolean',
        ], [
            'email.unique' => 'Email sudah dipakai akun lain.',
            'password.required' => 'Password wajib diisi untuk user baru.',
            'password.min' => 'Password minimal 8 karakter.',
            'role_id.required' => 'Pilih role untuk user ini.',
        ]);

        $u = $id ? User::findOrFail($id) : new User;
        if ($id && (int) $u->id === (int) auth()->id() && ! $d['aktif']) {
            throw ValidationException::withMessages(['aktif' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }
        if ($id) {
            $this->jagaSuper($u, (int) $d['role_id'], (bool) $d['aktif']);
        }

        $u->name = trim($d['name']);
        $u->email = strtolower(trim($d['email']));
        $u->role_id = (int) $d['role_id'];
        $u->aktif = (bool) $d['aktif'];
        if (! empty($d['password'])) {
            $u->password = $d['password'];
        }
        $u->save();

        Aktivitas::catat('user', $id ? 'ubah' : 'tambah', ($id ? 'Mengubah' : 'Menambah').' user '.$u->name.' ('.$u->email.') sebagai '.$u->namaRole().(! empty($d['password']) ? ', password diganti' : ''));

        return response()->json(['ok' => true]);
    }

    public function toggle(int $id): JsonResponse
    {
        $u = User::findOrFail($id);
        if ((int) $u->id === (int) auth()->id()) {
            return response()->json(['message' => 'Anda tidak bisa menonaktifkan akun sendiri.'], 422);
        }
        $this->jagaSuper($u, $u->role_id, ! $u->aktif);
        $u->update(['aktif' => ! $u->aktif]);
        Aktivitas::catat('user', 'status', ($u->aktif ? 'Mengaktifkan' : 'Menonaktifkan').' login user '.$u->name.' ('.$u->email.')', $u->aktif ? 'info' : 'warning');

        return response()->json(['aktif' => $u->aktif]);
    }

    public function destroy(int $id): JsonResponse
    {
        $u = User::findOrFail($id);
        if ((int) $u->id === (int) auth()->id()) {
            return response()->json(['message' => 'Anda tidak bisa menghapus akun sendiri.'], 422);
        }
        $this->jagaSuper($u, null, false, true);
        $u->delete();
        Aktivitas::catat('user', 'hapus', 'Menghapus user '.$u->name.' ('.$u->email.')', 'warning');

        return response()->json(['ok' => true]);
    }
}
