<?php

// Simpan sebagai: app/Jobs/KirimUndanganWa.php (ganti SELURUH isi file lama)

namespace App\Jobs;

use App\Models\RapatUndangan;
use App\Services\AbsensiService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class KirimUndanganWa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * SENGAJA hanya 1 kali: job tidak dicoba ulang otomatis.
     * Kalau dicoba ulang setelah provider sebenarnya sudah mengirim, penerima dapat pesan ganda.
     * Yang gagal bisa dikirim ulang manual lewat tombol "Kirim Ulang".
     */
    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $idUndangan)
    {
    }

    public function handle(WhatsAppService $wa): void
    {
        $u = RapatUndangan::with('rapat')->find($this->idUndangan);

        // Sudah terkirim / datanya sudah dihapus -> jangan kirim lagi
        if (!$u || $u->status === RapatUndangan::TERKIRIM) {
            return;
        }

        if (!$u->rapat) {
            $this->tandaiGagal('Data rapat tidak ditemukan.');
            return;
        }

        RapatUndangan::whereKey($u->getKey())->increment('percobaan');

        try {
            $hasil = $wa->kirim($u->nomor_wa, $this->susunPesan($u));
        } catch (Throwable $e) {
            // Timeout/jaringan: hasil di sisi provider tidak pasti -> jangan kirim ulang otomatis
            $this->tandaiGagal('Tidak ada respon pasti dari provider (' . $e->getMessage() . '). Cek HP penerima sebelum kirim ulang.');
            return;
        }

        if (!$hasil['ok']) {
            $this->tandaiGagal($hasil['pesan']);
            return;
        }

        $this->tandaiTerkirim();
    }

    /** Jaga-jaga bila job mati mendadak */
    public function failed(Throwable $e): void
    {
        $this->tandaiGagal(mb_substr($e->getMessage(), 0, 250), false);
    }

    private function tandaiTerkirim(): void
    {
        $q = RapatUndangan::whereKey($this->idUndangan);

        try {
            $q->update(['status' => RapatUndangan::TERKIRIM, 'error' => null, 'terkirim_pada' => now()]);
        } catch (Throwable $e) {
            Log::error('WA terkirim tetapi gagal mencatat waktu: ' . $e->getMessage());
            try {
                // Cadangan: catat status saja
                RapatUndangan::whereKey($this->idUndangan)->update(['status' => RapatUndangan::TERKIRIM]);
            } catch (Throwable $e2) {
                Log::critical('WA terkirim tetapi status gagal dicatat (id ' . $this->idUndangan . '): ' . $e2->getMessage());
            }
        }
    }

    private function tandaiGagal(string $alasan, bool $jangan_timpa_terkirim = true): void
    {
        $q = RapatUndangan::whereKey($this->idUndangan);
        if ($jangan_timpa_terkirim) {
            $q->where('status', '!=', RapatUndangan::TERKIRIM);
        }

        try {
            $q->update(['status' => RapatUndangan::GAGAL, 'error' => mb_substr($alasan, 0, 250)]);
        } catch (Throwable $e) {
            Log::error('Gagal mencatat status gagal WA (id ' . $this->idUndangan . '): ' . $e->getMessage());
        }
    }

    private function susunPesan(RapatUndangan $u): string
    {
        $r = $u->rapat;

        $tanggal = Carbon::parse($r->tanggal)->locale('id')->translatedFormat('l, d F Y');
        $mulai   = substr((string) $r->waktu, 0, 5);
        $selesai = !empty($r->waktu_selesai) ? substr((string) $r->waktu_selesai, 0, 5) : null;
        $jam     = $selesai ? "{$mulai} - {$selesai} WIB" : "{$mulai} WIB";

        $baris = [
            "*UNDANGAN RAPAT*",
            "",
            "Yth. {$u->nama},",
            "",
            "Anda diundang untuk menghadiri rapat:",
            "*{$r->nama_rapat}*",
            "",
            "Hari/Tanggal : {$tanggal}",
            "Waktu        : {$jam}",
            "Tempat       : {$r->tempat_rapat}",
        ];

        if (!empty($r->link_undangan_rapat)) {
            $baris[] = "Link Rapat   : {$r->link_undangan_rapat}";
        }
        if (!empty($r->catatan_rapat)) {
            $baris[] = "Agenda       : {$r->catatan_rapat}";
        }

        // Link absensi SIUPIN (hanya bisa dipakai saat rapat berlangsung).
        // Bila link gagal dibuat, undangan tetap dikirim tanpa link.
        try {
            $linkAbsen = AbsensiService::url((int) $r->getKey());
        } catch (Throwable $e) {
            Log::warning('Link absensi gagal dibuat untuk undangan (rapat ' . $r->getKey() . '): ' . $e->getMessage());
            $linkAbsen = null;
        }

        if ($linkAbsen) {
            $baris[] = "";
            $baris[] = "*Absensi kehadiran (SIUPIN):*";
            $baris[] = $linkAbsen;
            $baris[] = "_Link absensi hanya aktif saat rapat berlangsung. Gunakan nomor WhatsApp ini untuk absen._";
        }

        $baris[] = "";
        $baris[] = "Mohon kehadirannya tepat waktu. Terima kasih.";
        $baris[] = "_" . ($r->pengundang ?: 'SDM') . "_";

        return implode("\n", $baris);
    }
}