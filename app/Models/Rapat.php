<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Rapat extends Model
{
    protected $table = 'rapat';
    protected $primaryKey = 'id_rapat';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date',
    ];

    /**
     * Nilai kolom `status` yang ditulis aplikasi.
     * Disamakan dengan data yang sudah ada: "Belum Terlaksana", "Sudah Terlaksana", "Batal".
     */
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_TERJADWAL = 'Belum Terlaksana';
    public const NILAI_SELESAI    = 'Sudah Terlaksana';
    public const NILAI_BATAL      = 'Batal';

    /** Status yang dianggap batal (huruf kecil). */
    public const STATUS_BATAL = ['batal', 'dibatalkan'];

    public function kelompok()
    {
        return $this->belongsTo(KelompokRapat::class, 'id_kelompok', 'id_kelompok');
    }

    public function tempat()
    {
        return $this->belongsTo(TempatRapat::class, 'id_tempat', 'id_tempat');
    }

    /** Apakah tabel rapat punya kolom opsional (waktu_selesai, metode, lampiran_path) */
    public static function punyaKolom(string $nama): bool
    {
        static $cache = [];
        return $cache[$nama] ??= Schema::hasColumn('rapat', $nama);
    }

    /**
     * Apakah tempat sudah dipakai rapat lain (yang belum batal/tertunda)
     * pada tanggal dan jam yang bertabrakan.
     */
    public static function bentrok(int $idTempat, string $tanggal, string $waktu, ?string $selesai = null, ?int $kecuali = null): bool
    {
        $q = static::where('id_tempat', $idTempat)
            ->whereDate('tanggal', $tanggal)
            ->whereRaw('LOWER(status) NOT LIKE ?', ['%batal%'])
            ->whereRaw('LOWER(status) NOT LIKE ?', ['%tunda%']);

        if ($kecuali) {
            $q->where('id_rapat', '!=', $kecuali);
        }

        $mulai = $waktu . ':00';

        if ($selesai && static::punyaKolom('waktu_selesai')) {
            $akhir = $selesai . ':00';
            $q->where(function ($w) use ($mulai, $akhir) {
                $w->where(fn ($x) => $x->whereNotNull('waktu_selesai')
                        ->where('waktu', '<', $akhir)->where('waktu_selesai', '>', $mulai))
                  ->orWhere(fn ($x) => $x->whereNull('waktu_selesai')
                        ->where('waktu', '>=', $mulai)->where('waktu', '<', $akhir));
            });
        } else {
            $q->where('waktu', $mulai);
        }

        return $q->exists();
    }

    /**
     * Ekspresi SQL yang mengubah nilai kolom status + tanggal/jam menjadi:
     * mendatang | berlangsung | selesai | terlewat | tertunda | draft | dibatalkan | lainnya
     * (alias tabel harus `rapat`).
     */
    public static function ekspresiStatus(bool $punyaSelesai): string
    {
        $hari  = now()->toDateString();   // aman: berasal dari PHP, bukan input pengguna
        $jam   = now()->format('H:i:s');
        $akhir = $punyaSelesai
            ? "COALESCE(rapat.waktu_selesai, ADDTIME(rapat.waktu, '02:00:00'))"
            : "ADDTIME(rapat.waktu, '02:00:00')";

        return "CASE
            WHEN LOWER(rapat.status) LIKE '%batal%'  THEN 'dibatalkan'
            WHEN LOWER(rapat.status) LIKE '%tunda%'  THEN 'tertunda'
            WHEN LOWER(rapat.status) = 'draft'       THEN 'draft'
            WHEN LOWER(rapat.status) LIKE '%belum%' OR LOWER(rapat.status) LIKE '%jadwal%' THEN
                CASE
                    WHEN rapat.tanggal > '$hari' THEN 'mendatang'
                    WHEN rapat.tanggal < '$hari' THEN 'terlewat'
                    WHEN '$jam' < rapat.waktu    THEN 'mendatang'
                    WHEN '$jam' <= $akhir        THEN 'berlangsung'
                    ELSE 'terlewat'
                END
            WHEN LOWER(rapat.status) LIKE '%laksana%' OR LOWER(rapat.status) LIKE '%selesai%' THEN 'selesai'
            WHEN LOWER(rapat.status) LIKE '%berlangsung%' THEN 'berlangsung'
            ELSE 'lainnya'
        END";
    }
}