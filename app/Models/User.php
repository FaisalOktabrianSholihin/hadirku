<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'aktif', 'terakhir_login'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'terakhir_login' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function namaRole(): string
    {
        return $this->role->nama ?? 'Tanpa role';
    }

    public function isSuperAdmin(): bool
    {
        return (bool) ($this->role && $this->role->isSuper());
    }

    /** True jika role user punya salah satu izin yang diminta (any-of). */
    public function bolehAkses(string ...$kunci): bool
    {
        $izin = (array) ($this->role->izin ?? []);
        if (in_array('*', $izin, true)) {
            return true;
        }

        return (bool) array_intersect($kunci, $izin);
    }

    /** URL halaman pertama yang boleh dibuka user (dipakai setelah login / saat 403). */
    public function halamanAwal(): ?string
    {
        foreach (\App\Support\Izin::RUTE as $kunci => $rute) {
            if ($this->bolehAkses($kunci)) {
                return route($rute);
            }
        }

        return null;
    }
}
