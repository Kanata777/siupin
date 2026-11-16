<?php

namespace App\Http\Controllers;

use App\Jobs\KirimUndanganWa;
use App\Models\KelompokRapat;
use App\Models\Pegawai;
use App\Models\Rapat;
use App\Models\RapatUndangan;
use App\Models\TempatRapat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RapatController extends Controller
{
    /** Jam operasional rapat (harus sama dengan $jamBuka / $jamTutup di view rapat/create) */
    private const JAM_BUKA  = '07:00';
    private const JAM_TUTUP = '15:00';

    /** Batas pagi/siang untuk penentuan sesi otomatis (sama dengan $batasSiang di view) */
    private const BATAS_SIANG = '12:00';

    /** Halaman Buat Rapat / Undangan */
    public function create()
    {
        $tempat = TempatRapat::orderBy('nama_tempat')->get();

        $units = DB::table('unit_pegawai')->orderBy('nama_unit')->pluck('nama_unit');
        if ($units->isEmpty()) {
            $units = Pegawai::whereNotNull('nama_unit')->distinct()->orderBy('nama_unit')->pluck('nama_unit');
        }

        $jabatan = DB::table('jabatan_pegawai')->orderBy('jabatan')->pluck('jabatan');
        if ($jabatan->isEmpty()) {
            $jabatan = Pegawai::whereNotNull('jabatan')->distinct()->orderBy('jabatan')->pluck('jabatan');
        }

        $kelompok = KelompokRapat::ringkasan();

        // Daftar rapat yang sudah pernah mengirim undangan (untuk tabel status WA)
        $pk = (new Rapat)->getKeyName();
        $daftarRapat = Rapat::whereIn($pk, RapatUndangan::select('id_rapat')->distinct())
            ->orderByDesc('tanggal')->orderByDesc('waktu')->limit(30)
            ->get([$pk, 'nama_rapat', 'tanggal', 'waktu']);
        $rapatAktif = request('rapat') ?: optional($daftarRapat->first())->getKey();

        $konfig = [
            'urlPegawai'  => route('rapat.pegawai'),
            'urlCek'      => route('rapat.cek-tempat'),
            'urlAnggota'  => route('kelompok.anggota', '__ID__'),
            'urlSimpan'   => route('kelompok.simpan'),
            'urlStatus'   => route('rapat.status-wa', '__ID__'),
            'urlUlang'    => route('rapat.kirim-ulang', '__ID__'),
            'tersimpan'   => collect($kelompok)->map(fn ($g) => [
                'id' => $g['id'], 'nama' => $g['nama'], 'jumlah' => $g['jumlah'],
            ])->all(),
            'pesertaLama' => json_decode(old('peserta_json', '[]'), true) ?: [],
            'pesanSukses' => session('sukses'),
        ];

        $punya = [
            'selesai'  => $this->punyaKolom('waktu_selesai'),
            'metode'   => $this->punyaKolom('metode'),
            'lampiran' => $this->punyaKolom('lampiran_path'),
        ];

        return view('rapat.create', compact(
            'tempat', 'units', 'jabatan', 'kelompok', 'konfig', 'punya', 'daftarRapat', 'rapatAktif'
        ));
    }

    /** Simpan ke tabel `rapat` (+ antrekan pengiriman WA bila aksi = kirim) */
    public function store(Request $request)
    {
        // peserta dikirim sebagai JSON (hidden input) dari halaman
        $request->merge(['peserta' => json_decode((string) $request->input('peserta_json'), true) ?: []]);

        $buka  = self::JAM_BUKA;
        $tutup = self::JAM_TUTUP;

        // Jam mulai: 07:00 s/d sebelum 15:00. Jam selesai: setelah jam mulai, paling lambat 15:00.
        // Jam selesai wajib hanya bila kolom waktu_selesai ada di tabel (sama seperti di form).
        $aturanSelesai = $this->punyaKolom('waktu_selesai')
            ? "required|date_format:H:i|after:waktu|before_or_equal:{$tutup}"
            : 'nullable';

        $data = $request->validate([
            'nama_rapat'         => 'required|string|max:100',
            'catatan_rapat'      => 'nullable|string|max:255',
            'tanggal'            => 'required|date',
            'waktu'              => "required|date_format:H:i|after_or_equal:{$buka}|before:{$tutup}",
            'waktu_selesai'      => $aturanSelesai,
            'id_tempat'          => 'required|exists:tempat_rapat,id_tempat',
            'metode'             => 'nullable|in:tatap_muka,daring',
            'link_rapat'         => 'nullable|url|max:2000',
            'lampiran'           => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'peserta'            => 'required|array|min:1|max:3000',
            'peserta.*.nomor_wa' => 'required|string|max:20',
            'peserta.*.nama'     => 'required|string|max:100',
            'aksi'               => 'required|in:draft,kirim',
        ], [
            'required'                      => ':attribute wajib diisi.',
            'exists'                        => ':attribute tidak ditemukan.',
            'date'                          => ':attribute tidak valid.',
            'date_format'                   => ':attribute tidak valid.',
            'after'                         => 'Jam selesai harus setelah jam mulai.',
            'waktu.after_or_equal'          => "Waktu mulai paling awal pukul {$buka}.",
            'waktu.before'                  => "Waktu mulai harus sebelum pukul {$tutup}.",
            'waktu_selesai.before_or_equal' => "Waktu selesai paling lambat pukul {$tutup}.",
            'url'                           => 'Link rapat harus berupa alamat URL yang valid.',
            'mimes'                         => 'Lampiran harus berupa PDF atau DOC.',
            'max'                           => ':attribute terlalu besar/panjang.',
            'peserta.required'              => 'Pilih minimal satu peserta.',
            'peserta.min'                   => 'Pilih minimal satu peserta.',
        ], [
            'nama_rapat'    => 'Nama rapat',
            'catatan_rapat' => 'Agenda / topik',
            'tanggal'       => 'Tanggal',
            'waktu'         => 'Waktu mulai',
            'waktu_selesai' => 'Waktu selesai',
            'id_tempat'     => 'Tempat rapat',
            'lampiran'      => 'Lampiran',
        ]);

        $tempat  = TempatRapat::findOrFail($data['id_tempat']);
        $selesai = $this->punyaKolom('waktu_selesai') ? ($data['waktu_selesai'] ?? null) : null;
        $sesi    = $this->tentukanSesi($data['waktu'], $selesai);

        if ($this->bentrok($tempat->id_tempat, $data['tanggal'], $data['waktu'], $selesai)) {
            return back()->withInput()->withErrors([
                'id_tempat' => 'Tempat sudah dipakai rapat lain pada tanggal dan jam tersebut.',
            ]);
        }

        // Peserta unik per nomor WA
        $peserta = collect($data['peserta'])
            ->map(fn ($p) => ['nomor_wa' => trim($p['nomor_wa']), 'nama' => trim($p['nama'])])
            ->unique('nomor_wa')->values();

        $daring = ($data['metode'] ?? 'tatap_muka') === 'daring';
        $aksi   = $data['aksi'];

        $rapat = DB::transaction(function () use ($data, $tempat, $peserta, $selesai, $daring, $aksi, $request) {
            // Pakai kelompok tersimpan bila anggotanya persis sama, kalau tidak buat kelompok otomatis
            $kelompok = $this->cariKelompokSama($peserta->pluck('nomor_wa')->all())
                ?? $this->buatKelompokOtomatis($data['nama_rapat'], $data['tanggal'], $peserta);

            $baris = [
                'nama_rapat'          => $data['nama_rapat'],
                'tanggal'             => $data['tanggal'],
                'waktu'               => $data['waktu'] . ':00',
                'tempat_rapat'        => $tempat->nama_tempat,
                'id_tempat'           => $tempat->id_tempat,
                'status'              => $aksi === 'kirim' ? Rapat::STATUS_TERJADWAL : Rapat::STATUS_DRAFT,
                'jumlah_peserta'      => $peserta->count(),
                'catatan_rapat'       => $data['catatan_rapat'] ?? '',
                'pengundang'          => auth()->user()->name ?? 'SDM YNH',
                'link_undangan_rapat' => $daring ? ($data['link_rapat'] ?? '') : '',
                'id_kelompok'         => $kelompok->id_kelompok,
                'nama_kelompok'       => $kelompok->nama_kelompok,
            ];

            // Kolom opsional (lihat database/tambah_kolom_rapat.sql)
            if ($this->punyaKolom('waktu_selesai')) {
                $baris['waktu_selesai'] = $selesai ? $selesai . ':00' : null;
            }
            if ($this->punyaKolom('metode')) {
                $baris['metode'] = $daring ? 'daring' : 'tatap_muka';
            }
            if ($this->punyaKolom('lampiran_path') && $request->hasFile('lampiran')) {
                $baris['lampiran_path'] = $request->file('lampiran')->store('lampiran_rapat', 'public');
            }

            $rapat = Rapat::create($baris);

            // Satu baris status per peserta (hanya bila langsung dikirim)
            if ($aksi === 'kirim') {
                $now = now();
                $peserta->map(fn ($p) => [
                    'id_rapat'   => $rapat->getKey(),
                    'nomor_wa'   => $p['nomor_wa'],
                    'nama'       => $p['nama'],
                    'status'     => RapatUndangan::PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->chunk(500)->each(fn ($c) => RapatUndangan::insert($c->all()));
            }

            return $rapat;
        });

        if ($aksi === 'kirim') {
            // Dispatch SETELAH transaksi commit, supaya job pasti menemukan datanya
            $this->antrekan(
                RapatUndangan::where('id_rapat', $rapat->getKey())->orderBy('id')->pluck('id')
            );

            return redirect()->route('rapat.create', ['rapat' => $rapat->getKey()])
                ->with('sukses', 'Rapat berhasil dibuat (' . $sesi . '). Undangan WA sedang dikirim, pantau statusnya di bawah.');
        }

        return redirect()->route('rapat.jadwal')->with('sukses', 'Draft rapat berhasil disimpan (' . $sesi . ').');
    }

    /** Status pengiriman WA per peserta (dipanggil JS) */
    public function statusWa(Request $request, int $id)
    {
        $hitung = RapatUndangan::where('id_rapat', $id)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $ringkasan = [
            'pending'  => (int) ($hitung[RapatUndangan::PENDING] ?? 0),
            'terkirim' => (int) ($hitung[RapatUndangan::TERKIRIM] ?? 0),
            'gagal'    => (int) ($hitung[RapatUndangan::GAGAL] ?? 0),
            'total'    => (int) $hitung->sum(),
        ];

        $filter = $request->query('status');

        $daftar = RapatUndangan::where('id_rapat', $id)
            ->when(
                in_array($filter, [RapatUndangan::PENDING, RapatUndangan::TERKIRIM, RapatUndangan::GAGAL], true),
                fn ($q) => $q->where('status', $filter)
            )
            ->orderBy('id')->limit(500)->get();

        return response()->json([
            'ringkasan' => $ringkasan,
            'html'      => view('rapat.partials.status-wa-baris', ['daftar' => $daftar])->render(),
        ]);
    }

    /** Kirim ulang yang gagal (semua, atau satu lewat id_undangan) */
    public function kirimUlang(Request $request, int $id)
    {
        $q = RapatUndangan::where('id_rapat', $id)->where('status', RapatUndangan::GAGAL);
        if ($request->filled('id_undangan')) {
            $q->where('id', (int) $request->input('id_undangan'));
        }
        $ids = $q->pluck('id');

        if ($ids->isEmpty()) {
            return response()->json(['message' => 'Tidak ada pesan gagal untuk dikirim ulang.'], 422);
        }

        RapatUndangan::whereIn('id', $ids)->update(['status' => RapatUndangan::PENDING, 'error' => null]);
        $this->antrekan($ids);

        return response()->json(['pesan' => $ids->count() . ' pesan dijadwalkan kirim ulang.']);
    }

    /** Cek ketersediaan tempat (dipanggil JS) */
    public function cekTempat(Request $request)
    {
        $id = (int) $request->query('id_tempat');
        $tg = (string) $request->query('tanggal');
        $wk = (string) $request->query('waktu');
        $sl = (string) $request->query('waktu_selesai');

        if (!$id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tg) || !preg_match('/^\d{2}:\d{2}$/', $wk)) {
            return response()->json(['tersedia' => null]);
        }
        $sl = preg_match('/^\d{2}:\d{2}$/', $sl) && $sl > $wk ? $sl : null;

        return response()->json(['tersedia' => !$this->bentrok($id, $tg, $wk, $sl)]);
    }

    /** Pencarian pegawai (hanya yang punya nomor WA): ?q= | ?unit= | ?jabatan= */
    public function pegawai(Request $request)
    {
        $query = Pegawai::query()->whereNotNull('nomor_wa')->where('nomor_wa', '!=', '');

        if ($kata = trim((string) $request->query('q'))) {
            $like = '%' . $kata . '%';
            $query->where(function ($w) use ($like) {
                $w->where('nama_lengkap', 'like', $like)
                  ->orWhere('nik', 'like', $like)
                  ->orWhere('nama_unit', 'like', $like)
                  ->orWhere('jabatan', 'like', $like)
                  ->orWhere('panggilan_dan_nama', 'like', $like);
            });
            $batas = 20;
        } elseif ($request->filled('unit')) {
            $query->where('nama_unit', $request->query('unit'));
            $batas = 3000;
        } elseif ($request->filled('jabatan')) {
            $query->where('jabatan', $request->query('jabatan'));
            $batas = 3000;
        } else {
            return response()->json([]);
        }

        return response()->json(
            $query->orderBy('nama_lengkap')->limit($batas)
                ->get(['nomor_wa', 'nama_lengkap', 'nama_unit', 'jabatan'])
                ->map(fn ($p) => [
                    'nomor_wa' => trim($p->nomor_wa),
                    'nama'     => $p->nama_lengkap,
                    'unit'     => $p->nama_unit,
                    'jabatan'  => $p->jabatan,
                ])->values()
        );
    }

    /** Halaman Jadwal Rapat (daftar dari tabel `rapat`) */
    public function jadwal(Request $request)
    {
        $rapat = Rapat::query()
            ->when($request->query('q'), fn ($q, $kata) => $q->where('nama_rapat', 'like', '%' . $kata . '%'))
            ->when($request->query('status'), fn ($q, $st) => $q->where('status', $st))
            ->orderByDesc('tanggal')
            ->orderByDesc('waktu')
            ->paginate(10)
            ->withQueryString();

        $daftarStatus = Rapat::select('status')->distinct()->orderBy('status')->pluck('status');

        return view('rapat.jadwal', compact('rapat', 'daftarStatus'));
    }

    public function saya()
    {
        return view('rapat.saya');
    }

    // ------------------------------------------------------------------

    /** Masukkan job kirim WA ke antrean dengan jeda antar pesan (config/wa.php) */
    private function antrekan($ids): void
    {
        $jeda = max(0, (int) config('wa.jeda', 3));

        foreach ($ids->values() as $i => $idUndangan) {
            KirimUndanganWa::dispatch((int) $idUndangan)->delay(now()->addSeconds($i * $jeda));
        }
    }

    /**
     * Menentukan sesi dari jam mulai & selesai (format "H:i"):
     *   selesai <= BATAS_SIANG -> Sesi Pagi
     *   mulai   >= BATAS_SIANG -> Sesi Siang
     *   selain itu             -> Seharian (Pagi + Siang)
     */
    private function tentukanSesi(string $mulai, ?string $selesai): string
    {
        $batas = self::BATAS_SIANG;

        if ($selesai === null || $selesai === '') {
            return $mulai >= $batas ? 'Sesi Siang' : 'Sesi Pagi';
        }
        if ($selesai <= $batas) {
            return 'Sesi Pagi';
        }
        if ($mulai >= $batas) {
            return 'Sesi Siang';
        }
        return 'Seharian (Pagi + Siang)';
    }

    private function punyaKolom(string $nama): bool
    {
        static $cache = [];
        return $cache[$nama] ??= Schema::hasColumn('rapat', $nama);
    }

    /** Rapat lain (belum batal/tertunda) di tempat + tanggal yang jamnya bertabrakan */
    private function bentrok(int $idTempat, string $tanggal, string $waktu, ?string $selesai = null): bool
    {
        $q = Rapat::where('id_tempat', $idTempat)
            ->whereDate('tanggal', $tanggal)
            ->whereRaw('LOWER(status) NOT LIKE ?', ['%batal%'])
            ->whereRaw('LOWER(status) NOT LIKE ?', ['%tunda%']);

        $mulai = $waktu . ':00';

        if ($selesai && $this->punyaKolom('waktu_selesai')) {
            $akhir = $selesai . ':00';
            $q->where(function ($w) use ($mulai, $akhir) {
                $w->where(fn ($x) => $x->whereNotNull('waktu_selesai')
                        ->where('waktu', '<', $akhir)->where('waktu_selesai', '>', $mulai))
                  ->orWhere(fn ($x) => $x->whereNull('waktu_selesai')
                        ->where('waktu', '>=', $mulai)->where('waktu', '<', $akhir));
            });
        } else {
            $q->where('waktu', $mulai);
        }

        return $q->exists();
    }

    /** $nomor harus sudah terurut (SORT_STRING) */
    private function cariKelompokSama(array $nomor): ?KelompokRapat
    {
        sort($nomor, SORT_STRING);

        $kandidat = KelompokRapat::withCount('anggota')->get()->where('anggota_count', count($nomor));
        foreach ($kandidat as $k) {
            $isi = $k->anggota()->pluck('nomor_wa')->map(fn ($n) => trim($n))->all();
            sort($isi, SORT_STRING);
            if ($isi === $nomor) {
                return $k;
            }
        }
        return null;
    }

    private function buatKelompokOtomatis(string $namaRapat, string $tanggal, $peserta): KelompokRapat
    {
        $nama = mb_substr(
            KelompokRapat::PREFIX_OTOMATIS . $namaRapat . ' (' . date('d-m-Y', strtotime($tanggal)) . ')',
            0, 100
        );

        $kelompok = KelompokRapat::buat($nama);
        KelompokRapat::isiAnggota($kelompok->id_kelompok, $nama, $peserta);

        return $kelompok;
    }
}