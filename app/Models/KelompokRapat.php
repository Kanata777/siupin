<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KelompokRapat extends Model
{
    protected $table = 'kelompok_rapat';
    protected $primaryKey = 'id_kelompok';
    public $timestamps = false;
    protected $guarded = [];

    /**
     * Kelompok yang dibuat otomatis saat peserta dipilih satu per satu di
     * halaman Buat Rapat diberi awalan ini, dan tidak ditampilkan di daftar kelompok.
     */
    public const PREFIX_OTOMATIS = 'Peserta Rapat: ';

    public function anggota()
    {
        return $this->hasMany(KelompokRapatDetail::class, 'id_kelompok', 'id_kelompok');
    }

    public function rapat()
    {
        return $this->hasMany(Rapat::class, 'id_kelompok', 'id_kelompok');
    }

    /** Apakah tabel kelompok_rapat punya kolom opsional (keterangan, dibuat_oleh, dibuat_pada) */
    public static function punyaKolom(string $nama): bool
    {
        static $cache = [];
        return $cache[$nama] ??= Schema::hasColumn('kelompok_rapat', $nama);
    }

    /** Nilai atribut tanpa error bila kolomnya tidak ada */
    public function attr(string $nama)
    {
        return $this->getAttributes()[$nama] ?? null;
    }

    /** Buat kelompok baru (mengisi kolom opsional bila ada) */
    public static function buat(string $nama, ?string $keterangan = null): self
    {
        $data = ['nama_kelompok' => $nama];

        if (static::punyaKolom('keterangan') && $keterangan !== null) {
            $data['keterangan'] = $keterangan;
        }
        if (static::punyaKolom('dibuat_oleh')) {
            $data['dibuat_oleh'] = auth()->user()->name ?? 'SDM YNH';
        }
        if (static::punyaKolom('dibuat_pada')) {
            $data['dibuat_pada'] = now()->toDateTimeString();
        }

        return static::create($data);
    }

    /** Isi anggota kelompok. $peserta = [['nomor_wa' =>, 'nama' =>], ...] */
    public static function isiAnggota(int $id, string $nama, $peserta): void
    {
        $baris = collect($peserta)->map(fn ($p) => [
            'id_kelompok'   => $id,
            'nomor_wa'      => $p['nomor_wa'],
            'nama_kelompok' => $nama,
            'nama_lengkap'  => $p['nama'],
            'ingat_rapat'   => 0,
        ])->all();

        foreach (array_chunk($baris, 500) as $bagian) {
            KelompokRapatDetail::insert($bagian);
        }
    }

    /**
     * Daftar kelompok tersimpan untuk tabel & pilihan di halaman Buat Rapat:
     * [['id', 'nama', 'jumlah', 'keterangan'], ...]
     */
    public static function ringkasan(): array
    {
        $grup = static::query()
            ->where('nama_kelompok', 'not like', self::PREFIX_OTOMATIS . '%')
            ->withCount('anggota')
            ->orderBy('nama_kelompok')
            ->get();

        $unit = collect(DB::select("
            SELECT d.id_kelompok,
                   COUNT(DISTINCT NULLIF(TRIM(p.nama_unit), '')) AS n,
                   MIN(NULLIF(TRIM(p.nama_unit), '')) AS u1,
                   MAX(NULLIF(TRIM(p.nama_unit), '')) AS u2
              FROM kelompok_rapat_detail d
              JOIN pegawai p ON p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci
             GROUP BY d.id_kelompok
        "))->keyBy('id_kelompok');

        return $grup->map(function ($g) use ($unit) {
            $u = $unit[$g->id_kelompok] ?? null;
            if (!$u || (int) $u->n === 0) {
                $ket = '-';
            } elseif ((int) $u->n === 1) {
                $ket = $u->u1;
            } elseif ((int) $u->n === 2) {
                $ket = $u->u1 . ' & ' . $u->u2;
            } else {
                $ket = 'Lintas Unit';
            }

            return [
                'id'         => $g->id_kelompok,
                'nama'       => $g->nama_kelompok,
                'jumlah'     => $g->anggota_count,
                'keterangan' => $ket,
            ];
        })->values()->all();
    }
}