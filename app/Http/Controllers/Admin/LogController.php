<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        $modul = (string) $r->query('modul', '');
        $level = (string) $r->query('level', '');
        $dari = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query('dari')) ? $r->query('dari') : null;
        $sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query('sampai')) ? $r->query('sampai') : null;
        $dir = strtolower((string) $r->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $urut = ['waktu' => 'created_at', 'pelaku' => 'pelaku', 'modul' => 'modul', 'aksi' => 'aksi', 'level' => 'level', 'ip' => 'ip'];
        $kolom = $urut[$r->query('sort')] ?? 'created_at';

        $page = LogAktivitas::query()
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('pelaku', 'like', "%{$q}%")->orWhere('deskripsi', 'like', "%{$q}%")->orWhere('ip', 'like', "%{$q}%")))
            ->when($modul !== '', fn ($x) => $x->where('modul', $modul))
            ->when(in_array($level, ['info', 'warning', 'danger'], true), fn ($x) => $x->where('level', $level))
            ->when($dari, fn ($x) => $x->where('created_at', '>=', $dari.' 00:00:00'))
            ->when($sampai, fn ($x) => $x->where('created_at', '<=', $sampai.' 23:59:59'))
            ->orderBy($kolom, $dir)->orderBy('id', $dir)
            ->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($l) => [
                'id' => $l->id, 'waktu' => $l->created_at?->format('d/m/Y H:i:s'), 'pelaku' => $l->pelaku, 'tipe' => $l->tipe_pelaku,
                'modul' => $l->modul, 'aksi' => $l->aksi, 'level' => $l->level, 'deskripsi' => $l->deskripsi,
                'ip' => $l->ip, 'ua' => $l->user_agent, 'data' => $l->data,
            ])->values(),
            'meta' => ['halaman' => $page->currentPage(), 'terakhir' => $page->lastPage(), 'total' => $page->total()],
            'modul' => LogAktivitas::query()->distinct()->orderBy('modul')->pluck('modul'),
        ]);
    }
}
