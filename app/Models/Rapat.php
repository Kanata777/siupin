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

    /** Jeda minimal (menit) antar rapat di tempat yang sama */
    public const JEDA_MENIT = 30;

    /** Status yang dianggap batal (huruf kecil). */
    public const STATUS_BATAL = ['batal', 'dibatalkan'];

    /** Pemilik rapat = user yang membuatnya. NULL pada data lama (hanya terlihat Super Admin). */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Apakah user yang sedang login adalah Super Admin (tidak terkena pembatasan kepemilikan) */
    public static function bolehSemua(): bool
    {
        $user = auth()->user();

        return $user !== null && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    /**
     * Rapat yang boleh dilihat user login: Super Admin = semua, User biasa = hanya miliknya.
     * Tanpa login = tidak ada. Sengaja BUKAN global scope, karena pengecekan bentrok tempat
     * (bentrok / queryBentrok) harus tetap melihat rapat milik semua user.
     */
    public function scopeTerlihat($query)
    {
        if (static::bolehSemua()) {
            return $query;
        }

        $id = auth()->id();

        return $id === null
            ? $query->whereRaw('1 = 0')
            : $query->where($this->qualifyColumn('user_id'), $id);
    }

    /** Hanya rapat yang dibuat user login (halaman Rapat Saya, termasuk untuk Super Admin) */
    public function scopeMilikSaya($query)
    {
        $id = auth()->id();

        return $id === null
            ? $query->whereRaw('1 = 0')
            : $query->where($this->qualifyColumn('user_id'), $id);
    }

    public function kelompok()
    {
        return $this->belongsTo(KelompokRapat::class, 'id_kelompok', 'id_kelompok');
    }

    /**
     * Tempat dari daftar Tempat Rapat. Rapat di luar kantor punya id_tempat = NULL,
     * jadi pakai withDefault() agar `$rapat->tempat->nama_tempat` tidak error (hasilnya null).
     * Bila perlu mengecek "apakah rapat ini di tempat terdaftar", gunakan `$rapat->id_tempat !== null`.
     */
    public function tempat()
    {
        return $this->belongsTo(TempatRapat::class, 'id_tempat', 'id_tempat')->withDefault();
    }

    /** Nama tempat untuk ditampilkan: selalu memakai kolom tempat_rapat (terisi untuk dalam & luar kantor) */
    public function getNamaTempatTampilAttribute(): string
    {
        return (string) ($this->tempat_rapat ?: optional($this->tempat)->nama_tempat);
    }

    /** Apakah rapat ini di luar kantor (tidak terhubung ke daftar tempat) */
    public function getDiLuarKantorAttribute(): bool
    {
        return $this->id_tempat === null;
    }

    /** Apakah tabel rapat punya kolom opsional (waktu_selesai, metode, lampiran_path) */
    public static function punyaKolom(string $nama): bool
    {
        static $cache = [];
        return $cache[$nama] ??= Schema::hasColumn('rapat', $nama);
    }

    /**
     * Apakah tempat sudah dipakai rapat lain (yang belum batal/tertunda)
     * pada tanggal dan jam yang bertabrakan, termasuk jeda JEDA_MENIT menit
     * sebelum dan sesudah rapat lain.
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

        $punyaSelesai = static::punyaKolom('waktu_selesai');
        $mulai = $waktu . ':00';
        $akhir = ($selesai && $punyaSelesai) ? $selesai . ':00' : $mulai;

        $selesaiLama = $punyaSelesai ? 'COALESCE(waktu_selesai, waktu)' : 'waktu';
        $jeda = sprintf('%02d:%02d:00', intdiv(static::JEDA_MENIT, 60), static::JEDA_MENIT % 60);

        // bentrok bila: mulai_baru <= selesai_lama + jeda  DAN  selesai_baru + jeda >= mulai_lama
        $q->whereRaw("TIME(?) < ADDTIME($selesaiLama, '$jeda')", [$mulai])
          ->whereRaw("ADDTIME(TIME(?), '$jeda') > waktu", [$akhir]);

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