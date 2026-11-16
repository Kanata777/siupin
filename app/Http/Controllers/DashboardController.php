<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        // Jika bulan ini belum ada rapat, tampilkan bulan terakhir yang punya data
        $adaData         = DB::table('rapat')->exists();
        $periodeOtomatis = false;
        if ($adaData && !$request->has('bulan') && !$request->has('dari') && !$request->has('sampai')) {
            $adaDiPeriode = DB::table('rapat')
                ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])->exists();
            if (!$adaDiPeriode && ($terakhir = DB::table('rapat')->max('tanggal'))) {
                $t               = Carbon::parse($terakhir);
                $dari            = $t->copy()->startOfMonth()->startOfDay();
                $sampai          = $t->copy()->endOfMonth()->startOfDay();
                $periodeOtomatis = true;
            }
        }

        // Navigasi bulan sebelum / berikutnya
        $bulanSebelum = $dari->copy()->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        $bulanBerikut = $dari->copy()->startOfMonth()->addMonthNoOverflow()->format('Y-m');

        // Pintasan periode
        $mm = DB::table('rapat')->selectRaw('MIN(tanggal) AS a, MAX(tanggal) AS b')->first();
        $preset = [
            'Bulan ini'  => route('dashboard', ['bulan' => now()->format('Y-m')]),
            'Tahun ini'  => route('dashboard', ['dari' => now()->startOfYear()->toDateString(),  'sampai' => now()->endOfYear()->toDateString()]),
            'Semua data' => ($mm && $mm->a) ? route('dashboard', ['dari' => $mm->a, 'sampai' => $mm->b]) : route('dashboard'),
        ];

        // ---------- Rapat pada periode ----------
        $rapat = $this->ambilRapat($dari, $sampai);
        $total = $rapat->count();

        $jumlah = [
            'selesai'     => $rapat->where('kelompok', 'selesai')->count(),
            'berlangsung' => $rapat->where('kelompok', 'berlangsung')->count(),
            'dibatalkan'  => $rapat->where('kelompok', 'dibatalkan')->count(),
            'belum'       => $rapat->where('kelompok', 'belum')->count(),
            'tertunda'    => $rapat->where('kelompok', 'tertunda')->count(),
            'lainnya'     => $rapat->where('kelompok', 'lainnya')->count(),
        ];
        // Status yang tidak dikenali (untuk ditampilkan di legenda)
        $statusLain = $rapat->where('kelompok', 'lainnya')
            ->groupBy(fn ($r) => trim((string) $r->status) !== '' ? trim((string) $r->status) : '(kosong)')
            ->map->count()->all();

        $persen = array_map(fn ($n) => $total ? (int) round($n / $total * 100) : 0, $jumlah);

        // Perubahan dibanding periode sebelumnya (panjang sama)
        $hari       = (int) $dari->diffInDays($sampai) + 1;
        $prevSampai = $dari->copy()->subDay();
        $prevDari   = $prevSampai->copy()->subDays($hari - 1);
        $prevTotal  = $this->ambilRapat($prevDari, $prevSampai)->count();
        $perubahan  = $prevTotal > 0 ? (int) round(($total - $prevTotal) / $prevTotal * 100) : null;

        // ---------- Tren per minggu ----------
        $tren = $this->tren($dari, $sampai, $rapat);

        // ---------- Kehadiran ----------
        $ukuranKelompok = DB::table('kelompok_rapat_detail')
            ->select('id_kelompok', DB::raw('COUNT(*) AS n'))
            ->groupBy('id_kelompok')->pluck('n', 'id_kelompok')->all();

        $undangan = $rapat->whereIn('kelompok', ['selesai', 'berlangsung'])
            ->sum(fn ($r) => $ukuranKelompok[$r->id_kelompok] ?? 0);

        $awal  = $dari->toDateString() . ' 00:00:00';
        $akhir = $sampai->toDateString() . ' 23:59:59';

        $hadir = DB::table('tabel_absensi')->whereBetween('waktu_absensi', [$awal, $akhir])->count();
        $persenHadir = $undangan > 0 ? min(100, (int) round($hadir / $undangan * 100)) : 0;

        // Daftar nama peserta yang hadir (digabung per orang)
        $daftarHadir = $this->daftarHadir($awal, $akhir);

        // ---------- Rekap per unit ----------
        $perUnit = $this->perUnit($rapat, $awal, $akhir);

        // ---------- Notulensi & dokumentasi ----------
        $hasil = DB::table('hasil_rapat')
            ->whereIn('id_rapat', $rapat->pluck('id_rapat')->all())
            ->get(['id_rapat', 'notulensi_path', 'dokumentasi1_path', 'dokumentasi2_path']);

        $nNotulensi = $hasil->filter(fn ($h) => filled($h->notulensi_path))->pluck('id_rapat')->unique()->count();
        $nDokumen   = $hasil->filter(fn ($h) => filled($h->dokumentasi1_path) || filled($h->dokumentasi2_path))
                            ->pluck('id_rapat')->unique()->count();
        $notulensi  = ['jumlah' => $nNotulensi, 'persen' => $total ? (int) round($nNotulensi / $total * 100) : 0];
        $dokumentasi = ['jumlah' => $nDokumen,  'persen' => $total ? (int) round($nDokumen / $total * 100) : 0];

        return view('dashboard.index', compact(
            'dari', 'sampai', 'total', 'jumlah', 'persen', 'perubahan', 'tren',
            'undangan', 'hadir', 'persenHadir', 'daftarHadir', 'perUnit', 'notulensi', 'dokumentasi',
            'statusLain', 'adaData', 'periodeOtomatis', 'preset', 'bulanSebelum', 'bulanBerikut'
        ));
    }

    /** Periode dari ?dari=&sampai= ; default: bulan berjalan. */
    private function rentang(Request $request): array
    {
        // ?bulan=2026-11  ->  1 November 2026 s/d 30 November 2026 (awal sampai akhir bulan)
        $bulan = (string) $request->query('bulan');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            $awal = Carbon::createFromFormat('!Y-m', $bulan)->startOfMonth();
            return [$awal, $awal->copy()->endOfMonth()->startOfDay()];
        }

        $baca = function ($v, Carbon $default) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)) {
                return $default;
            }
            try {
                return Carbon::createFromFormat('!Y-m-d', $v);
            } catch (\Throwable $e) {
                return $default;
            }
        };

        $dari   = $baca($request->query('dari'),   now()->startOfMonth()->startOfDay());
        $sampai = $baca($request->query('sampai'), now()->endOfMonth()->startOfDay());

        return $sampai->lt($dari) ? [$sampai, $dari] : [$dari, $sampai];
    }

    /**
     * Semua rapat pada periode: tabel rapat + rapat yang hanya tercatat
     * di rapat_batal_tertunda. Tiap baris diberi atribut ->kelompok
     * (selesai / berlangsung / dibatalkan / lainnya).
     */
    private function ambilRapat(Carbon $dari, Carbon $sampai)
    {
        $d = $dari->toDateString();
        $s = $sampai->toDateString();

        $baris = DB::select("
            SELECT id_rapat, tanggal, id_kelompok, LOWER(TRIM(status)) AS status
              FROM rapat
             WHERE tanggal BETWEEN ? AND ?
            UNION
            SELECT b.id_rapat, b.tanggal, b.id_kelompok,
                   LOWER(TRIM(b.status COLLATE utf8mb4_general_ci))
              FROM rapat_batal_tertunda b
             WHERE b.tanggal BETWEEN ? AND ?
               AND b.id_rapat NOT IN (SELECT id_rapat FROM rapat)
        ", [$d, $s, $d, $s]);

        return collect($baris)->unique('id_rapat')->values()->map(function ($r) {
            $r->kelompok = $this->klasifikasi((string) $r->status, (string) $r->tanggal);
            return $r;
        });
    }

    /**
     * Mengelompokkan nilai kolom rapat.status:
     *   Batal                         -> dibatalkan
     *   Tertunda                      -> tertunda
     *   Terlaksana / Sudah Terlaksana -> selesai
     *   Belum Terlaksana              -> berlangsung bila tanggalnya hari ini,
     *                                    selain itu -> belum
     */
    private function klasifikasi(string $status, string $tanggal): string
    {
        $s = strtolower(trim($status));

        if (str_contains($s, 'batal'))  { return 'dibatalkan'; }
        if (str_contains($s, 'tunda'))  { return 'tertunda'; }
        if (str_contains($s, 'belum'))  { return $tanggal === now()->toDateString() ? 'berlangsung' : 'belum'; }
        if (str_contains($s, 'selesai') || str_contains($s, 'laksana')) { return 'selesai'; }
        if (str_contains($s, 'berlangsung') || str_contains($s, 'jalan')) { return 'berlangsung'; }

        return 'lainnya';
    }

    /** Jumlah rapat per minggu (blok 7 hari; blok terakhir sampai akhir periode). */
    private function tren(Carbon $dari, Carbon $sampai, $rapat): array
    {
        $hari   = (int) $dari->diffInDays($sampai) + 1;
        $minggu = max(1, intdiv($hari, 7));
        $label  = [];
        $data   = [];

        // Periode panjang (> 100 hari): per bulan
        if ($hari > 100) {
            for ($b = $dari->copy()->startOfMonth(); $b->lte($sampai); $b->addMonth()) {
                $kunci   = $b->format('Y-m');
                $label[] = [$b->translatedFormat('M Y')];
                $data[]  = $rapat->filter(fn ($r) => substr((string) $r->tanggal, 0, 7) === $kunci)->count();
            }
            return ['label' => $label, 'data' => $data];
        }

        for ($i = 0; $i < $minggu; $i++) {
            $mulai = $dari->copy()->addDays($i * 7);
            $akhir = $i === $minggu - 1 ? $sampai->copy() : $mulai->copy()->addDays(6);

            $rentang = $mulai->month === $akhir->month
                ? $mulai->translatedFormat('j') . '–' . $akhir->translatedFormat('j M')
                : $mulai->translatedFormat('j M') . '–' . $akhir->translatedFormat('j M');

            $label[] = ['Minggu ' . ($i + 1), '(' . $rentang . ')'];
            $data[]  = $rapat->filter(fn ($r) => $r->tanggal >= $mulai->toDateString()
                                              && $r->tanggal <= $akhir->toDateString())->count();
        }

        return ['label' => $label, 'data' => $data];
    }

    /**
     * Daftar nama peserta yang hadir pada periode.
     * Orang yang sama digabung, `kali` = berapa kali absen.
     */
    private function daftarHadir(string $awal, string $akhir): array
    {
        $rows = DB::table('tabel_absensi')
            ->selectRaw("TRIM(nama_lengkap) AS peserta,
                         COALESCE(NULLIF(TRIM(nama_unit), ''), 'Tanpa Unit') AS unit,
                         TRIM(nama_rapat) AS rapat,
                         DATE(waktu_absensi) AS tgl,
                         COUNT(*) AS kali")
            ->whereBetween('waktu_absensi', [$awal, $akhir])
            ->whereRaw("TRIM(COALESCE(nama_lengkap, '')) <> ''")
            ->groupBy('peserta', 'unit', 'rapat', 'tgl')
            ->orderBy('peserta')
            ->get();

        // Kunci rapat = nama rapat + tanggal absensi (nama rapat bisa berulang di tanggal berbeda)
        $kunci = fn ($r) => $r->rapat . '||' . $r->tgl;

        $baris = $rows->map(fn ($r) => [
            'peserta'  => $r->peserta,
            'unit'     => $r->unit,
            'kali'     => (int) $r->kali,
            'id_rapat' => $kunci($r),
        ])->all();

        // Pilihan dropdown: rapat yang memang punya absensi pada periode
        $opsi = $rows->unique($kunci)
            ->sortBy([['tgl', 'desc'], ['rapat', 'asc']])
            ->map(fn ($r) => [
                'id'   => $kunci($r),
                'nama' => ($r->rapat !== '' ? $r->rapat : 'Tanpa nama rapat')
                          . ' (' . Carbon::parse($r->tgl)->translatedFormat('j M Y') . ')',
            ])->values()->all();

        return ['rapat' => $opsi, 'baris' => $baris, 'bisaFilter' => true];
    }

    /**
     * Rekap per unit: rapat yang melibatkan unit tsb
     * (anggota kelompok rapat -> pegawai.nama_unit),
     * kehadiran = absensi unit / undangan unit.
     */
    private function perUnit($rapat, string $awal, string $akhir)
    {
        // Anggota tiap kelompok dikelompokkan per unit (join lewat nomor WA)
        $unitKelompok = [];
        $anggota = DB::select("
            SELECT d.id_kelompok,
                   COALESCE(NULLIF(TRIM(p.nama_unit), ''), 'Tanpa Unit') AS unit,
                   COUNT(*) AS n
              FROM kelompok_rapat_detail d
              JOIN pegawai p ON p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci
             GROUP BY d.id_kelompok, unit
        ");
        foreach ($anggota as $a) {
            $unitKelompok[$a->id_kelompok][$a->unit] = (int) $a->n;
        }

        $hasil = [];
        foreach ($rapat as $r) {
            foreach ($unitKelompok[$r->id_kelompok] ?? [] as $unit => $n) {
                $hasil[$unit] ??= ['unit' => $unit, 'jumlah' => 0, 'selesai' => 0,
                                   'berlangsung' => 0, 'dibatalkan' => 0, 'undangan' => 0];
                $hasil[$unit]['jumlah']++;
                if (isset($hasil[$unit][$r->kelompok])) {
                    $hasil[$unit][$r->kelompok]++;
                }
                if (in_array($r->kelompok, ['selesai', 'berlangsung'], true)) {
                    $hasil[$unit]['undangan'] += $n;
                }
            }
        }

        $hadirUnit = DB::table('tabel_absensi')
            ->selectRaw("COALESCE(NULLIF(TRIM(nama_unit), ''), 'Tanpa Unit') AS unit, COUNT(*) AS n")
            ->whereBetween('waktu_absensi', [$awal, $akhir])
            ->groupBy('unit')->pluck('n', 'unit')->all();

        return collect($hasil)->map(function ($u) use ($hadirUnit) {
            $hadir = $hadirUnit[$u['unit']] ?? 0;
            $u['kehadiran'] = $u['undangan'] > 0 ? min(100, (int) round($hadir / $u['undangan'] * 100)) : null;
            return $u;
        })->sortByDesc('jumlah')->values();
    }
}