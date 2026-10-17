<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabel milik db_indukk (read-only dari sisi Hadirku) */
class Karyawan extends IndukkModel
{
    protected $table = 'karyawans';
    protected $guarded = [];

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status_aktif', '1');
    }

    public function namaJabatan(): string
    {
        return (string) ($this->jabatan->nama_jabatan ?? '');
    }

    /** Contoh: "Manager Area I" (bukan "Asisten Manager ...") */
    public function isManager(): bool
    {
        return (bool) preg_match('/^manager\b/i', $this->namaJabatan());
    }

    /** Contoh: "Asisten Manager Area I" */
    public function isAsmen(): bool
    {
        return (bool) preg_match('/^asisten\s+manager\b/i', $this->namaJabatan());
    }
}
