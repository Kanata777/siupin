<?php

namespace App\Jobs;

use App\Models\Rapat;
use App\Models\RapatUndangan;
use App\Services\WaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class KirimUndanganWa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // jangan diulang otomatis agar tidak terkirim ganda

    public function __construct(public int $idUndangan) {}

    public function handle(WaService $wa): void
    {
        $u = RapatUndangan::find($this->idUndangan);
        if (!$u || $u->status !== RapatUndangan::PENDING) return;

        $rapat = Rapat::find($u->id_rapat);
        if (!$rapat) {
            $u->update(['status' => RapatUndangan::GAGAL, 'error' => 'Data rapat tidak ditemukan.']);
            return;
        }

        $hasil = $wa->kirim($u->nomor_wa, $wa->pesanUndangan($rapat, $u->nama));

        $u->update($hasil['ok']
            ? ['status' => RapatUndangan::TERKIRIM, 'id_pesan_wa' => $hasil['id'], 'error' => null,
               'terkirim_pada' => now(), 'percobaan' => $u->percobaan + 1]
            : ['status' => RapatUndangan::GAGAL, 'error' => $hasil['error'],
               'percobaan' => $u->percobaan + 1]);
    }

    public function failed(\Throwable $e): void
    {
        RapatUndangan::where('id', $this->idUndangan)->update([
            'status' => RapatUndangan::GAGAL,
            'error'  => mb_substr($e->getMessage(), 0, 255),
        ]);
    }
}