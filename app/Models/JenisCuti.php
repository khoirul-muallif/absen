<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisCuti extends Model
{
    use  HasFactory;

    protected $fillable = [
        'nama', 'is_tahunan', 'default_kuota', 'perlu_lampiran', 'potong_kuota', 'is_active',
    ];

    protected $casts = [
        'is_tahunan' => 'boolean',
        'perlu_lampiran' => 'boolean',
        'potong_kuota' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function cutis(): HasMany
    {
        return $this->hasMany(Cuti::class);
    }

    public function kuotaCutis(): HasMany
    {
        return $this->hasMany(KuotaCuti::class);
    }
}
