<?php

namespace App\Http\Controllers;

use App\Jobs\KirimUndanganWa;
use App\Models\Pegawai;
use App\Models\Rapat;
use App\Models\RapatUndangan;
use Illuminate\Http\Request;

/**
 * Halaman monitoring status pengiriman undangan WA untuk SELURUH rapat.
 * Endpoint per-rapat di RapatController (statusWa / kirimUlang) tetap dipakai halaman Buat Rapat.
 */
class StatusWaController extends Controller
{
    private const PER_HALAMAN = 25;
    private const MAKS_KIRIM_ULANG = 1000;

    /** GET /status-wa  ?status=bermasalah|gagal|pending|terkirim|semua &rapat=ID &q= */
    public function index(Request $request)
    {
        $tu = (new RapatUndangan)->getTable();
        $rapatModel = new Rapat;
        $tr = $rapatModel->getTable();
        $pk = $rapatModel->getKeyName();

        $filter = (string) $request->query('status', 'bermasalah');
        $idRapat = (int) $request->query('rapat');
        $kata = trim((string) $request->query('q'));

        // Ringkasan mengikuti pilihan rapat (bila ada), tidak mengikuti status/pencarian
        $hitung = RapatUndangan::terlihat()
            ->when($idRapat > 0, fn($q) => $q->where('id_rapat', $idRapat))
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $gagal = (int) ($hitung[RapatUndangan::GAGAL] ?? 0);
        $pending = (int) ($hitung[RapatUndangan::PENDING] ?? 0);
        $terkirim = (int) ($hitung[RapatUndangan::TERKIRIM] ?? 0);
        $total = $gagal + $pending + $terkirim;

        $ringkasan = [
            'total' => $total,
            'terkirim' => $terkirim,
            'pending' => $pending,
            'gagal' => $gagal,
            'bermasalah' => $gagal + $pending,
            'persen' => $total > 0 ? round($terkirim / $total * 100) : 0,
        ];

        $q = $this->dasar($kata, $idRapat);

        match ($filter) {
            'gagal' => $q->where("$tu.status", RapatUndangan::GAGAL),
            'pending' => $q->where("$tu.status", RapatUndangan::PENDING),
            'terkirim' => $q->where("$tu.status", RapatUndangan::TERKIRIM),
            'semua' => null,
            default => $q->whereIn("$tu.status", [RapatUndangan::GAGAL, RapatUndangan::PENDING]),
        };

        $daftar = $q
            ->orderByDesc("$tr.tanggal")
            ->orderByDesc("$tu.id")
            ->paginate(self::PER_HALAMAN, ["$tu.*", "$tr.nama_rapat", "$tr.tanggal as tgl_rapat"])
            ->withQueryString();

        // Pilihan rapat untuk filter (hanya rapat yang punya data undangan)
        $daftarRapat = Rapat::terlihat()->whereIn($pk, RapatUndangan::query()->select('id_rapat')->distinct())
            ->orderByDesc('tanggal')->limit(200)
            ->get([$pk, 'nama_rapat', 'tanggal']);

        return view('status-wa.index', [
            'daftar' => $daftar,
            'ringkasan' => $ringkasan,
            'daftarRapat' => $daftarRapat,
            'filter' => $filter,
            'idRapat' => $idRapat,
            'kata' => $kata,
        ]);
    }

    /**
     * POST kirim ulang pesan yang GAGAL.
     *  - ids[]   : peserta terpilih (hanya yang berstatus gagal yang diproses)
     *  - semua=1 : semua yang gagal, mengikuti ?q dan ?rapat bila dikirim
     */
    public function kirimUlang(Request $request)
    {
        $tu = (new RapatUndangan)->getTable();

        if ($request->boolean('semua')) {
            $ids = $this->dasar((string) $request->input('q'), (int) $request->input('rapat'))
                ->where("$tu.status", RapatUndangan::GAGAL)
                ->orderBy("$tu.id")
                ->limit(self::MAKS_KIRIM_ULANG)
                ->pluck("$tu.id");
        } elseif (is_array($request->input('ids'))) {
            $ids = RapatUndangan::terlihat()->whereIn('id', array_map('intval', array_slice($request->input('ids'), 0, self::MAKS_KIRIM_ULANG)))
                ->where('status', RapatUndangan::GAGAL)
                ->orderBy('id')
                ->pluck('id');
        } else {
            return response()->json(['message' => 'Permintaan tidak valid.'], 422);
        }

        if ($ids->isEmpty()) {
            return response()->json(['message' => 'Tidak ada pesan gagal untuk dikirim ulang.'], 422);
        }

        $daftar = RapatUndangan::whereIn('id', $ids)->get();

        // Ambil nomor terbaru dari data pegawai sebelum dikirim ulang
        $diperbarui = $daftar->filter(fn($u) => $this->segarkanNomor($u))->count();

        RapatUndangan::whereIn('id', $ids)->update(['status' => RapatUndangan::PENDING, 'error' => null]);
        $this->antrekan($ids);

        $pesan = $ids->count() . ' pesan dijadwalkan kirim ulang.';
        if ($diperbarui > 0) {
            $pesan .= ' ' . $diperbarui . ' nomor diperbarui dari data pegawai.';
        }

        return response()->json(['pesan' => $pesan]);
    }

    // ------------------------------------------------------------------

    /** Query dasar: undangan + rapatnya, dengan pencarian dan filter rapat opsional */
    private function dasar(string $kata, int $idRapat = 0)
    {
        $tu = (new RapatUndangan)->getTable();
        $rapat = new Rapat;
        $tr = $rapat->getTable();
        $pk = $rapat->getKeyName();

        $q = RapatUndangan::query()->join($tr, "$tr.$pk", '=', "$tu.id_rapat");

        // Pemilik undangan = pemilik rapatnya. Super Admin melihat semua, User hanya rapat miliknya.
        if (!Rapat::bolehSemua()) {
            $q->where("$tr.user_id", (int) auth()->id());
        }

        if ($idRapat > 0) {
            $q->where("$tu.id_rapat", $idRapat);
        }

        $kata = trim($kata);
        if ($kata !== '') {
            $like = '%' . $kata . '%';
            $q->where(function ($w) use ($like, $tu, $tr) {
                $w->where("$tu.nama", 'like', $like)
                    ->orWhere("$tu.nomor_wa", 'like', $like)
                    ->orWhere("$tu.error", 'like', $like)
                    ->orWhere("$tr.nama_rapat", 'like', $like);
            });
        }

        return $q;
    }

    /** Sama dengan RapatController::antrekan (jeda antar pesan dari config/wa.php) */
    private function antrekan($ids): void
    {
        $jeda = max(0, (int) config('wa.jeda', 3));

        foreach ($ids->values() as $i => $idUndangan) {
            KirimUndanganWa::dispatch((int) $idUndangan)->delay(now()->addSeconds($i * $jeda));
        }
    }

    /** Sama dengan RapatController::segarkanNomor */
    private function segarkanNomor(RapatUndangan $u): bool
    {
        $lama = trim((string) $u->nomor_wa);

        $nomorPegawai = Pegawai::where('nama_lengkap', trim((string) $u->nama))
            ->whereNotNull('nomor_wa')->where('nomor_wa', '!=', '')
            ->pluck('nomor_wa')->map(fn($n) => trim($n))->unique()->values();

        if ($nomorPegawai->isEmpty() || $nomorPegawai->contains($lama) || $nomorPegawai->count() > 1) {
            return false;
        }

        $baru = $nomorPegawai->first();

        $dipakai = RapatUndangan::where('id_rapat', $u->id_rapat)
            ->where('nomor_wa', $baru)->where('id', '!=', $u->id)->exists();
        if ($dipakai) {
            return false;
        }

        $u->update(['nomor_wa' => $baru]);
        return true;
    }
}