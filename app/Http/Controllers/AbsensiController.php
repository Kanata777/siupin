<?php

// Simpan sebagai: app/Http/Controllers/AbsensiController.php (file baru)

namespace App\Http\Controllers;

use App\Models\Rapat;
use App\Services\AbsensiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Sisi panitia (wajib login): menampilkan QR absensi dan daftar hadir.
 * Rapat yang bisa dibuka mengikuti Rapat::terlihat() (User = rapat miliknya, Super Admin = semua).
 */
class AbsensiController extends Controller
{
    /** Daftar rapat pada satu tanggal (bawaan: hari ini) */
    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $tanggal = $this->tanggal($request->query('tanggal'));

        $rapat = Rapat::terlihat()
            ->whereDate('rapat.tanggal', $tanggal)
            ->whereRaw("LOWER(rapat.status) NOT LIKE '%batal%'")
            ->whereRaw("LOWER(rapat.status) <> 'draft'")
            ->orderBy('rapat.waktu')
            ->get();

        foreach ($rapat as $r) {
            $s = AbsensiService::ringkasan($r);
            $r->jml_hadir    = $s['jumlah_hadir'];
            $r->jml_undangan = $s['jumlah_undangan'];
        }

        return view('absensi.index', compact('rapat', 'tanggal'));
    }

    /** Halaman Daftar Hadir: QR + peserta yang sudah / belum hadir */
    public function hadir(int $id)
    {
        Carbon::setLocale('id');

        $rapat   = $this->cari($id);
        $selesai = $rapat->getAttributes()['waktu_selesai'] ?? null;
        $jam     = substr((string) $rapat->waktu, 0, 5) . ($selesai ? ' – ' . substr((string) $selesai, 0, 5) : '');

        return view('absensi.hadir', [
            'rapat'       => $rapat,
            'jam'         => $jam,
            'urlQr'       => AbsensiService::urlQr($rapat->id_rapat),
            'berganti'    => (bool) config('absen.qr_berganti', true),
            'urlScan'     => AbsensiService::urlScan(),
            'urlData'     => route('hadir.data', $rapat->id_rapat),
            'awal'        => AbsensiService::ringkasan($rapat),
            'alasanTutup' => AbsensiService::alasanTutup($rapat),
        ]);
    }

    /** Data terbaru (JSON) untuk pembaruan otomatis di halaman Daftar Hadir */
    public function data(int $id)
    {
        $rapat = $this->cari($id);

        // 'qr' = alamat QR terbaru (berganti tiap menit), dipakai halaman untuk menyegarkan QR
        return response()->json(AbsensiService::ringkasan($rapat) + ['qr' => AbsensiService::urlQr($rapat->id_rapat)]);
    }

    // ------------------------------------------------------------------

    /** Rapat milik user login (Super Admin: semua); selain itu 404 */
    private function cari(int $id): Rapat
    {
        return Rapat::terlihat()->where('rapat.id_rapat', $id)->firstOrFail();
    }

    private function tanggal($nilai): string
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', (string) $nilai)->toDateString();
        } catch (\Throwable $e) {
            return today()->toDateString();
        }
    }
}