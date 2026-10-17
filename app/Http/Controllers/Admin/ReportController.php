<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Keterangan;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.report', [
            'divisi' => Divisi::orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            'keterangan' => Keterangan::where('aktif', true)->where(fn ($w) => $w->whereNull('sistem')->orWhere('sistem', '!=', 'libur'))
                ->orderBy('urutan')->get(['id', 'kode', 'nama', 'pakai_menit']),
            'awal' => now()->startOfMonth()->toDateString(),
            'akhir' => now()->toDateString(),
        ]);
    }

    public function data(Request $r, ReportService $svc): JsonResponse
    {
        $d = $r->validate([
            'dari' => 'required|date_format:Y-m-d',
            'sampai' => 'required|date_format:Y-m-d|after_or_equal:dari',
            'divisi' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
        ], ['sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);

        if (strtotime($d['sampai']) - strtotime($d['dari']) > 366 * 86400) {
            return response()->json(['message' => 'Rentang tanggal maksimal 1 tahun.'], 422);
        }

        return response()->json($svc->build($d['dari'], $d['sampai'], ($d['divisi'] ?? 0) ?: null, trim((string) ($d['q'] ?? ''))));
    }
}
