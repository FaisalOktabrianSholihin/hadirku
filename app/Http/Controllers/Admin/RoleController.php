<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Aktivitas;
use App\Support\Izin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::withCount('users')->orderByDesc('sistem')->orderBy('nama')->get()->map(fn ($x) => [
                'id' => $x->id, 'nama' => $x->nama, 'deskripsi' => $x->deskripsi, 'izin' => $x->izin,
                'sistem' => $x->sistem, 'jumlah_user' => $x->users_count,
            ])->values(),
            'katalog' => Izin::katalog(),
        ]);
    }

    public function simpan(Request $r): JsonResponse
    {
        $id = $r->input('id');
        $d = $r->validate([
            'id' => 'nullable|integer|exists:roles,id',
            'nama' => ['required', 'string', 'max:50', Rule::unique('roles', 'nama')->ignore($id)],
            'deskripsi' => 'nullable|string|max:255',
            'izin' => 'required|array|min:1',
            'izin.*' => 'string',
        ], ['nama.unique' => 'Nama role sudah ada.', 'izin.required' => 'Pilih minimal 1 izin menu.', 'izin.min' => 'Pilih minimal 1 izin menu.']);

        $izin = Izin::bersihkan($d['izin']);
        if (! $izin) {
            throw ValidationException::withMessages(['izin' => 'Pilih minimal 1 izin menu.']);
        }

        if ($id) {
            $role = Role::findOrFail($id);
            if ($role->sistem) {
                return response()->json(['message' => 'Role bawaan sistem tidak bisa diubah.'], 422);
            }
            $role->update(['nama' => trim($d['nama']), 'deskripsi' => $d['deskripsi'] ?? null, 'izin' => $izin]);
        } else {
            $slug = Str::slug($d['nama']) ?: 'role';
            $dasar = $slug;
            for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
                $slug = $dasar.'-'.$i;
            }
            $role = Role::create(['nama' => trim($d['nama']), 'slug' => $slug, 'deskripsi' => $d['deskripsi'] ?? null, 'izin' => $izin, 'sistem' => false]);
        }

        Aktivitas::catat('role', $id ? 'ubah' : 'tambah', ($id ? 'Mengubah' : 'Menambah').' role '.$role->nama.' ('.count($izin).' izin menu)', 'info', ['izin' => $izin]);

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::withCount('users')->findOrFail($id);
        if ($role->sistem) {
            return response()->json(['message' => 'Role bawaan sistem tidak bisa dihapus.'], 422);
        }
        if ($role->users_count > 0) {
            return response()->json(['message' => "Role masih dipakai {$role->users_count} user. Pindahkan user ke role lain dulu."], 422);
        }
        $role->delete();
        Aktivitas::catat('role', 'hapus', 'Menghapus role '.$role->nama, 'warning');

        return response()->json(['ok' => true]);
    }
}
