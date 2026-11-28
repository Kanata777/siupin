<?php

// Simpan sebagai: app/Http/Controllers/AbsenPublikController.php (file baru)

namespace App\Http\Controllers;

use App\Models\Rapat;
use App\Services\AbsensiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Sisi peserta (TANPA login): scan QR rapat -> isi nomor WA -> kehadiran tercatat.
 * Link absensi hanya valid bila {kode} cocok dengan HMAC id rapat (lihat AbsensiService::kode).
 */
class AbsenPublikController extends Controller
{
    /** Halaman scanner kamera: membaca QR absensi lalu membuka link-nya */
    public function scan()
    {
        return view('absen.scan', [
            // dua domain: link absen ada di akar domain absensi; satu domain: di bawah /absen
            'awalan'    => AbsensiService::terpisah() ? '' : 'absen',
            'asalAbsen' => AbsensiService::asalAbsen(),
        ]);
    }

    /**
     * Form absen untuk satu rapat.
     *  - Dibuka dari QR di lokasi (membawa ?t=token yang sah): browser diberi izin, lalu form tampil.
     *  - Dibuka dari link biasa (mis. dari WhatsApp): form TIDAK tampil, peserta diminta scan QR dulu.
     */
    public function form(Request $request, int $id, string $kode)
    {
        Carbon::setLocale('id');

        $rapat   = $this->cari($id, $kode);
        $tutup   = AbsensiService::alasanTutup($rapat);
        $selesai = $rapat->getAttributes()['waktu_selesai'] ?? null;
        $kedaluwarsa = false;

        if (! $tutup && $request->filled('t')) {
            if (AbsensiService::tokenValid($id, (string) $request->query('t'))) {
                AbsensiService::beriIzin($id);

                // bersihkan token dari alamat agar tidak ikut tersalin/terbagikan
                return redirect()->route('absen.form', ['id' => $id, 'kode' => $kode]);
            }
            $kedaluwarsa = true;
        }

        return view('absen.form', [
            'rapat'       => $rapat,
            'jam'         => substr((string) $rapat->waktu, 0, 5) . ($selesai ? ' – ' . substr((string) $selesai, 0, 5) : ''),
            'tutup'       => $tutup,
            'perluScan'   => ! $tutup && ! AbsensiService::punyaIzin($id),
            'qrKedaluwarsa' => $kedaluwarsa,
            'hasil'       => session('hasil'),
        ]);
    }

    /** Catat kehadiran (hanya untuk browser yang sudah men-scan QR yang sah) */
    public function simpan(Request $request, int $id, string $kode)
    {
        $rapat = $this->cari($id, $kode);

        if (AbsensiService::alasanTutup($rapat) || ! AbsensiService::punyaIzin($id)) {
            return redirect()->route('absen.form', ['id' => $id, 'kode' => $kode]);
        }

        $request->validate(
            ['nomor_wa' => 'required|string|max:20'],
            ['nomor_wa.required' => 'Nomor WhatsApp wajib diisi.', 'nomor_wa.max' => 'Nomor WhatsApp terlalu panjang.']
        );

        $hasil = AbsensiService::catat($rapat, (string) $request->input('nomor_wa'));

        // satu kali scan = satu kali absen; untuk orang berikutnya QR harus di-scan lagi
        if (in_array($hasil['status'] ?? '', ['ok', 'sudah'], true)) {
            AbsensiService::cabutIzin($id);
        }

        return redirect()->route('absen.form', ['id' => $id, 'kode' => $kode])->with('hasil', $hasil);
    }

    // ------------------------------------------------------------------

    private function cari(int $id, string $kode): Rapat
    {
        abort_unless(AbsensiService::kodeValid($id, $kode), 404);

        return Rapat::findOrFail($id);
    }
}