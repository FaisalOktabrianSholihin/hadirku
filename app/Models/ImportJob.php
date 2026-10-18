<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan satu proses import Excel yang berjalan di latar belakang */
class ImportJob extends Model
{
    protected $table = 'import_jobs';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'dilihat' => 'boolean',
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function aktif(): bool
    {
        return in_array($this->status, ['menunggu', 'berjalan'], true);
    }

    /** Bentuk ringkas untuk JSON ke browser */
    public function ringkas(): array
    {
        return [
            'id' => $this->id,
            'nama_file' => $this->nama_file,
            'status' => $this->status,
            'persen' => (int) $this->persen,
            'total' => $this->total,
            'diproses' => $this->diproses,
            'berhasil' => $this->berhasil,
            'gagal' => $this->gagal,
            'errors' => $this->errors ?? [],
            'errors_lainnya' => $this->errors_lainnya,
            'pesan' => $this->pesan,
            'menunggu_detik' => $this->status === 'menunggu' ? $this->created_at?->diffInSeconds(now(), true) : 0,
        ];
    }
}
