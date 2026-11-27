<?php

// Simpan sebagai: app/Services/AbsensiService.php (file baru)

namespace App\Services;

use App\Models\KelompokRapatDetail;
use App\Models\Pegawai;
use App\Models\Rapat;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Absensi rapat lewat scan QR.
 *
 *  - Tiap rapat punya satu QR. Isinya link /absen/{id}/{kode}; {kode} adalah HMAC dari id rapat
 *    (memakai APP_KEY), jadi tidak perlu kolom baru dan tidak bisa ditebak.
 *  - Peserta scan QR, memasukkan nomor WA, lalu dicocokkan dengan anggota kelompok rapat (undangan).
 *  - Kehadiran dicatat ke tabel_absensi yang sama dengan yang dibaca Dashboard, Laporan, dan Notulensi
 *    (dicocokkan lewat nama rapat + tanggal, karena tabel itu tidak punya id_rapat).
 */
class AbsensiService
{
    // ======================================================================
    //  LINK / KODE QR
    // ======================================================================
    public static function kode(int $idRapat): string
    {
        return substr(hash_hmac('sha256', 'absen|' . $idRapat, (string) config('app.key')), 0, 16);
    }

    public static function kodeValid(int $idRapat, string $kode): bool
    {
        return hash_equals(self::kode($idRapat), $kode);
    }

    /** Apakah aplikasi dan absensi dilayani dua domain berbeda (lihat config/absen.php) */
    public static function terpisah(): bool
    {
        return trim((string) config('absen.host')) !== '' && trim((string) config('absen.app_host')) !== '';
    }

    /** Alamat asal absensi, mis. https://siupinabsen.nhsolo.com (null bila satu domain) */
    public static function asalAbsen(): ?string
    {
        if (! self::terpisah()) {
            return null;
        }

        // skema dan port mengikuti APP_URL (mis. http://localhost:8000 saat uji coba lokal)
        $skema = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: request()->getScheme();
        $port  = parse_url((string) config('app.url'), PHP_URL_PORT);

        return $skema . '://' . trim((string) config('absen.host')) . ($port ? ':' . $port : '');
    }

    /** Link yang dimasukkan ke QR (domain absensi bila dua domain, selain itu domain aplikasi + /absen) */
    public static function url(int $idRapat): string
    {
        return self::alamat(route('absen.form', ['id' => $idRapat, 'kode' => self::kode($idRapat)], false));
    }

    /** Link halaman scanner kamera */
    public static function urlScan(): string
    {
        return self::alamat(route('absen.scan', [], false));
    }

    private static function alamat(string $pathRelatif): string
    {
        $asal = self::asalAbsen();

        return $asal ? $asal . $pathRelatif : url($pathRelatif);
    }

    /** Samakan format nomor: 0812..., 812..., +62812... -> 62812... */
    public static function nomor(?string $n): string
    {
        $d = preg_replace('/\D+/', '', (string) $n);

        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '0')) {
            return '62' . substr($d, 1);
        }
        if (str_starts_with($d, '8')) {
            return '62' . $d;
        }

        return $d;
    }

    // ======================================================================
    //  ATURAN BUKA / TUTUP
    // ======================================================================

    /**
     * Alasan absensi belum/tidak bisa dilakukan; null bila boleh absen.
     * Absensi hanya dibuka saat rapat BERLANGSUNG (sama dengan status "Berlangsung" di Jadwal Rapat):
     * pada hari rapat, dari jam mulai sampai jam selesai (bila jam selesai kosong: mulai + 2 jam).
     */
    public static function alasanTutup(Rapat $r): ?string
    {
        $st = strtolower(trim((string) $r->status));

        if (str_contains($st, 'batal')) {
            return 'Rapat ini dibatalkan.';
        }
        if (str_contains($st, 'tunda')) {
            return 'Rapat ini ditunda.';
        }
        if ($st === 'draft') {
            return 'Rapat ini masih berstatus draft.';
        }

        // rapat yang sudah ditandai selesai (Sudah Terlaksana) tidak menerima absensi lagi
        $belumTerlaksana = str_contains($st, 'belum') || str_contains($st, 'jadwal');
        if (! $belumTerlaksana && (str_contains($st, 'laksana') || str_contains($st, 'selesai'))) {
            return 'Rapat ini sudah selesai, absensi ditutup.';
        }

        if (! $r->tanggal->isToday()) {
            return $r->tanggal->isFuture()
                ? 'Absensi dibuka saat rapat berlangsung, pada ' . $r->tanggal->translatedFormat('d F Y')
                    . ' pukul ' . substr((string) $r->waktu, 0, 5) . '.'
                : 'Rapat ini sudah berakhir, absensi ditutup.';
        }

        [$mulai, $akhir] = self::jendela($r);
        $sekarang = now();

        if ($sekarang->lt($mulai)) {
            return 'Absensi dibuka saat rapat dimulai pukul ' . $mulai->format('H:i') . '.';
        }
        if ($sekarang->gt($akhir)) {
            return 'Rapat sudah berakhir pukul ' . $akhir->format('H:i') . ', absensi ditutup.';
        }

        return null;
    }

    /** Waktu rapat berlangsung: [mulai, selesai] pada tanggal rapat */
    private static function jendela(Rapat $r): array
    {
        $tgl     = $r->tanggal->toDateString();
        $mulai   = Carbon::parse($tgl . ' ' . substr((string) $r->waktu, 0, 8));
        $selesai = $r->getAttributes()['waktu_selesai'] ?? null;
        $akhir   = $selesai
            ? Carbon::parse($tgl . ' ' . substr((string) $selesai, 0, 8))
            : $mulai->copy()->addHours(2);

        return [$mulai, $akhir];
    }

    // ======================================================================
    //  DATA
    // ======================================================================

    /** Baris absensi untuk rapat ini (nama rapat + tanggal) */
    public static function kehadiran(Rapat $r): Collection
    {
        return DB::table('tabel_absensi')
            ->whereRaw('TRIM(nama_rapat) = ?', [trim((string) $r->nama_rapat)])
            ->whereDate('waktu_absensi', $r->tanggal->toDateString())
            ->orderBy('waktu_absensi')
            ->get(['nama_lengkap', 'nama_unit', 'nomor_wa', 'waktu_absensi']);
    }

    /** Peserta undangan = anggota kelompok rapat */
    public static function undangan(Rapat $r): Collection
    {
        return KelompokRapatDetail::where('id_kelompok', $r->id_kelompok)
            ->orderBy('nama_lengkap')
            ->get(['nama_lengkap', 'nomor_wa'])
            ->map(fn ($d) => [
                'nama'  => trim((string) $d->nama_lengkap),
                'nomor' => trim((string) $d->nomor_wa),
                'norm'  => self::nomor($d->nomor_wa),
            ])
            ->filter(fn ($d) => $d['norm'] !== '')
            ->unique('norm')
            ->values();
    }

    /** Ringkasan untuk halaman Daftar Hadir (dipakai halaman dan endpoint JSON) */
    public static function ringkasan(Rapat $r): array
    {
        $hadir    = self::kehadiran($r);
        $undangan = self::undangan($r);

        // Daftar hadir hanya memuat anggota yang diundang ke rapat ini
        $anggota      = $undangan->pluck('norm')->flip();
        $hadirAnggota = $hadir->filter(fn ($x) => $anggota->has(self::nomor($x->nomor_wa)));

        $sudah = $hadirAnggota->map(fn ($x) => self::nomor($x->nomor_wa))->flip();
        $belum = $undangan->reject(fn ($u) => $sudah->has($u['norm']))->values();

        // satu orang yang tercatat lebih dari sekali ditampilkan sekali
        $hadirUnik = $hadirAnggota
            ->unique(fn ($x) => self::nomor($x->nomor_wa))
            ->values();

        $jumlahHadir    = $hadirUnik->count();
        $jumlahUndangan = $undangan->count();

        return [
            'hadir' => $hadirUnik->map(fn ($x) => [
                'nama'  => trim((string) $x->nama_lengkap),
                'unit'  => trim((string) $x->nama_unit),
                'waktu' => date('H:i', strtotime((string) $x->waktu_absensi)),
            ])->all(),
            'belum' => $belum->map(fn ($u) => ['nama' => $u['nama'], 'nomor' => $u['nomor']])->all(),
            'jumlah_hadir'    => $jumlahHadir,
            'jumlah_undangan' => $jumlahUndangan,
            'persen'          => $jumlahUndangan > 0 ? min(100, (int) round($jumlahHadir / $jumlahUndangan * 100)) : 0,
        ];
    }

    // ======================================================================
    //  CATAT KEHADIRAN
    // ======================================================================

    /**
     * @return array{status:string, nama?:string, waktu?:string}
     *   status: ok | sudah | tidak_valid | tidak_terdaftar | gagal
     */
    public static function catat(Rapat $r, string $input): array
    {
        $norm = self::nomor($input);
        if (strlen($norm) < 9) {
            return ['status' => 'tidak_valid'];
        }

        $diundang = self::undangan($r)->firstWhere('norm', $norm);
        if (! $diundang) {
            return ['status' => 'tidak_terdaftar'];
        }

        $sudah = self::kehadiran($r)->first(fn ($x) => self::nomor($x->nomor_wa) === $norm);
        if ($sudah) {
            return [
                'status' => 'sudah',
                'nama'   => $diundang['nama'],
                'waktu'  => date('H:i', strtotime((string) $sudah->waktu_absensi)),
            ];
        }

        $peg = Pegawai::where('nomor_wa', $diundang['nomor'])->first();

        try {
            DB::table('tabel_absensi')->insert([
                'waktu_absensi'        => now()->toDateTimeString(),
                'nama_lengkap'         => $diundang['nama'],
                'nik_or_wa'            => $peg?->nik ?: $diundang['nomor'],
                'jenis_kelamin'        => $peg?->jenis_kelamin,
                'nama_unit'            => $peg?->nama_unit,
                'nomor_wa'             => $diundang['nomor'],
                'nama_rapat'           => trim((string) $r->nama_rapat),
                'tempat_rapat'         => (string) $r->tempat_rapat,
                'signature_image_path' => '',
            ]);
        } catch (\Throwable $e) {
            Log::error('Absensi gagal disimpan: ' . $e->getMessage());

            return ['status' => 'gagal'];
        }

        return ['status' => 'ok', 'nama' => $diundang['nama'], 'waktu' => now()->format('H:i')];
    }
}