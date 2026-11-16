<?php

namespace App\Services;

use App\Models\Rapat;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WaService
{
    /** @return array{ok:bool,id:?string,error:?string} */
    public function kirim(string $nomor, string $pesan): array
    {
        try {
            $res = Http::withHeaders(['Authorization' => (string) config('wa.token')])
                ->asForm()->timeout(20)
                ->post(config('wa.url'), [
                    'target'  => $this->normalisasi($nomor),
                    'message' => $pesan,
                ]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'id' => null, 'error' => 'Tidak dapat terhubung ke server WA.'];
        }

        $json = $res->json() ?? [];

        if ($res->successful() && ($json['status'] ?? false) === true) {
            $id = $json['id'] ?? null;
            return ['ok' => true, 'id' => is_array($id) ? ($id[0] ?? null) : $id, 'error' => null];
        }

        $alasan = $json['reason'] ?? $json['message'] ?? ('HTTP ' . $res->status());
        return ['ok' => false, 'id' => null, 'error' => mb_substr((string) $alasan, 0, 255)];
    }

    public function normalisasi(string $nomor): string
    {
        $n = preg_replace('/\D+/', '', $nomor);
        if (str_starts_with($n, '0'))  return '62' . substr($n, 1);
        if (str_starts_with($n, '8'))  return '62' . $n;
        return $n;
    }

    public function pesanUndangan(Rapat $r, string $nama): string
    {
        $tgl   = Carbon::parse($r->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y');
        $mulai = substr((string) $r->waktu, 0, 5);
        $akhir = $r->waktu_selesai ? ' - ' . substr((string) $r->waktu_selesai, 0, 5) : '';

        $baris = [
            "Yth. {$nama},",
            '',
            'Anda diundang pada rapat berikut:',
            "*{$r->nama_rapat}*",
            '',
            "Hari/Tanggal : {$tgl}",
            "Waktu        : {$mulai}{$akhir} WIB",
            "Tempat       : {$r->tempat_rapat}",
        ];
        if ($r->catatan_rapat) $baris[] = "Agenda       : {$r->catatan_rapat}";
        if ($r->link_undangan_rapat) $baris[] = "Link Rapat   : {$r->link_undangan_rapat}";
        if ($r->lampiran_path) $baris[] = 'Lampiran     : ' . url(Storage::disk('public')->url($r->lampiran_path));
        $baris[] = '';
        $baris[] = 'Mohon kehadirannya. Terima kasih.';
        $baris[] = "_{$r->pengundang}_";

        return implode("\n", $baris);
    }
}