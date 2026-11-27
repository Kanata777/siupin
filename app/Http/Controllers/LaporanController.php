<?php

// Simpan sebagai: app/Http/Controllers/LaporanController.php (ganti SELURUH isi file lama)

namespace App\Http\Controllers;

use App\Services\LaporanService;
use App\Services\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class LaporanController extends Controller
{
    /** Jumlah baris maksimum per laporan pada tampilan cetak / PDF */
    private const BATAS_CETAK = 5000;
    private const BATAS_PRATINJAU = 100;

    // ======================================================================
    //  HALAMAN LAPORAN & REKAP
    // ======================================================================
    public function index(Request $request, LaporanService $svc)
    {
        Carbon::setLocale('id');

        $f      = $svc->filter($request);
        $daftar = $svc->daftar();   // sudah disaring sesuai hak akses (User biasa: laporan rapat miliknya saja)
        foreach ($daftar as $kunci => $d) {
            $daftar[$kunci]['total'] = $svc->total($kunci, $f);
        }

        // Hanya tampilkan grup yang punya laporan (User biasa tidak melihat grup "Data Master")
        $grup = array_intersect_key(LaporanService::GRUP, array_flip(array_column($daftar, 'grup')));

        $terpilih = array_values(array_intersect(array_keys($daftar), (array) $request->query('jenis', [])));
        $lihat    = (string) $request->query('lihat');
        if (!isset($daftar[$lihat])) {
            $lihat = '';
        }

        $units = DB::table('unit_pegawai')->orderBy('nama_unit')->pluck('nama_unit');
        if ($units->isEmpty()) {
            $units = DB::table('pegawai')->whereNotNull('nama_unit')->distinct()->orderBy('nama_unit')->pluck('nama_unit');
        }
        $tempatList = DB::table('tempat_rapat')->orderBy('nama_tempat')->get(['id_tempat', 'nama_tempat']);

        // periode cepat
        $dasar = $request->except(['dari', 'sampai', 'page']);
        $bln   = now();
        $lalu  = now()->subMonthNoOverflow();
        $preset = [
            'Bulan ini'     => route('laporan.index', $dasar + ['dari' => $bln->copy()->startOfMonth()->toDateString(), 'sampai' => $bln->copy()->endOfMonth()->toDateString()]),
            'Bulan lalu'    => route('laporan.index', $dasar + ['dari' => $lalu->copy()->startOfMonth()->toDateString(), 'sampai' => $lalu->copy()->endOfMonth()->toDateString()]),
            'Tahun ini'     => route('laporan.index', $dasar + ['dari' => $bln->copy()->startOfYear()->toDateString(), 'sampai' => $bln->copy()->endOfYear()->toDateString()]),
            'Semua tanggal' => route('laporan.index', $dasar),
        ];

        $fmt = fn ($t) => Carbon::parse($t)->translatedFormat('d M Y');
        $teksRentang = ($f['dari'] && $f['sampai']) ? $fmt($f['dari']) . ' – ' . $fmt($f['sampai'])
            : ($f['dari'] ? 'Mulai ' . $fmt($f['dari']) : ($f['sampai'] ? 'Sampai ' . $fmt($f['sampai']) : ''));

        $konfig = [
            'urlPratinjau' => route('laporan.pratinjau'),
            'urlUnduh'     => route('laporan.unduh'),
            'urlCetak'     => route('laporan.cetak'),
            'lihat'        => $lihat ?: null,
        ];

        return view('laporan.index', [
            'f' => $f, 'daftar' => $daftar, 'grup' => $grup, 'statusList' => LaporanService::STATUS_LABEL,
            'terpilih' => $terpilih, 'units' => $units, 'tempatList' => $tempatList,
            'preset' => $preset, 'teksRentang' => $teksRentang, 'konfig' => $konfig, 'lihat' => $lihat,
        ]);
    }

    // ======================================================================
    //  PRATINJAU (JSON)
    // ======================================================================
    public function pratinjau(Request $request, LaporanService $svc)
    {
        $daftar = $svc->daftar();
        $jenis  = (string) $request->query('jenis');
        abort_unless(isset($daftar[$jenis]), 404);

        $f      = $svc->filter($request);
        $baris  = iterator_to_array($svc->baris($jenis, $f, self::BATAS_PRATINJAU), false);

        return response()->json([
            'judul' => $daftar[$jenis]['judul'],
            'kolom' => array_merge(['No'], $daftar[$jenis]['kolom']),
            'baris' => $baris,
            'total' => $svc->total($jenis, $f),
            'batas' => self::BATAS_PRATINJAU,
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    // ======================================================================
    //  UNDUH: Excel (.xlsx) atau CSV (ZIP bila lebih dari satu laporan)
    // ======================================================================
    public function unduh(Request $request, LaporanService $svc)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $daftar = $svc->daftar();
        $jenis  = $this->jenisDipilih($request, $daftar);
        if (!$jenis) {
            return back()->with('galat', 'Pilih minimal satu laporan untuk diunduh.');
        }

        $f      = $svc->filter($request);
        $format = (string) $request->query('format', 'xlsx');
        $nama   = 'laporan-siupin-' . now()->format('Ymd-His');

        // ----- CSV satu laporan -----
        if ($format === 'csv' && count($jenis) === 1) {
            $j = $jenis[0];
            return response()->streamDownload(function () use ($svc, $daftar, $j, $f) {
                $h = fopen('php://output', 'w');
                $this->tulisCsv($h, $daftar[$j]['kolom'], $svc->baris($j, $f));
                fclose($h);
            }, $nama . '-' . Str::slug($daftar[$j]['judul']) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        // ----- CSV beberapa laporan -> ZIP -----
        if ($format === 'csv') {
            $zipPath = tempnam(sys_get_temp_dir(), 'lapzip');
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $sementara = [];

            foreach ($jenis as $j) {
                $tmp = tempnam(sys_get_temp_dir(), 'lapcsv');
                $h = fopen($tmp, 'w');
                $this->tulisCsv($h, $daftar[$j]['kolom'], $svc->baris($j, $f));
                fclose($h);
                $zip->addFile($tmp, Str::slug($daftar[$j]['judul']) . '.csv');
                $sementara[] = $tmp;
            }
            $zip->close();
            foreach ($sementara as $tmp) {
                @unlink($tmp);
            }

            return response()->download($zipPath, $nama . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
        }

        // ----- Excel: satu lembar per laporan -----
        $x = new XlsxWriter();
        foreach ($jenis as $j) {
            $x->tambah($daftar[$j]['judul'], $daftar[$j]['kolom'], $svc->baris($j, $f));
        }
        $path = tempnam(sys_get_temp_dir(), 'lapxlsx');
        $x->simpan($path);

        return response()->download($path, $nama . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ======================================================================
    //  CETAK / PDF (halaman siap cetak; "Simpan sebagai PDF" dari dialog cetak browser)
    // ======================================================================
    public function cetak(Request $request, LaporanService $svc)
    {
        @set_time_limit(0);
        Carbon::setLocale('id');

        $daftar = $svc->daftar();
        $jenis  = $this->jenisDipilih($request, $daftar);
        if (!$jenis) {
            return redirect()->route('laporan.index')->with('galat', 'Pilih minimal satu laporan untuk dicetak.');
        }

        $f    = $svc->filter($request);
        $data = [];
        foreach ($jenis as $j) {
            $data[] = [
                'judul' => $daftar[$j]['judul'],
                'kolom' => array_merge(['No'], $daftar[$j]['kolom']),
                'baris' => iterator_to_array($svc->baris($j, $f, self::BATAS_CETAK), false),
                'total' => $svc->total($j, $f),
            ];
        }

        return view('laporan.cetak', [
            'data'      => $data,
            'filter'    => $svc->ringkasanFilter($f),
            'dicetak'   => now()->translatedFormat('d F Y, H:i'),
            'batas'     => self::BATAS_CETAK,
            'pengguna'  => auth()->user()->name ?? 'SDM YNH',
        ]);
    }

    // ======================================================================
    //  BANTUAN
    // ======================================================================

    /** Kunci laporan yang dipilih, sesuai urutan daftar (jenis yang tidak boleh diakses otomatis terbuang) */
    private function jenisDipilih(Request $request, array $daftar): array
    {
        return array_values(array_intersect(array_keys($daftar), (array) $request->query('jenis', [])));
    }

    /** CSV: titik koma + BOM UTF-8 agar terbaca benar di Excel (pengaturan regional Indonesia) */
    private function tulisCsv($h, array $kolom, iterable $baris): void
    {
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_merge(['No'], $kolom), ';', '"', '');

        $no = 0;
        foreach ($baris as $b) {
            $isi = array_map(function ($v) {
                $v = (string) $v;
                // cegah rumus tersembunyi saat dibuka di Excel
                return ($v !== '' && in_array($v[0], ['=', '@'], true)) ? "'" . $v : $v;
            }, $b);
            fputcsv($h, array_merge([++$no], $isi), ';', '"', '');
        }
    }
}