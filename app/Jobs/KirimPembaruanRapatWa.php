<?php

// Simpan sebagai: app/Jobs/KirimPembaruanRapatWa.php

namespace App\Jobs;

use App\Models\RapatUndangan;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim pemberitahuan "jadwal/tempat rapat berubah" ke peserta lama.
 * Anggota baru memakai job undangan biasa: KirimUndanganWa.
 */
class KirimPembaruanRapatWa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sama dengan KirimUndanganWa: tidak dicoba ulang otomatis agar tidak ada pesan ganda */
    public int $tries = 1;
    public int $timeout = 60;

    /**
     * @param int   $idUndangan id baris tabel rapat_undangan
     * @param array $lama       ['tanggal'=>'Y-m-d','waktu'=>'H:i','selesai'=>'H:i'|null,'tempat'=>'...'] sebelum diubah
     */
    public function __construct(public int $idUndangan, public array $lama = [])
    {
    }

    public function handle(WhatsAppService $wa): void
    {
        $u = RapatUndangan::with('rapat')->find($this->idUndangan);

        // Hanya yang berstatus pending (sudah dihapus / sudah terkirim -> lewati)
        if (!$u || $u->status !== RapatUndangan::PENDING) {
            return;
        }

        if (!$u->rapat) {
            $this->tandaiGagal('Data rapat tidak ditemukan.');
            return;
        }
        if (str_contains(strtolower((string) $u->rapat->status), 'batal')) {
            $this->tandaiGagal('Rapat sudah dibatalkan.');
            return;
        }

        RapatUndangan::whereKey($u->getKey())->increment('percobaan');

        try {
            $hasil = $wa->kirim((string) $u->nomor_wa, $this->susunPesan($u));
        } catch (Throwable $e) {
            $this->tandaiGagal('Tidak ada respon pasti dari provider (' . $e->getMessage() . '). Cek HP penerima sebelum kirim ulang.');
            return;
        }

        if (!$hasil['ok']) {
            $this->tandaiGagal($hasil['pesan']);
            return;
        }

        $this->tandaiTerkirim();
    }

    public function failed(Throwable $e): void
    {
        $this->tandaiGagal(mb_substr($e->getMessage(), 0, 250), false);
    }

    private function tandaiTerkirim(): void
    {
        try {
            RapatUndangan::whereKey($this->idUndangan)
                ->update(['status' => RapatUndangan::TERKIRIM, 'error' => null, 'terkirim_pada' => now()]);
        } catch (Throwable $e) {
            Log::error('WA pembaruan terkirim tetapi gagal mencatat waktu: ' . $e->getMessage());
            try {
                RapatUndangan::whereKey($this->idUndangan)->update(['status' => RapatUndangan::TERKIRIM]);
            } catch (Throwable $e2) {
                Log::critical('WA pembaruan terkirim tetapi status gagal dicatat (id ' . $this->idUndangan . '): ' . $e2->getMessage());
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
            Log::error('Gagal mencatat status gagal WA pembaruan (id ' . $this->idUndangan . '): ' . $e->getMessage());
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
            '*PEMBARUAN JADWAL RAPAT*',
            '',
            'Yth. ' . ($u->nama ?: 'Bapak/Ibu') . ',',
            '',
            'Ada perubahan pada rapat berikut:',
            "*{$r->nama_rapat}*",
            '',
            'Jadwal terbaru:',
            "Hari/Tanggal : {$tanggal}",
            "Waktu        : {$jam}",
            "Tempat       : {$r->tempat_rapat}",
        ];

        if (!empty($r->link_undangan_rapat)) {
            $baris[] = "Link Rapat   : {$r->link_undangan_rapat}";
        }

        // Tampilkan apa yang berubah (bila datanya ada)
        $ubah = [];
        $l = $this->lama;
        if (!empty($l['tanggal']) && !empty($l['waktu'])) {
            $lamaSelesai = $l['selesai'] ?? null;
            if (Carbon::parse($l['tanggal'])->toDateString() !== Carbon::parse($r->tanggal)->toDateString()
                || $l['waktu'] !== $mulai
                || $lamaSelesai !== $selesai) {
                $ubah[] = 'Jadwal sebelumnya: ' . Carbon::parse($l['tanggal'])->locale('id')->translatedFormat('d F Y')
                    . ', ' . $l['waktu'] . ($lamaSelesai ? ' - ' . $lamaSelesai : '') . ' WIB';
            }
        }
        if (!empty($l['tempat']) && $l['tempat'] !== $r->tempat_rapat) {
            $ubah[] = 'Tempat sebelumnya: ' . $l['tempat'];
        }
        if ($ubah) {
            $baris[] = '';
            foreach ($ubah as $b) {
                $baris[] = '_' . $b . '_';
            }
        }

        $baris[] = '';
        $baris[] = 'Mohon menyesuaikan jadwal Anda. Terima kasih.';
        $baris[] = '_' . ($r->pengundang ?: 'SDM') . '_';

        return implode("\n", $baris);
    }
}