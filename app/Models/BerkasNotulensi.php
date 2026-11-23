<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BerkasNotulensi extends Model
{
    protected $table = 'notulensi_berkas';
    protected $primaryKey = 'id_berkas';

    protected $fillable = ['id_notulensi', 'jenis', 'nama_asli', 'path', 'ukuran', 'mime'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getUkuranTeksAttribute(): string
    {
        return $this->ukuran >= 1048576
            ? number_format($this->ukuran / 1048576, 1, ',', '.') . ' MB'
            : max(1, (int) round($this->ukuran / 1024)) . ' KB';
    }
}
