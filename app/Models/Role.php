<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['izin' => 'array', 'sistem' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function isSuper(): bool
    {
        return $this->slug === 'super_admin';
    }
}
