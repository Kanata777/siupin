<?php

// Simpan sebagai: app/Models/RapatUndangan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapatUndangan extends Model
{
    public const PENDING  = 'pending';
    public const TERKIRIM = 'terkirim';
    public const GAGAL    = 'gagal';

    protected $table   = 'rapat_undangan';
    protected $guarded = [];

    protected $casts = [
        'terkirim_pada' => 'datetime',
    ];

    public function rapat(): BelongsTo
    {
        // Primary key tabel `rapat` diambil dari model Rapat (controller memakai getKeyName())
        return $this->belongsTo(Rapat::class, 'id_rapat', (new Rapat)->getKeyName());
    }

    /**
     * Undangan / status WhatsApp yang boleh dilihat user login.
     * Pemilik undangan = pemilik rapatnya (rapat.user_id):
     *  - Super Admin : semua undangan
     *  - User biasa  : hanya undangan dari rapat yang dibuatnya
     */
    public function scopeTerlihat($query)
    {
        if (Rapat::bolehSemua()) {
            return $query;
        }

        return $query->whereIn(
            $this->qualifyColumn('id_rapat'),
            Rapat::terlihat()->select((new Rapat)->getKeyName())
        );
    }
}
