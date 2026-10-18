<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KehadiranManual;
use App\Models\Keterangan;
use App\Support\Aktivitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KeteranganController extends Controller
{
    public function index(): JsonResponse
    {
        $pakai = KehadiranManual::selectRaw('keterangan_id, count(*) as n')->groupBy('keterangan_id')->pluck('n', 'keterangan_id');

        return response()->json(Keterangan::orderBy('urutan')->orderBy('id')->get()->map(fn ($k) => [
            'id' => $k->id, 'nama' => $k->nama, 'kode' => $k->kode, 'sistem' => $k->sistem,
            'warna' => $k->warna, 'pakai_menit' => $k->pakai_menit, 'aktif' => $k->aktif,
            'dipakai' => (int) ($pakai[$k->id] ?? 0),
        ]));
    }

    public function simpan(Request $r): JsonResponse
    {
        $id = $r->input('id');
        $d = $r->validate([
            'id' => 'nullable|integer|exists:keterangan_absen,id',
            'nama' => 'required|string|max:50',
            'kode' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('keterangan_absen', 'kode')->ignore($id)],
            'warna' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pakai_menit' => 'required|boolean',
        ], [
            'kode.unique' => 'Kode sudah dipakai keterangan lain.',
            'kode.regex' => 'Kode hanya huruf/angka tanpa spasi.',
            'warna.regex' => 'Warna tidak valid.',
        ]);

        $data = ['nama' => trim($d['nama']), 'kode' => strtoupper($d['kode']), 'warna' => strtolower($d['warna']), 'pakai_menit' => $d['pakai_menit']];

        if ($id) {
            Keterangan::findOrFail($id)->update($data);
            Aktivitas::catat('keterangan', 'ubah', "Mengubah keterangan absen {$data['nama']} ({$data['kode']})");
        } else {
            $data['urutan'] = (int) Keterangan::max('urutan') + 1;
            if ($data['urutan'] >= 99) {
                $data['urutan'] = 98; // 99 dicadangkan untuk "Libur"
            }
            Keterangan::create($data);
            Aktivitas::catat('keterangan', 'tambah', "Menambah keterangan absen {$data['nama']} ({$data['kode']})");
        }

        return response()->json(['ok' => true]);
    }

    public function toggle(int $id): JsonResponse
    {
        $k = Keterangan::findOrFail($id);
        if ($k->sistem) {
            return response()->json(['message' => 'Keterangan bawaan sistem tidak bisa dinonaktifkan.'], 422);
        }
        $k->update(['aktif' => ! $k->aktif]);
        Aktivitas::catat('keterangan', 'status', ($k->aktif ? 'Mengaktifkan' : 'Menonaktifkan')." keterangan absen {$k->nama}");

        return response()->json(['aktif' => $k->aktif]);
    }

    public function destroy(int $id): JsonResponse
    {
        $k = Keterangan::findOrFail($id);
        if ($k->sistem) {
            return response()->json(['message' => 'Keterangan bawaan sistem tidak bisa dihapus.'], 422);
        }
        if (KehadiranManual::where('keterangan_id', $id)->exists()) {
            return response()->json(['message' => 'Sudah dipakai pada data kehadiran. Nonaktifkan saja.'], 422);
        }
        $k->delete();
        Aktivitas::catat('keterangan', 'hapus', "Menghapus keterangan absen {$k->nama}", 'warning');

        return response()->json(['ok' => true]);
    }
}
