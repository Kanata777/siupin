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

    /** Form absen untuk satu rapat (dibuka dari QR) */
    public function form(int $id, string $kode)
    {
        Carbon::setLocale('id');

        $rapat   = $this->cari($id, $kode);
        $selesai = $rapat->getAttributes()['waktu_selesai'] ?? null;

        return view('absen.form', [
            'rapat' => $rapat,
            'jam'   => substr((string) $rapat->waktu, 0, 5) . ($selesai ? ' – ' . substr((string) $selesai, 0, 5) : ''),
            'tutup' => AbsensiService::alasanTutup($rapat),
            'hasil' => session('hasil'),
        ]);
    }

    /** Catat kehadiran */
    public function simpan(Request $request, int $id, string $kode)
    {
        $rapat = $this->cari($id, $kode);

        if (AbsensiService::alasanTutup($rapat)) {
            return redirect()->route('absen.form', ['id' => $id, 'kode' => $kode]);
        }

        $request->validate(
            ['nomor_wa' => 'required|string|max:20'],
            ['nomor_wa.required' => 'Nomor WhatsApp wajib diisi.', 'nomor_wa.max' => 'Nomor WhatsApp terlalu panjang.']
        );

        $hasil = AbsensiService::catat($rapat, (string) $request->input('nomor_wa'));

        return redirect()->route('absen.form', ['id' => $id, 'kode' => $kode])->with('hasil', $hasil);
    }

    // ------------------------------------------------------------------

    private function cari(int $id, string $kode): Rapat
    {
        abort_unless(AbsensiService::kodeValid($id, $kode), 404);

        return Rapat::findOrFail($id);
    }
}