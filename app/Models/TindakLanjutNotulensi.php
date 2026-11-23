<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TindakLanjutNotulensi extends Model
{
    public const STATUS = ['belum' => 'Belum', 'proses' => 'Proses', 'selesai' => 'Selesai'];

    protected $table = 'notulensi_tindak_lanjut';
    protected $primaryKey = 'id_tindak_lanjut';

    protected $fillable = ['id_notulensi', 'uraian', 'pic', 'target_tanggal', 'status', 'urutan'];
    protected $casts = ['target_tanggal' => 'date'];
}
