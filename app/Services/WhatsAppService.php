<?php

// Simpan sebagai: app/Services/WhatsAppService.php  (versi Watzap.id)

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    /**
     * Kirim satu pesan teks lewat Watzap.id.
     *
     * @return array{ok: bool, pesan: string, respon: array}
     * @throws RuntimeException bila provider/jaringan bermasalah (agar job di-retry)
     */
    public function kirim(string $nomor, string $pesan): array
    {
        $apiKey    = (string) config('wa.token');
        $numberKey = (string) config('wa.number_key');

        if ($apiKey === '' || $numberKey === '') {
            return ['ok' => false, 'pesan' => 'WA_TOKEN (API Key) atau WA_NUMBER_KEY belum diisi di .env', 'respon' => []];
        }

        $target = self::normalisasiNomor($nomor);
        if ($target === null) {
            return ['ok' => false, 'pesan' => 'Format nomor tidak valid: ' . $nomor, 'respon' => []];
        }

        $base = preg_replace('#/v1/?$#', '', rtrim((string) config('wa.url'), '/'));

        $res = Http::timeout((int) config('wa.timeout', 20))
            ->acceptJson()
            ->asJson()
            ->post($base . '/v1/send_message', [
                'api_key'    => $apiKey,
                'number_key' => $numberKey,
                'phone_no'   => $target,
                'message'    => $pesan,
            ]);

        // Gangguan di sisi provider -> lempar agar dicoba ulang
        if ($res->serverError()) {
            throw new RuntimeException('Provider WA error HTTP ' . $res->status());
        }

        $json = $res->json() ?: [];
        $ok   = $res->successful() && ((string) ($json['status'] ?? '') === '200');

        return [
            'ok'     => $ok,
            'pesan'  => $ok ? 'Terkirim' : (string) ($json['message'] ?? ('HTTP ' . $res->status())),
            'respon' => $json,
        ];
    }

    /** 0812... / +62812... / 62812... / 812... -> 62812... ; null bila tidak masuk akal */
    public static function normalisasiNomor(string $nomor): ?string
    {
        $d = preg_replace('/\D+/', '', $nomor) ?? '';

        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0')) {
            $d = '62' . substr($d, 1);
        } elseif (str_starts_with($d, '8')) {
            $d = '62' . $d;
        }

        return (str_starts_with($d, '62') && strlen($d) >= 10 && strlen($d) <= 15) ? $d : null;
    }
}