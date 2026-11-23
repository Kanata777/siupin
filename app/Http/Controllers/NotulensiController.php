<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimpanNotulensiRequest;
use App\Models\BerkasNotulensi;
use App\Models\Notulensi;
use App\Models\Rapat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NotulensiController extends Controller
{
    /** Status rapat yang tidak berlangsung, jadi tidak butuh notulensi. Sesuaikan dengan isi kolom rapat.status. */
    private const TIDAK_BERLANGSUNG = ['batal', 'dibatalkan', 'tertunda'];

    /** Daftar rapat yang sudah lewat beserta status notulensinya. */
    public function index(Request $request)
    {
        $tab = in_array($request->query('s'), ['belum', 'draft', 'final'], true) ? $request->query('s') : 'semua';
        $q   = trim((string) $request->query('q'));

        $dasar = Rapat::query()
            ->terlihat()
            ->leftJoin('notulensi', 'notulensi.id_rapat', '=', 'rapat.id_rapat')
            ->whereDate('rapat.tanggal', '<=', today())
            ->whereNotIn('rapat.status', self::TIDAK_BERLANGSUNG)
            ->when($q !== '', fn ($x) => $x->where('rapat.nama_rapat', 'like', '%' . $q . '%'));

        $hitung = [
            'semua' => (clone $dasar)->count(),
            'belum' => (clone $dasar)->whereNull('notulensi.id_notulensi')->count(),
            'draft' => (clone $dasar)->where('notulensi.status', 'draft')->count(),
            'final' => (clone $dasar)->where('notulensi.status', 'final')->count(),
        ];

        $rapat = (clone $dasar)
            ->when($tab === 'belum', fn ($x) => $x->whereNull('notulensi.id_notulensi'))
            ->when(in_array($tab, ['draft', 'final'], true), fn ($x) => $x->where('notulensi.status', $tab))
            ->select('rapat.*', 'notulensi.status as status_notulensi')
            ->orderByDesc('rapat.tanggal')
            ->orderByDesc('rapat.waktu')
            ->paginate(15)
            ->withQueryString();

        return view('notulensi.index', compact('rapat', 'hitung', 'tab', 'q'));
    }

    /** Form notulensi satu rapat. */
    public function edit(int $id)
    {
        return view('notulensi.edit', $this->muat($id));
    }

    /** Simpan draft atau finalisasi (tombol memakai name="aksi"). */
    public function update(SimpanNotulensiRequest $request, int $id)
    {
        $rapat     = $this->cariRapat($id);
        $notulensi = Notulensi::firstOrNew(['id_rapat' => $rapat->id_rapat]);

        if ($notulensi->exists && $notulensi->sudahFinal()) {
            return back()->with('galat', 'Notulensi sudah final. Klik "Buka untuk Ubah" sebelum mengedit.');
        }

        $final = $request->input('aksi') === 'final';

        DB::transaction(function () use ($request, $notulensi, $final) {
            $notulensi->fill($request->only('agenda', 'pembahasan', 'keputusan'));
            $notulensi->status          = $final ? 'final' : 'draft';
            $notulensi->difinalkan_pada = $final ? now() : null;
            $notulensi->save();

            // Rencana tindak lanjut: ganti seluruhnya, baris tanpa uraian dilewati.
            $notulensi->tindakLanjut()->delete();
            foreach (array_values($request->input('tl', [])) as $i => $baris) {
                if (trim($baris['uraian'] ?? '') === '') {
                    continue;
                }
                $notulensi->tindakLanjut()->create([
                    'uraian'         => trim($baris['uraian']),
                    'pic'            => $baris['pic'] ?? null,
                    'target_tanggal' => $baris['target_tanggal'] ?? null,
                    'status'         => $baris['status'] ?? 'belum',
                    'urutan'         => $i,
                ]);
            }

            // Hapus berkas yang dicentang (hanya milik notulensi ini).
            $hapus = BerkasNotulensi::where('id_notulensi', $notulensi->id_notulensi)
                ->whereIn('id_berkas', (array) $request->input('hapus_berkas', []))
                ->get();
            foreach ($hapus as $b) {
                Storage::disk('public')->delete($b->path);
                $b->delete();
            }

            // Unggah berkas baru.
            foreach (['foto', 'lampiran'] as $jenis) {
                foreach ($request->file($jenis, []) as $file) {
                    $notulensi->berkas()->create([
                        'jenis'     => $jenis,
                        'nama_asli' => $file->getClientOriginalName(),
                        'path'      => $file->store("notulensi/{$notulensi->id_rapat}/{$jenis}", 'public'),
                        'ukuran'    => $file->getSize(),
                        'mime'      => $file->getClientMimeType(),
                    ]);
                }
            }
        });

        return redirect()
            ->route('notulensi.edit', $rapat->id_rapat)
            ->with('sukses', $final ? 'Notulensi difinalisasi.' : 'Draft notulensi tersimpan.');
    }

    /** Buka kembali notulensi final untuk diubah. */
    public function buka(int $id)
    {
        $rapat = $this->cariRapat($id);
        Notulensi::where('id_rapat', $rapat->id_rapat)->update(['status' => 'draft', 'difinalkan_pada' => null]);

        return redirect()->route('notulensi.edit', $rapat->id_rapat)->with('sukses', 'Notulensi dapat diubah kembali.');
    }

    public function unduh(int $idBerkas)
    {
        $b = BerkasNotulensi::findOrFail($idBerkas);

        // Berkas hanya boleh diunduh bila rapat induknya boleh diakses user login
        $idRapat = Notulensi::where('id_notulensi', $b->id_notulensi)->value('id_rapat');
        abort_unless($idRapat && Rapat::terlihat()->where('rapat.id_rapat', $idRapat)->exists(), 404);

        abort_unless(Storage::disk('public')->exists($b->path), 404);

        return Storage::disk('public')->download($b->path, $b->nama_asli);
    }

    /** Halaman cetak: buka di tab baru, dialog cetak muncul otomatis (pilih "Simpan sebagai PDF"). */
    public function cetak(int $id)
    {
        $data = $this->muat($id);
        if (! $data['notulensi']->exists) {
            return redirect()->route('notulensi.edit', $id)->with('galat', 'Simpan notulensi terlebih dahulu sebelum diekspor.');
        }

        return view('notulensi.cetak', $data);
    }

    private function cariRapat(int $id): Rapat
    {
        // terlihat(): rapat milik user lain = 404
        return Rapat::terlihat()->where('rapat.id_rapat', $id)->firstOrFail();
    }

    private function muat(int $id): array
    {
        $rapat     = $this->cariRapat($id);
        $notulensi = Notulensi::with(['tindakLanjut', 'berkas'])->firstOrNew(['id_rapat' => $rapat->id_rapat]);

        // Presensi dari form absensi (tabel_absensi tidak punya id_rapat, jadi dicocokkan lewat nama + tanggal).
        $hadir = DB::table('tabel_absensi')
            ->where('nama_rapat', $rapat->nama_rapat)
            ->whereDate('waktu_absensi', $rapat->tanggal)
            ->orderBy('waktu_absensi')
            ->get();

        return compact('rapat', 'notulensi', 'hadir');
    }
}
