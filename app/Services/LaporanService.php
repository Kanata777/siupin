<?php

// Simpan sebagai: app/Services/LaporanService.php (ganti SELURUH isi file lama)

namespace App\Services;

use App\Models\KelompokRapat;
use App\Models\Rapat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Daftar laporan SIUPIN + cara mengambil datanya dari database siupin_nh.
 * Menambah laporan baru: tambahkan di daftar() dan buat method q<Nama> (query)
 * atau a<Nama> (data hasil hitung) + petakan() bila memakai query.
 *
 * Hak akses:
 *  - Super Admin : semua laporan, seluruh data.
 *  - User biasa  : hanya laporan di UNTUK_USER, dan hanya data dari rapat yang dia buat.
 *    Laporan data master (pegawai, kelompok, anggota, tembusan) dan absensi lama
 *    (tidak punya pemilik) hanya untuk Super Admin.
 */
class LaporanService
{
    public const GRUP = [
        'rapat'   => 'Rekap Rapat',
        'hadir'   => 'Kehadiran',
        'dokumen' => 'Notulensi & Dokumentasi',
        'master'  => 'Kelompok & Data Master',
    ];

    public const STATUS_LABEL = [
        'mendatang'   => 'Mendatang',
        'berlangsung' => 'Berlangsung',
        'selesai'     => 'Selesai',
        'terlewat'    => 'Terlewat',
        'tertunda'    => 'Tertunda',
        'draft'       => 'Draft',
        'dibatalkan'  => 'Dibatalkan',
        'lainnya'     => 'Lainnya',
    ];

    /** Laporan yang boleh dibuka User biasa (semuanya dibatasi ke rapat miliknya) */
    private const UNTUK_USER = ['rapat', 'per_unit', 'ruang', 'batal', 'kehadiran', 'notulensi'];

    /** Rapat yang kelompoknya punya anggota dari unit tertentu (%s = alias tabel yang punya id_kelompok) */
    private const UNIT_ADA = "EXISTS (SELECT 1 FROM kelompok_rapat_detail d
        JOIN pegawai p ON p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci
        WHERE d.id_kelompok = %s.id_kelompok AND p.nama_unit = ?)";

    // ======================================================================
    //  DAFTAR LAPORAN
    // ======================================================================
    public function daftar(): array
    {
        $semua = [
            'rapat' => [
                'judul' => 'Daftar Rapat', 'grup' => 'rapat', 'ikon' => 'kalender',
                'deskripsi' => 'Seluruh rapat: jadwal, tempat, kelompok, jumlah peserta, dan status.',
                'filter' => 'Periode · Unit · Tempat · Status',
                'kolom' => ['Nama Rapat', 'Agenda', 'Tanggal', 'Waktu', 'Tempat', 'Kelompok', 'Peserta', 'Status', 'Pengundang'],
            ],
            'per_unit' => [
                'judul' => 'Rekap Rapat per Unit', 'grup' => 'rapat', 'ikon' => 'gedung',
                'deskripsi' => 'Jumlah rapat, undangan, dan tingkat kehadiran tiap unit.',
                'filter' => 'Periode · Unit · Tempat',
                'kolom' => ['Unit', 'Jumlah Rapat', 'Selesai', 'Dibatalkan', 'Belum Terlaksana / Tertunda', 'Undangan', 'Hadir', 'Kehadiran (%)'],
            ],
            'ruang' => [
                'judul' => 'Rekap Ruang Rapat', 'grup' => 'rapat', 'ikon' => 'pin',
                'deskripsi' => 'Pemakaian setiap ruang / tempat rapat dan total pesertanya.',
                'filter' => 'Periode · Tempat',
                'kolom' => ['Ruang / Tempat', 'Gedung', 'Total Rapat', 'Selesai', 'Dibatalkan', 'Tertunda', 'Belum Terlaksana', 'Total Peserta'],
            ],
            'batal' => [
                'judul' => 'Rapat Dibatalkan / Tertunda', 'grup' => 'rapat', 'ikon' => 'silang',
                'deskripsi' => 'Catatan rapat yang dibatalkan atau ditunda.',
                'filter' => 'Periode · Unit · Tempat',
                'kolom' => ['Nama Rapat', 'Tanggal', 'Waktu', 'Tempat', 'Kelompok', 'Peserta', 'Status', 'Pengundang'],
            ],
            'kehadiran' => [
                'judul' => 'Daftar Kehadiran Rapat', 'grup' => 'hadir', 'ikon' => 'cek',
                'deskripsi' => 'Absensi peserta pada setiap rapat (waktu absen, unit, dan tanda tangan).',
                'filter' => 'Periode · Unit · Tempat',
                'kolom' => ['Waktu Absen', 'Nama', 'NIK / No. WA', 'Jenis Kelamin', 'Unit', 'Nomor WA', 'Rapat', 'Tempat', 'Tanda Tangan'],
            ],
            'absensi_umum' => [
                'judul' => 'Absensi Umum (Data Lama)', 'grup' => 'hadir', 'ikon' => 'user',
                'deskripsi' => 'Data absensi dari sistem sebelumnya (tabel absensi).',
                'filter' => 'Periode · Unit',
                'kolom' => ['Waktu Absen', 'Nama', 'NIK', 'Jenis Kelamin', 'Unit', 'Nomor HP', 'Tanda Tangan'],
            ],
            'notulensi' => [
                'judul' => 'Notulensi & Dokumentasi', 'grup' => 'dokumen', 'ikon' => 'catatan',
                'deskripsi' => 'Hasil rapat: jumlah hadir, berkas notulensi, dan dokumentasi foto.',
                'filter' => 'Periode · Unit · Tempat',
                'kolom' => ['Nama Rapat', 'Kelompok', 'Tanggal', 'Waktu', 'Tempat', 'Peserta Hadir', 'Status Rapat', 'Notulensi', 'Dokumentasi 1', 'Dokumentasi 2'],
            ],
            'kelompok' => [
                'judul' => 'Kelompok Rapat', 'grup' => 'master', 'ikon' => 'users',
                'deskripsi' => 'Daftar kelompok peserta beserta jumlah anggota dan unitnya.',
                'filter' => 'Tidak terpengaruh filter',
                'kolom' => ['Nama Kelompok', 'Jumlah Anggota', 'Unit / Keterangan'],
            ],
            'anggota' => [
                'judul' => 'Anggota Kelompok Rapat', 'grup' => 'master', 'ikon' => 'user-plus',
                'deskripsi' => 'Seluruh anggota di setiap kelompok rapat.',
                'filter' => 'Unit',
                'kolom' => ['Kelompok', 'Nama', 'Nomor WA', 'Unit', 'Jabatan'],
            ],
            'pegawai' => [
                'judul' => 'Data Pegawai', 'grup' => 'master', 'ikon' => 'user',
                'deskripsi' => 'Data pegawai: NIK, jabatan, unit, dan nomor WhatsApp.',
                'filter' => 'Unit',
                'kolom' => ['NIK', 'Nama Lengkap', 'Jenis Kelamin', 'Jabatan', 'Unit', 'Nomor WA'],
            ],
            'tembusan' => [
                'judul' => 'Kelompok Tembusan', 'grup' => 'master', 'ikon' => 'laporan',
                'deskripsi' => 'Penerima tembusan pada setiap kelompok tembusan.',
                'filter' => 'Tidak terpengaruh filter',
                'kolom' => ['Kelompok Tembusan', 'Nama', 'Nomor WA'],
            ],
        ];

        if (Rapat::bolehSemua()) {
            return $semua;
        }

        return array_intersect_key($semua, array_flip(self::UNTUK_USER));
    }

    /** Baca filter dari request */
    public function filter(Request $r): array
    {
        $tgl = function ($v) {
            return (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) ? (string) $v : null;
        };
        $dari   = $tgl($r->query('dari'));
        $sampai = $tgl($r->query('sampai'));
        if ($dari && $sampai && $sampai < $dari) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $tempat = (int) $r->query('tempat');
        $status = (string) $r->query('status');

        return [
            'dari'        => $dari,
            'sampai'      => $sampai,
            'unit'        => trim((string) $r->query('unit')),
            'tempat'      => $tempat,
            'tempat_nama' => $tempat ? (string) DB::table('tempat_rapat')->where('id_tempat', $tempat)->value('nama_tempat') : '',
            'status'      => array_key_exists($status, self::STATUS_LABEL) ? $status : '',
        ];
    }

    /** Ringkasan filter untuk judul cetakan */
    public function ringkasanFilter(array $f): string
    {
        $bagian = [];
        $fmt = fn ($t) => date('d-m-Y', strtotime($t));
        if ($f['dari'] || $f['sampai']) {
            $bagian[] = 'Periode: ' . ($f['dari'] ? $fmt($f['dari']) : '…') . ' s/d ' . ($f['sampai'] ? $fmt($f['sampai']) : '…');
        } else {
            $bagian[] = 'Periode: semua tanggal';
        }
        if ($f['unit'] !== '')        { $bagian[] = 'Unit: ' . $f['unit']; }
        if ($f['tempat_nama'] !== '') { $bagian[] = 'Tempat: ' . $f['tempat_nama']; }
        if ($f['status'] !== '')      { $bagian[] = 'Status: ' . self::STATUS_LABEL[$f['status']]; }

        return implode(' · ', $bagian);
    }

    // ======================================================================
    //  AKSES DATA
    // ======================================================================
    public function total(string $jenis, array $f): int
    {
        $this->pastikanBoleh($jenis);

        $q = $this->query($jenis, $f);

        return $q ? (int) $q->count() : count($this->agregat($jenis, $f));
    }

    /** Baris laporan (tanpa kolom "No"), satu per satu */
    public function baris(string $jenis, array $f, ?int $batas = null): \Generator
    {
        $this->pastikanBoleh($jenis);

        $q = $this->query($jenis, $f);

        if ($q) {
            $sumber = $batas ? $q->limit($batas)->get() : $q->cursor();
            foreach ($sumber as $r) {
                yield $this->petakan($jenis, $r);
            }
            return;
        }

        $data = $this->agregat($jenis, $f);
        if ($batas) {
            $data = array_slice($data, 0, $batas);
        }
        foreach ($data as $baris) {
            yield $baris;
        }
    }

    /** Lapisan pengaman: jenis laporan yang tidak boleh dibuka user ini = 404 */
    private function pastikanBoleh(string $jenis): void
    {
        abort_unless(isset($this->daftar()[$jenis]), 404);
    }

    private function query(string $jenis, array $f)
    {
        return match ($jenis) {
            'rapat'        => $this->qRapat($f),
            'batal'        => $this->qBatal($f),
            'kehadiran'    => $this->qKehadiran($f),
            'absensi_umum' => $this->qAbsensiUmum($f),
            'notulensi'    => $this->qNotulensi($f),
            'anggota'      => $this->qAnggota($f),
            'pegawai'      => $this->qPegawai($f),
            'tembusan'     => $this->qTembusan(),
            default        => null,
        };
    }

    private function agregat(string $jenis, array $f): array
    {
        return match ($jenis) {
            'per_unit' => $this->aPerUnit($f),
            'ruang'    => $this->aRuang($f),
            'kelompok' => $this->aKelompok(),
            default    => [],
        };
    }

    // ======================================================================
    //  QUERY
    // ======================================================================
    private function qRapat(array $f)
    {
        $case = Rapat::ekspresiStatus(Rapat::punyaKolom('waktu_selesai'));

        // terlihat(): Super Admin = semua rapat, User biasa = rapat miliknya
        $q = Rapat::query()->terlihat()->selectRaw(
            "rapat.*, ($case) AS status_tampil,
             COALESCE(rapat.jumlah_peserta,
                (SELECT COUNT(*) FROM kelompok_rapat_detail dd WHERE dd.id_kelompok = rapat.id_kelompok)) AS jumlah_tampil"
        );
        $this->rentangTanggal($q, 'rapat.tanggal', $f);
        if ($f['tempat'])      { $q->where('rapat.id_tempat', $f['tempat']); }
        if ($f['unit'] !== '') { $q->whereRaw(sprintf(self::UNIT_ADA, 'rapat'), [$f['unit']]); }
        if ($f['status'] !== '') { $q->whereRaw("($case) = ?", [$f['status']]); }

        return $q->orderBy('rapat.tanggal')->orderBy('rapat.waktu')->orderBy('rapat.id_rapat');
    }

    private function qBatal(array $f)
    {
        $q = DB::table('rapat_batal_tertunda as b')->select(
            'b.nama_rapat', 'b.tanggal', 'b.waktu', 'b.tempat_rapat', 'b.nama_kelompok',
            'b.jumlah_peserta', 'b.status', 'b.pengundang'
        );

        // Catatan tanpa rapat induk tidak punya pemilik, jadi hanya terlihat Super Admin
        if (!Rapat::bolehSemua()) {
            $q->whereIn('b.id_rapat', Rapat::terlihat()->select('rapat.id_rapat'));
        }

        $this->rentangTanggal($q, 'b.tanggal', $f);
        if ($f['tempat_nama'] !== '') { $q->where('b.tempat_rapat', $f['tempat_nama']); }
        if ($f['unit'] !== '')        { $q->whereRaw(sprintf(self::UNIT_ADA, 'b'), [$f['unit']]); }

        return $q->orderBy('b.tanggal')->orderBy('b.waktu');
    }

    private function qKehadiran(array $f)
    {
        $q = DB::table('tabel_absensi as a')->select(
            'a.waktu_absensi', 'a.nama_lengkap', 'a.nik_or_wa', 'a.jenis_kelamin', 'a.nama_unit',
            'a.nomor_wa', 'a.nama_rapat', 'a.tempat_rapat', 'a.signature_image_path'
        );
        $this->batasiAbsensi($q, 'a');
        $this->rentangWaktu($q, 'a.waktu_absensi', $f);
        if ($f['unit'] !== '')        { $q->where('a.nama_unit', $f['unit']); }
        if ($f['tempat_nama'] !== '') { $q->where('a.tempat_rapat', $f['tempat_nama']); }

        return $q->orderBy('a.waktu_absensi')->orderBy('a.id');
    }

    /** Hanya Super Admin (tabel lama tidak punya pemilik) */
    private function qAbsensiUmum(array $f)
    {
        $q = DB::table('absensi as a')->select(
            'a.waktu_absen', 'a.nama', 'a.nik', 'a.jenis_kelamin', 'a.unit', 'a.nomor_hp', 'a.signature_image_path'
        );
        $this->rentangWaktu($q, 'a.waktu_absen', $f);
        if ($f['unit'] !== '') { $q->where('a.unit', $f['unit']); }

        return $q->orderBy('a.waktu_absen')->orderBy('a.id');
    }

    private function qNotulensi(array $f)
    {
        $q = DB::table('hasil_rapat as h')->select(
            'h.nama_rapat', 'h.nama_kelompok', 'h.tanggal', 'h.waktu', 'h.tempat_rapat',
            'h.jumlah_peserta_hadir', 'h.status_rapat', 'h.notulensi_path', 'h.dokumentasi1_path', 'h.dokumentasi2_path'
        );

        if (!Rapat::bolehSemua()) {
            $q->whereIn('h.id_rapat', Rapat::terlihat()->select('rapat.id_rapat'));
        }

        $this->rentangTanggal($q, 'h.tanggal', $f);
        if ($f['tempat_nama'] !== '') { $q->where('h.tempat_rapat', $f['tempat_nama']); }
        if ($f['unit'] !== '')        { $q->whereRaw(sprintf(self::UNIT_ADA, 'h'), [$f['unit']]); }

        return $q->orderBy('h.tanggal')->orderBy('h.waktu');
    }

    /** Hanya Super Admin */
    private function qAnggota(array $f)
    {
        $unit = "(SELECT p.nama_unit FROM pegawai p WHERE p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci LIMIT 1)";
        $jab  = "(SELECT p.jabatan FROM pegawai p WHERE p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci LIMIT 1)";

        $q = DB::table('kelompok_rapat_detail as d')
            ->join('kelompok_rapat as k', 'k.id_kelompok', '=', 'd.id_kelompok')
            ->selectRaw("k.nama_kelompok, d.nama_lengkap, d.nomor_wa, $unit AS unit, $jab AS jabatan")
            ->where('k.nama_kelompok', 'not like', KelompokRapat::PREFIX_OTOMATIS . '%');

        if ($f['unit'] !== '') { $q->whereRaw("$unit = ?", [$f['unit']]); }

        return $q->orderBy('k.nama_kelompok')->orderBy('d.nama_lengkap');
    }

    /** Hanya Super Admin */
    private function qPegawai(array $f)
    {
        $q = DB::table('pegawai')->select('nik', 'nama_lengkap', 'jenis_kelamin', 'jabatan', 'nama_unit', 'nomor_wa');
        if ($f['unit'] !== '') { $q->where('nama_unit', $f['unit']); }

        return $q->orderBy('nama_unit')->orderBy('nama_lengkap');
    }

    /** Hanya Super Admin */
    private function qTembusan()
    {
        return DB::table('tembusan_detail as d')
            ->join('tembusan as t', 't.id_tembusan', '=', 'd.id_tembusan')
            ->select('t.nama_kelompok_tembusan', 'd.nama_lengkap', 'd.nomor_wa')
            ->orderBy('t.nama_kelompok_tembusan')->orderBy('d.nama_lengkap');
    }

    /** Ubah satu baris hasil query menjadi array sesuai urutan kolom */
    private function petakan(string $jenis, $r): array
    {
        $tgl  = fn ($t) => $t ? date('d-m-Y', strtotime((string) $t)) : '';
        $wkt  = fn ($t) => $t ? date('d-m-Y H:i', strtotime((string) $t)) : '';
        $jam  = fn ($t) => $t ? substr((string) $t, 0, 5) : '';
        $path = fn ($p) => filled($p) ? (string) $p : '-';

        switch ($jenis) {
            case 'rapat':
                $akhir = $r->getAttributes()['waktu_selesai'] ?? null;
                return [
                    $r->nama_rapat, $r->catatan_rapat, $r->tanggal->format('d-m-Y'),
                    $jam($r->waktu) . ($akhir ? ' - ' . $jam($akhir) : ''),
                    $r->tempat_rapat, $r->nama_kelompok, (int) $r->jumlah_tampil,
                    self::STATUS_LABEL[$r->status_tampil] ?? (string) $r->status, $r->pengundang,
                ];

            case 'batal':
                return [$r->nama_rapat, $tgl($r->tanggal), $jam($r->waktu), $r->tempat_rapat,
                        $r->nama_kelompok, (int) $r->jumlah_peserta, $r->status, $r->pengundang];

            case 'kehadiran':
                return [$wkt($r->waktu_absensi), $r->nama_lengkap, $r->nik_or_wa, $r->jenis_kelamin, $r->nama_unit,
                        $r->nomor_wa, $r->nama_rapat, $r->tempat_rapat, filled($r->signature_image_path) ? 'Ya' : '-'];

            case 'absensi_umum':
                return [$wkt($r->waktu_absen), $r->nama, $r->nik, $r->jenis_kelamin, $r->unit, $r->nomor_hp,
                        filled($r->signature_image_path) ? 'Ya' : '-'];

            case 'notulensi':
                return [$r->nama_rapat, $r->nama_kelompok, $tgl($r->tanggal), $jam($r->waktu), $r->tempat_rapat,
                        (int) $r->jumlah_peserta_hadir, $r->status_rapat ? ucfirst($r->status_rapat) : '',
                        $path($r->notulensi_path), $path($r->dokumentasi1_path), $path($r->dokumentasi2_path)];

            case 'anggota':
                return [$r->nama_kelompok, $r->nama_lengkap, $r->nomor_wa, $r->unit, $r->jabatan];

            case 'pegawai':
                return [$r->nik, $r->nama_lengkap, $r->jenis_kelamin, $r->jabatan, $r->nama_unit, $r->nomor_wa];

            case 'tembusan':
                return [$r->nama_kelompok_tembusan, $r->nama_lengkap, $r->nomor_wa];
        }

        return [];
    }

    // ======================================================================
    //  LAPORAN HASIL HITUNG
    // ======================================================================
    private function aPerUnit(array $f): array
    {
        $rapat = Rapat::query()->terlihat()->select('rapat.id_rapat', 'rapat.id_kelompok', 'rapat.status');
        $this->rentangTanggal($rapat, 'rapat.tanggal', $f);
        if ($f['tempat']) { $rapat->where('rapat.id_tempat', $f['tempat']); }
        $rapat = $rapat->get();

        // anggota tiap kelompok dikelompokkan per unit (dicocokkan lewat nomor WA)
        $peta = [];
        $anggota = DB::select("
            SELECT d.id_kelompok, COALESCE(NULLIF(TRIM(p.nama_unit), ''), 'Tanpa Unit') AS unit, COUNT(*) AS n
              FROM kelompok_rapat_detail d
              JOIN pegawai p ON p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci
             GROUP BY d.id_kelompok, unit
        ");
        foreach ($anggota as $a) {
            $peta[$a->id_kelompok][$a->unit] = (int) $a->n;
        }

        $hasil = [];
        foreach ($rapat as $r) {
            $g = $this->golongan($r->status);
            foreach ($peta[$r->id_kelompok] ?? [] as $unit => $n) {
                if ($f['unit'] !== '' && (string) $unit !== $f['unit']) {
                    continue;
                }
                $hasil[$unit] ??= ['unit' => (string) $unit, 'jumlah' => 0, 'selesai' => 0, 'batal' => 0, 'belum' => 0, 'undangan' => 0];
                $hasil[$unit]['jumlah']++;
                $hasil[$unit][$g]++;
                if ($g !== 'batal') {
                    $hasil[$unit]['undangan'] += $n;
                }
            }
        }

        $h = DB::table('tabel_absensi')
            ->selectRaw("COALESCE(NULLIF(TRIM(nama_unit), ''), 'Tanpa Unit') AS unit, COUNT(*) AS n")
            ->groupBy('unit');
        $this->batasiAbsensi($h, 'tabel_absensi');
        $this->rentangWaktu($h, 'waktu_absensi', $f);
        if ($f['tempat_nama'] !== '') { $h->where('tempat_rapat', $f['tempat_nama']); }
        $hadir = $h->pluck('n', 'unit')->all();

        usort($hasil, fn ($a, $b) => [$b['jumlah'], $a['unit']] <=> [$a['jumlah'], $b['unit']]);

        return array_map(function ($u) use ($hadir) {
            $jml = (int) ($hadir[$u['unit']] ?? 0);
            return [
                $u['unit'], $u['jumlah'], $u['selesai'], $u['batal'], $u['belum'], $u['undangan'], $jml,
                $u['undangan'] > 0 ? min(100, (int) round($jml / $u['undangan'] * 100)) : '',
            ];
        }, array_values($hasil));
    }

    private function aRuang(array $f): array
    {
        $ikat   = [];
        $join   = '';
        $where  = '';
        $having = '';

        if ($f['dari'])   { $join .= ' AND r.tanggal >= ?'; $ikat[] = $f['dari']; }
        if ($f['sampai']) { $join .= ' AND r.tanggal <= ?'; $ikat[] = $f['sampai']; }

        // User biasa: hanya rapat miliknya, dan hanya ruang yang pernah dia pakai
        if (!Rapat::bolehSemua()) {
            $join  .= ' AND r.user_id = ?';
            $ikat[] = (int) auth()->id();
            $having = 'HAVING COUNT(r.id_rapat) > 0';
        }

        // urutan binding harus sama dengan urutan tanda tanya di SQL: JOIN dulu, lalu WHERE
        if ($f['tempat']) { $where = ' WHERE t.id_tempat = ?'; $ikat[] = $f['tempat']; }

        $baris = DB::select("
            SELECT t.nama_tempat, t.gedung,
                   COUNT(r.id_rapat) AS total,
                   COALESCE(SUM(CASE WHEN LOWER(r.status) NOT LIKE '%belum%' AND LOWER(r.status) NOT LIKE '%batal%'
                                      AND (LOWER(r.status) LIKE '%laksana%' OR LOWER(r.status) LIKE '%selesai%')
                                THEN 1 ELSE 0 END), 0) AS selesai,
                   COALESCE(SUM(CASE WHEN LOWER(r.status) LIKE '%batal%' THEN 1 ELSE 0 END), 0) AS batal,
                   COALESCE(SUM(CASE WHEN LOWER(r.status) LIKE '%tunda%' THEN 1 ELSE 0 END), 0) AS tunda,
                   COALESCE(SUM(r.jumlah_peserta), 0) AS peserta
              FROM tempat_rapat t
              LEFT JOIN rapat r ON r.id_tempat = t.id_tempat $join
              $where
             GROUP BY t.id_tempat, t.nama_tempat, t.gedung
             $having
             ORDER BY total DESC, t.nama_tempat
        ", $ikat);

        return array_map(function ($b) {
            $total = (int) $b->total;
            $belum = max(0, $total - (int) $b->selesai - (int) $b->batal - (int) $b->tunda);
            return [$b->nama_tempat, $b->gedung, $total, (int) $b->selesai, (int) $b->batal, (int) $b->tunda, $belum, (int) $b->peserta];
        }, $baris);
    }

    /** Hanya Super Admin */
    private function aKelompok(): array
    {
        return array_map(
            fn ($g) => [$g['nama'], (int) $g['jumlah'], $g['keterangan']],
            KelompokRapat::ringkasan()
        );
    }

    // ======================================================================
    //  BANTUAN
    // ======================================================================

    /**
     * Absensi tidak punya id_rapat, jadi untuk User biasa hanya absensi yang cocok
     * (nama rapat + tanggal) dengan rapat miliknya yang dihitung. Sama dengan Dashboard.
     * $t = nama atau alias tabel absensi pada query.
     */
    private function batasiAbsensi($q, string $t): void
    {
        if (Rapat::bolehSemua()) {
            return;
        }

        $q->whereRaw(
            "EXISTS (SELECT 1 FROM rapat r
                      WHERE r.user_id = ?
                        AND TRIM(r.nama_rapat) = TRIM($t.nama_rapat)
                        AND r.tanggal = DATE($t.waktu_absensi))",
            [(int) auth()->id()]
        );
    }

    private function rentangTanggal($q, string $kolom, array $f): void
    {
        if ($f['dari'])   { $q->where($kolom, '>=', $f['dari']); }
        if ($f['sampai']) { $q->where($kolom, '<=', $f['sampai']); }
    }

    private function rentangWaktu($q, string $kolom, array $f): void
    {
        if ($f['dari'])   { $q->where($kolom, '>=', $f['dari'] . ' 00:00:00'); }
        if ($f['sampai']) { $q->where($kolom, '<=', $f['sampai'] . ' 23:59:59'); }
    }

    private function golongan($status): string
    {
        $s = strtolower(trim((string) $status));
        if (str_contains($s, 'batal')) {
            return 'batal';
        }
        if (!str_contains($s, 'belum') && (str_contains($s, 'laksana') || str_contains($s, 'selesai'))) {
            return 'selesai';
        }
        return 'belum';
    }
}