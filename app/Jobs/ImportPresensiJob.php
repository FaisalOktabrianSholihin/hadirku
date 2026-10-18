<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\ImportPresensiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/** Import pengaturan presensi dari Excel, berjalan di latar belakang (queue) */
class ImportPresensiJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(ImportPresensiService $svc): void
    {
        $job = ImportJob::find($this->importId);
        if (! $job || $job->status !== 'menunggu') {
            return;
        }
        $svc->jalankan($job);
    }

    public function failed(?Throwable $e): void
    {
        ImportJob::whereKey($this->importId)->update([
            'status' => 'gagal',
            'pesan' => Str::limit($e?->getMessage() ?: 'Import gagal diproses.', 250, ''),
            'selesai_at' => now(),
        ]);
    }
}
