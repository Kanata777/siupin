<?php

namespace App\Http\Controllers;

use App\Models\KelompokRapat;
use App\Models\KelompokRapatDetail;
use App\Models\Rapat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelompokRapatController extends Controller
{
    private const PER_HALAMAN = 8;

    // ======================================================================
    //  HALAMAN KELOMPOK RAPAT
    //  Dua halaman setara dengan tab:
    //   - /kelompok                      -> Daftar Kelompok
    //   - /kelompok?hal=anggota          -> Daftar Anggota (semua kelompok)
    //   - /kelompok?hal=anggota&pilih=ID -> Daftar Anggota, disaring ke kelompok ID
    // ======================================================================
    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $kata  = trim((string) $request->query('q'));
        $pilih = (int) $request->query('pilih');
        if ($pilih && !KelompokRapat::find($pilih)) {
            $pilih = 0;
        }

        $hal  = $request->query('hal') === 'anggota' ? 'anggota' : 'daftar';
        $mode = 'mode-' . $hal;

        $dasar = fn () => KelompokRapat::query()
            ->where('nama_kelompok', 'not like', KelompokRapat::PREFIX_OTOMATIS . '%')
            ->when($kata !== '', fn ($q) => $q->where('nama_kelompok', 'like', '%' . addcslashes($kata, '%_\\') . '%'));

        // bila ada ?pilih=ID tanpa ?page, buka halaman tabel yang memuat kelompok tsb
        $halaman = (int) $request->query('page');
        if (!$halaman && $pilih && ($g = KelompokRapat::find($pilih))) {
            $urutan  = $dasar()->where('nama_kelompok', '<', $g->nama_kelompok)->count();
            $halaman = intdiv($urutan, self::PER_HALAMAN) + 1;
        }

        $kelompok = $dasar()
            ->withCount('anggota')
            ->orderBy('nama_kelompok')
            ->paginate(self::PER_HALAMAN, ['*'], 'page', max(1, $halaman))
            ->appends($request->except(['pilih', 'page', 'hal']));

        // seluruh kelompok untuk dropdown filter di halaman Daftar Anggota
        $semuaKelompok = KelompokRapat::query()
            ->where('nama_kelompok', 'not like', KelompokRapat::PREFIX_OTOMATIS . '%')
            ->orderBy('nama_kelompok')
            ->get(['id_kelompok', 'nama_kelompok']);

        $units = DB::table('unit_pegawai')->orderBy('nama_unit')->pluck('nama_unit');
        if ($units->isEmpty()) {
            $units = DB::table('pegawai')->whereNotNull('nama_unit')->distinct()->orderBy('nama_unit')->pluck('nama_unit');
        }

        $konfig = [
            'pilih'           => $pilih ?: null,
            'hal'             => $hal,
            'urlIndex'        => route('kelompok.index'),
            'urlDetail'       => route('kelompok.detail', '__ID__'),
            'urlAnggotaSemua' => route('kelompok.anggota.semua'),
            'urlBuat'         => route('kelompok.buat'),
            'urlUbah'         => route('kelompok.ubah', '__ID__'),
            'urlHapus'        => route('kelompok.hapus', '__ID__'),
            'urlTambah'       => route('kelompok.anggota.tambah', '__ID__'),
            'urlHapusAnggota' => route('kelompok.anggota.hapus', ['id' => '__ID__', 'detail' => '__D__']),
            'urlPegawai'      => route('rapat.pegawai'),
            'punyaKeterangan' => KelompokRapat::punyaKolom('keterangan'),
        ];
        $punyaKeterangan = $konfig['punyaKeterangan'];

        return view('kelompok.index', compact(
            'kelompok', 'pilih', 'kata', 'units', 'konfig', 'punyaKeterangan', 'semuaKelompok', 'hal', 'mode'
        ));
    }

    // ======================================================================
    //  DETAIL KELOMPOK + ANGGOTA (JSON, dipakai dialog detail)
    // ======================================================================
    public function detail(int $id)
    {
        Carbon::setLocale('id');
        $k = KelompokRapat::findOrFail($id);

        $anggota = collect(DB::select("
            SELECT d.id_detail AS id, d.nama_lengkap AS nama, d.nomor_wa,
                   (SELECT p.nama_unit FROM pegawai p
                     WHERE p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci LIMIT 1) AS unit,
                   (SELECT p.jabatan FROM pegawai p
                     WHERE p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci LIMIT 1) AS jabatan
              FROM kelompok_rapat_detail d
             WHERE d.id_kelompok = ?
             ORDER BY d.nama_lengkap
        ", [$id]))->map(fn ($a) => [
            'id'       => $a->id,
            'nama'     => $a->nama,
            'nomor_wa' => $a->nomor_wa,
            'unit'     => trim((string) $a->unit),
            'jabatan'  => trim((string) $a->jabatan),
        ])->values();

        $unit = $anggota->pluck('unit')->filter()->unique()->values();
        $unitRingkas = match (true) {
            $unit->count() === 0 => '-',
            $unit->count() === 1 => $unit[0],
            $unit->count() === 2 => $unit[0] . ' & ' . $unit[1],
            default              => 'Lintas Unit',
        };

        $dibuatPada = $k->attr('dibuat_pada');

        return response()->json([
            'id'            => $k->id_kelompok,
            'nama'          => $k->nama_kelompok,
            'jumlah'        => $anggota->count(),
            'unit'          => $unitRingkas,
            'unit_semua'    => $unit->implode(', '),
            'dibuat_oleh'   => $k->attr('dibuat_oleh'),
            'dibuat_pada'   => $dibuatPada ? Carbon::parse($dibuatPada)->translatedFormat('d F Y') : null,
            'keterangan'    => $k->attr('keterangan'),
            'anggota'       => $anggota,
        ]);
    }

    /** Semua anggota dari semua kelompok (halaman Daftar Anggota) */
    public function anggotaSemua()
    {
        $tK = (new KelompokRapat)->getTable();
        $tD = (new KelompokRapatDetail)->getTable();

        $rows = DB::select("
            SELECT d.id_detail AS id, d.id_kelompok, d.nama_lengkap AS nama, d.nomor_wa,
                   k.nama_kelompok AS kelompok,
                   (SELECT p.nama_unit FROM pegawai p
                     WHERE p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci LIMIT 1) AS unit,
                   (SELECT p.jabatan FROM pegawai p
                     WHERE p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci LIMIT 1) AS jabatan
              FROM {$tD} d
              JOIN {$tK} k ON k.id_kelompok = d.id_kelompok
             WHERE k.nama_kelompok NOT LIKE ?
             ORDER BY d.nama_lengkap, k.nama_kelompok
        ", [KelompokRapat::PREFIX_OTOMATIS . '%']);

        return response()->json(collect($rows)->map(fn ($a) => [
            'id'          => $a->id,
            'id_kelompok' => $a->id_kelompok,
            'nama'        => $a->nama,
            'nomor_wa'    => $a->nomor_wa,
            'kelompok'    => $a->kelompok,
            'unit'        => trim((string) $a->unit),
            'jabatan'     => trim((string) $a->jabatan),
        ])->values());
    }

    /** Anggota satu kelompok (dipakai halaman Buat Rapat) */
    public function anggota(int $id)
    {
        $kelompok = KelompokRapat::findOrFail($id);

        $anggota = KelompokRapatDetail::where('id_kelompok', $id)
            ->orderBy('nama_lengkap')
            ->get(['nomor_wa', 'nama_lengkap'])
            ->map(fn ($d) => ['nomor_wa' => $d->nomor_wa, 'nama' => $d->nama_lengkap])
            ->values();

        return response()->json([
            'nama_kelompok' => $kelompok->nama_kelompok,
            'anggota'       => $anggota,
        ]);
    }

    // ======================================================================
    //  BUAT / UBAH / HAPUS KELOMPOK
    // ======================================================================

    /** Kelompok baru (kosong); anggota ditambah setelahnya */
    public function buat(Request $request)
    {
        [$nama, $ket, $galat] = $this->validasiNama($request, null);
        if ($galat) {
            return response()->json(['message' => $galat], 422);
        }

        $k = KelompokRapat::buat($nama, $ket);

        return response()->json([
            'id'    => $k->id_kelompok,
            'pesan' => 'Kelompok "' . $nama . '" dibuat. Tambahkan anggotanya di tab Daftar Anggota.',
        ]);
    }

    /** Ubah nama / keterangan kelompok */
    public function ubah(Request $request, int $id)
    {
        $k = KelompokRapat::findOrFail($id);

        [$nama, $ket, $galat] = $this->validasiNama($request, $id);
        if ($galat) {
            return response()->json(['message' => $galat], 422);
        }

        DB::transaction(function () use ($k, $id, $nama, $ket) {
            $data = ['nama_kelompok' => $nama];
            if (KelompokRapat::punyaKolom('keterangan')) {
                $data['keterangan'] = $ket;
            }
            $k->update($data);

            // nama kelompok juga disalin di tabel lain -> samakan
            foreach (['kelompok_rapat_detail', 'rapat', 'rapat_batal_tertunda', 'hasil_rapat'] as $tabel) {
                DB::table($tabel)->where('id_kelompok', $id)->update(['nama_kelompok' => $nama]);
            }
        });

        return response()->json(['id' => $id, 'pesan' => 'Kelompok berhasil diperbarui.']);
    }

    /** Hapus kelompok (hanya bila belum dipakai rapat) */
    public function hapus(int $id)
    {
        $kelompok = KelompokRapat::findOrFail($id);

        $dipakai = Rapat::where('id_kelompok', $id)->count()
                 + DB::table('rapat_batal_tertunda')->where('id_kelompok', $id)->count()
                 + DB::table('hasil_rapat')->where('id_kelompok', $id)->count();

        if ($dipakai > 0) {
            return response()->json([
                'message' => 'Kelompok masih dipakai ' . $dipakai . ' data rapat, jadi tidak bisa dihapus.',
            ], 422);
        }

        DB::transaction(function () use ($kelompok) {
            KelompokRapatDetail::where('id_kelompok', $kelompok->id_kelompok)->delete();
            $kelompok->delete();
        });

        return $this->daftarBaru('Kelompok dihapus.');
    }

    // ======================================================================
    //  ANGGOTA
    // ======================================================================

    /** Tambah anggota: dari data pegawai atau input manual */
    public function tambahAnggota(Request $request, int $id)
    {
        $k = KelompokRapat::findOrFail($id);

        $data = $request->validate([
            'manual'              => 'nullable|boolean',
            'peserta'             => 'required|array|min:1|max:3000',
            'peserta.*.nomor_wa'  => 'required|string|max:20',
            'peserta.*.nama'      => 'required|string|max:100',
        ], [
            'required' => ':attribute wajib diisi.',
            'max'      => ':attribute terlalu panjang.',
            'peserta.required' => 'Pilih minimal satu anggota.',
            'peserta.min'      => 'Pilih minimal satu anggota.',
        ], [
            'peserta.*.nomor_wa' => 'Nomor WA',
            'peserta.*.nama'     => 'Nama',
        ]);

        $manual = (bool) ($data['manual'] ?? false);
        $baru   = [];

        foreach ($data['peserta'] as $p) {
            $wa = trim($p['nomor_wa']);
            if ($manual) {
                // input manual: ambil angka saja dan cek panjangnya
                $wa = preg_replace('/\D+/', '', $wa);
                if (strlen($wa) < 8 || strlen($wa) > 15) {
                    return response()->json(['message' => 'Nomor WA tidak valid (harus 8–15 digit angka).'], 422);
                }
            }
            if ($wa === '') {
                continue;
            }
            $baru[$wa] = ['nomor_wa' => $wa, 'nama' => trim($p['nama'])];
        }

        $sudahAda = KelompokRapatDetail::where('id_kelompok', $id)->pluck('nomor_wa')
            ->map(fn ($n) => trim($n))->flip();

        $tambah = collect($baru)->reject(fn ($p, $wa) => $sudahAda->has($wa))->values();

        if ($tambah->isEmpty()) {
            return response()->json(['message' => 'Semua yang dipilih sudah menjadi anggota kelompok ini.'], 422);
        }

        KelompokRapat::isiAnggota($id, $k->nama_kelompok, $tambah);

        $lewat = count($baru) - $tambah->count();
        $pesan = $tambah->count() . ' anggota ditambahkan' . ($lewat > 0 ? ' (' . $lewat . ' sudah ada, dilewati)' : '') . '.';

        return response()->json(['pesan' => $pesan, 'ditambah' => $tambah->count()]);
    }

    /** Keluarkan satu anggota dari kelompok */
    public function hapusAnggota(int $id, int $detail)
    {
        $hapus = KelompokRapatDetail::where('id_kelompok', $id)->where('id_detail', $detail)->delete();

        if (!$hapus) {
            return response()->json(['message' => 'Anggota tidak ditemukan.'], 404);
        }

        return response()->json(['pesan' => 'Anggota dikeluarkan dari kelompok.']);
    }

    // ======================================================================
    //  DARI HALAMAN BUAT RAPAT: simpan peserta terpilih sebagai kelompok
    // ======================================================================
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'id_kelompok'        => 'nullable|integer|exists:kelompok_rapat,id_kelompok',
            'nama_kelompok'      => 'required|string|max:100',
            'peserta'            => 'required|array|min:1|max:3000',
            'peserta.*.nomor_wa' => 'required|string|max:20',
            'peserta.*.nama'     => 'required|string|max:100',
        ], [
            'required' => ':attribute wajib diisi.',
            'max'      => ':attribute terlalu panjang.',
            'min'      => 'Pilih minimal satu peserta.',
        ], [
            'nama_kelompok' => 'Nama kelompok',
            'peserta'       => 'Peserta',
        ]);

        $nama = trim($data['nama_kelompok']);

        if (str_starts_with($nama, KelompokRapat::PREFIX_OTOMATIS)) {
            return response()->json(['message' => 'Nama kelompok tidak boleh diawali "' . KelompokRapat::PREFIX_OTOMATIS . '".'], 422);
        }

        $peserta = collect($data['peserta'])->unique('nomor_wa')->values();
        $edit    = !empty($data['id_kelompok']);

        DB::transaction(function () use ($data, $nama, $peserta, $edit) {
            if ($edit) {
                $kelompok = KelompokRapat::findOrFail($data['id_kelompok']);
                $kelompok->update(['nama_kelompok' => $nama]);
                KelompokRapatDetail::where('id_kelompok', $kelompok->id_kelompok)->delete();
                Rapat::where('id_kelompok', $kelompok->id_kelompok)->update(['nama_kelompok' => $nama]);
            } else {
                $kelompok = KelompokRapat::buat($nama);
            }

            KelompokRapat::isiAnggota($kelompok->id_kelompok, $nama, $peserta);
        });

        return $this->daftarBaru($edit ? 'Kelompok berhasil diperbarui.' : 'Kelompok berhasil disimpan.');
    }

    // ======================================================================
    //  BANTUAN
    // ======================================================================

    /** Validasi nama kelompok. Mengembalikan [nama, keterangan, pesanGalat|null] */
    private function validasiNama(Request $request, ?int $kecuali): array
    {
        $data = $request->validate([
            'nama_kelompok' => 'required|string|max:100',
            'keterangan'    => 'nullable|string|max:255',
        ], [
            'required' => ':attribute wajib diisi.',
            'max'      => ':attribute terlalu panjang.',
        ], [
            'nama_kelompok' => 'Nama kelompok',
            'keterangan'    => 'Keterangan',
        ]);

        $nama = trim($data['nama_kelompok']);
        $ket  = isset($data['keterangan']) ? trim($data['keterangan']) : null;

        if (str_starts_with($nama, KelompokRapat::PREFIX_OTOMATIS)) {
            return [$nama, $ket, 'Nama kelompok tidak boleh diawali "' . KelompokRapat::PREFIX_OTOMATIS . '".'];
        }

        $kembar = KelompokRapat::where('nama_kelompok', $nama)
            ->when($kecuali, fn ($q) => $q->where('id_kelompok', '!=', $kecuali))
            ->exists();

        if ($kembar) {
            return [$nama, $ket, 'Nama kelompok "' . $nama . '" sudah dipakai.'];
        }

        return [$nama, $ket, null];
    }

    /** Respons JSON: baris tabel (HTML) + daftar untuk pilihan peserta di halaman Buat Rapat */
    private function daftarBaru(string $pesan)
    {
        $daftar = KelompokRapat::ringkasan();

        return response()->json([
            'pesan'     => $pesan,
            'html'      => view('rapat.partials.kelompok-baris', ['daftar' => $daftar])->render(),
            'tersimpan' => collect($daftar)->map(fn ($g) => [
                'id' => $g['id'], 'nama' => $g['nama'], 'jumlah' => $g['jumlah'],
            ])->all(),
        ]);
    }
}