<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notulensi extends Model
{
    protected $table = 'notulensi';
    protected $primaryKey = 'id_notulensi';

    protected $fillable = ['id_rapat', 'agenda', 'pembahasan', 'keputusan', 'status', 'difinalkan_pada'];
    protected $casts = ['difinalkan_pada' => 'datetime'];

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class, 'id_rapat', 'id_rapat');
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjutNotulensi::class, 'id_notulensi', 'id_notulensi')->orderBy('urutan');
    }

    public function berkas(): HasMany
    {
        return $this->hasMany(BerkasNotulensi::class, 'id_notulensi', 'id_notulensi');
    }

    public function sudahFinal(): bool
    {
        return $this->status === 'final';
    }
}
