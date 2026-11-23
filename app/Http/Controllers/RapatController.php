<?php

namespace App\Http\Controllers;

use App\Jobs\KirimUndanganWa;
use App\Models\KelompokRapat;
use App\Models\Pegawai;
use App\Models\Rapat;
use App\Models\RapatUndangan;
use App\Models\TempatRapat;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RapatController extends Controller
{
    /** Jam operasional rapat (harus sama dengan $jamBuka / $jamTutup di view rapat/create) */
    private const JAM_BUKA = '07:00';
    private const JAM_TUTUP = '15:00';

    /** Batas pagi/siang untuk penentuan sesi otomatis (sama dengan $batasSiang di view) */
    private const BATAS_SIANG = '12:00';

    /** Nilai pilihan "Tempat di luar kantor (sementara)" pada select tempat */
    private const TEMPAT_LUAR = 'luar';

    /** Panjang maksimal kolom rapat.tempat_rapat (varchar 255) */
    private const MAKS_TEMPAT = 255;

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

        // Status pengiriman WA hanya ditampilkan untuk rapat yang baru dikirim (?rapat=ID)
        // dan hanya bila rapat tersebut memang punya data undangan.
        $pk = (new Rapat)->getKeyName();
        $rapatBaru = null;
        $idRapat = (int) request('rapat');
        if ($idRapat > 0 && RapatUndangan::where('id_rapat', $idRapat)->exists()) {
            // Hanya rapat milik user login (Super Admin: semua); selain itu dianggap tidak ada
            $rapatBaru = Rapat::terlihat()->where($pk, $idRapat)->first([$pk, 'nama_rapat', 'tanggal', 'waktu']);
        }
        $rapatAktif = $rapatBaru ? $rapatBaru->getKey() : null;

        $konfig = [
            'urlPegawai' => route('rapat.pegawai'),
            'urlCek' => route('rapat.cek-tempat'),
            'urlAnggota' => route('kelompok.anggota', '__ID__'),
            'urlSimpan' => route('kelompok.simpan'),
            'urlStatus' => route('rapat.status-wa', '__ID__'),
            'urlUlang' => route('rapat.kirim-ulang', '__ID__'),
            'tersimpan' => collect($kelompok)->map(fn($g) => [
                'id' => $g['id'],
                'nama' => $g['nama'],
                'jumlah' => $g['jumlah'],
            ])->all(),
            'pesertaLama' => json_decode(old('peserta_json', '[]'), true) ?: [],
            'pesanSukses' => session('sukses'),
        ];

        $punya = [
            'selesai' => $this->punyaKolom('waktu_selesai'),
            'metode' => $this->punyaKolom('metode'),
            'lampiran' => $this->punyaKolom('lampiran_path'),
        ];

        return view('rapat.create', compact(
            'tempat',
            'units',
            'jabatan',
            'kelompok',
            'konfig',
            'punya',
            'rapatBaru',
            'rapatAktif'
        ));
    }

    /** Simpan ke tabel `rapat` (+ antrekan pengiriman WA bila aksi = kirim) */
    public function store(Request $request)
    {
        // peserta dikirim sebagai JSON (hidden input) dari halaman
        $request->merge(['peserta' => json_decode((string) $request->input('peserta_json'), true) ?: []]);

        // Tempat di luar kantor (sementara): tidak disimpan ke tabel tempat_rapat
        $luar = $request->input('id_tempat') === self::TEMPAT_LUAR;

        // Tempat luar kantor menyimpan id_tempat = NULL, jadi kolomnya harus boleh NULL.
        if ($luar && !$this->idTempatBolehNull()) {
            return back()->withInput()->withErrors([
                'id_tempat' => 'Database belum siap untuk tempat di luar kantor. Jalankan: '
                    . 'ALTER TABLE rapat MODIFY id_tempat INT NULL DEFAULT NULL;',
            ]);
        }

        $buka = self::JAM_BUKA;
        $tutup = self::JAM_TUTUP;

        // Jam mulai: 07:00 s/d sebelum 15:00. Jam selesai: setelah jam mulai, paling lambat 15:00.
        // Jam selesai wajib hanya bila kolom waktu_selesai ada di tabel (sama seperti di form).
        $aturanSelesai = $this->punyaKolom('waktu_selesai')
            ? "required|date_format:H:i|after:waktu|before_or_equal:{$tutup}"
            : 'nullable';

        $data = $request->validate([
            'nama_rapat' => 'required|string|max:100',
            'catatan_rapat' => 'nullable|string|max:255',
            'tanggal' => 'required|date',
            'waktu' => "required|date_format:H:i|after_or_equal:{$buka}|before:{$tutup}",
            'waktu_selesai' => $aturanSelesai,
            'id_tempat' => $luar ? 'required|in:' . self::TEMPAT_LUAR : 'required|exists:tempat_rapat,id_tempat',
            'tempat_luar_nama' => $luar ? 'required|string|max:60' : 'nullable',
            'tempat_luar_ket' => 'nullable|string|max:100',
            'metode' => 'nullable|in:tatap_muka,daring',
            'link_rapat' => 'nullable|url|max:2000',
            'lampiran' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'peserta' => 'required|array|min:1|max:3000',
            'peserta.*.nomor_wa' => 'required|string|max:20',
            'peserta.*.nama' => 'required|string|max:100',
            'aksi' => 'required|in:draft,kirim',
        ], [
            'required' => ':attribute wajib diisi.',
            'exists' => ':attribute tidak ditemukan.',
            'date' => ':attribute tidak valid.',
            'date_format' => ':attribute tidak valid.',
            'after' => 'Jam selesai harus setelah jam mulai.',
            'waktu.after_or_equal' => "Waktu mulai paling awal pukul {$buka}.",
            'waktu.before' => "Waktu mulai harus sebelum pukul {$tutup}.",
            'waktu_selesai.before_or_equal' => "Waktu selesai paling lambat pukul {$tutup}.",
            'url' => 'Link rapat harus berupa alamat URL yang valid.',
            'mimes' => 'Lampiran harus berupa PDF atau DOC.',
            'max' => ':attribute terlalu besar/panjang.',
            'peserta.required' => 'Pilih minimal satu peserta.',
            'peserta.min' => 'Pilih minimal satu peserta.',
        ], [
            'nama_rapat' => 'Nama rapat',
            'catatan_rapat' => 'Agenda / topik',
            'tanggal' => 'Tanggal',
            'waktu' => 'Waktu mulai',
            'waktu_selesai' => 'Waktu selesai',
            'id_tempat' => 'Tempat rapat',
            'tempat_luar_nama' => 'Nama tempat di luar kantor',
            'tempat_luar_ket' => 'Keterangan lokasi',
            'lampiran' => 'Lampiran',
        ]);

        $selesai = $this->punyaKolom('waktu_selesai') ? ($data['waktu_selesai'] ?? null) : null;
        $sesi = $this->tentukanSesi($data['waktu'], $selesai);

        if ($luar) {
            // Hanya untuk rapat ini: nama + keterangan digabung ke kolom tempat_rapat, id_tempat dikosongkan
            $idTempat = null;
            $namaTempat = $this->susunTempatLuar($data['tempat_luar_nama'], $data['tempat_luar_ket'] ?? '');
        } else {
            $tempat = TempatRapat::findOrFail($data['id_tempat']);
            $idTempat = $tempat->id_tempat;
            $namaTempat = $tempat->nama_tempat;

            if ($this->bentrok($idTempat, $data['tanggal'], $data['waktu'], $selesai)) {
                return back()->withInput()->withErrors([
                    'id_tempat' => 'Tempat sudah dipakai rapat lain pada jadwal tersebut atau kurang dari '
                        . Rapat::JEDA_MENIT . ' menit dari rapat lain. Beri jeda minimal '
                        . Rapat::JEDA_MENIT . ' menit.',
                ]);
            }
        }

        // Peserta unik per nomor WA (0812... dan 62812... dianggap nomor yang sama)
        $peserta = collect($data['peserta'])
            ->map(fn($p) => ['nomor_wa' => trim($p['nomor_wa']), 'nama' => trim($p['nama'])])
            ->unique(fn($p) => WhatsAppService::normalisasiNomor($p['nomor_wa']) ?? $p['nomor_wa'])
            ->values();

        $daring = ($data['metode'] ?? 'tatap_muka') === 'daring';
        $aksi = $data['aksi'];

        $rapat = DB::transaction(function () use ($data, $idTempat, $namaTempat, $peserta, $selesai, $daring, $aksi, $request) {
            // Pakai kelompok tersimpan bila anggotanya persis sama, kalau tidak buat kelompok otomatis
            $kelompok = $this->cariKelompokSama($peserta->pluck('nomor_wa')->all())
                ?? $this->buatKelompokOtomatis($data['nama_rapat'], $data['tanggal'], $peserta);

            $baris = [
                'nama_rapat' => $data['nama_rapat'],
                'tanggal' => $data['tanggal'],
                'waktu' => $data['waktu'] . ':00',
                'tempat_rapat' => $namaTempat,
                'id_tempat' => $idTempat,
                'status' => $aksi === 'kirim' ? Rapat::STATUS_TERJADWAL : Rapat::STATUS_DRAFT,
                'jumlah_peserta' => $peserta->count(),
                'catatan_rapat' => $data['catatan_rapat'] ?? '',
                // Penyelenggara & pemilik selalu dari user yang login, tidak pernah dari input form
                'pengundang' => auth()->user()->name,
                'user_id' => auth()->id(),
                'link_undangan_rapat' => $daring ? ($data['link_rapat'] ?? '') : '',
                'id_kelompok' => $kelompok->id_kelompok,
                'nama_kelompok' => $kelompok->nama_kelompok,
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
                $peserta->map(fn($p) => [
                    'id_rapat' => $rapat->getKey(),
                    'nomor_wa' => $p['nomor_wa'],
                    'nama' => $p['nama'],
                    'status' => RapatUndangan::PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->chunk(500)->each(fn($c) => RapatUndangan::insert($c->all()));
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

        // Jadwal global hanya untuk Super Admin; User biasa melihat drafnya di Rapat Saya
        return redirect()->route(Rapat::bolehSemua() ? 'rapat.jadwal' : 'rapat.saya')
            ->with('sukses', 'Draft rapat berhasil disimpan (' . $sesi . ').');
    }

    /** Status pengiriman WA per peserta (dipanggil JS) */
    public function statusWa(Request $request, int $id)
    {
        $this->pastikanMilik($id);

        $hitung = RapatUndangan::where('id_rapat', $id)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $ringkasan = [
            'pending' => (int) ($hitung[RapatUndangan::PENDING] ?? 0),
            'terkirim' => (int) ($hitung[RapatUndangan::TERKIRIM] ?? 0),
            'gagal' => (int) ($hitung[RapatUndangan::GAGAL] ?? 0),
            'total' => (int) $hitung->sum(),
        ];

        $filter = $request->query('status');

        $daftar = RapatUndangan::where('id_rapat', $id)
            ->when(
                in_array($filter, [RapatUndangan::PENDING, RapatUndangan::TERKIRIM, RapatUndangan::GAGAL], true),
                fn($q) => $q->where('status', $filter)
            )
            ->orderBy('id')->limit(500)->get();

        return response()->json([
            'ringkasan' => $ringkasan,
            'html' => view('rapat.partials.status-wa-baris', ['daftar' => $daftar])->render(),
        ]);
    }

    /** Kirim ulang yang gagal (semua, atau satu lewat id_undangan) */
    public function kirimUlang(Request $request, int $id)
    {
        $this->pastikanMilik($id);

        $q = RapatUndangan::where('id_rapat', $id)->where('status', RapatUndangan::GAGAL);
        if ($request->filled('id_undangan')) {
            $q->where('id', (int) $request->input('id_undangan'));
        }
        $daftar = $q->get();

        if ($daftar->isEmpty()) {
            return response()->json(['message' => 'Tidak ada pesan gagal untuk dikirim ulang.'], 422);
        }

        // Ambil nomor terbaru dari data pegawai sebelum dikirim ulang
        $diperbarui = $daftar->filter(fn($u) => $this->segarkanNomor($u))->count();

        $ids = $daftar->pluck('id');
        RapatUndangan::whereIn('id', $ids)->update(['status' => RapatUndangan::PENDING, 'error' => null]);
        $this->antrekan($ids);

        $pesan = $ids->count() . ' pesan dijadwalkan kirim ulang.';
        if ($diperbarui > 0) {
            $pesan .= ' ' . $diperbarui . ' nomor diperbarui dari data pegawai.';
        }

        return response()->json(['pesan' => $pesan]);
    }

    /**
     * Samakan nomor di tabel undangan dengan data pegawai terbaru.
     * Dicocokkan lewat nama; hanya diubah bila hasilnya tepat satu pegawai.
     * (Peserta dari luar tidak ada di data pegawai, jadi nomornya tidak diubah.)
     */
    private function segarkanNomor(RapatUndangan $u): bool
    {
        $lama = trim((string) $u->nomor_wa);

        $nomorPegawai = Pegawai::where('nama_lengkap', trim((string) $u->nama))
            ->whereNotNull('nomor_wa')->where('nomor_wa', '!=', '')
            ->pluck('nomor_wa')->map(fn($n) => trim($n))->unique()->values();

        // Tidak ketemu, atau nomor lama masih tercatat di data pegawai -> tidak ada yang diubah
        if ($nomorPegawai->isEmpty() || $nomorPegawai->contains($lama)) {
            return false;
        }
        // Nama kembar dengan nomor berbeda -> tidak bisa dipastikan, biarkan
        if ($nomorPegawai->count() > 1) {
            return false;
        }

        $baru = $nomorPegawai->first();

        // Nomor baru sudah dipakai peserta lain di rapat yang sama (kolom unik)
        $dipakai = RapatUndangan::where('id_rapat', $u->id_rapat)
            ->where('nomor_wa', $baru)->where('id', '!=', $u->id)->exists();
        if ($dipakai) {
            return false;
        }

        $u->update(['nomor_wa' => $baru]);
        return true;
    }

    /** Cek ketersediaan satu tempat (dipanggil JS lama) */
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

    /** Status SEMUA tempat untuk tanggal + jam tertentu (tersedia / tidak tersedia) */
    public function statusTempat(Request $request)
    {
        $tg = (string) $request->query('tanggal');
        $wk = (string) $request->query('waktu');
        $sl = (string) $request->query('waktu_selesai');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tg) || !preg_match('/^\d{2}:\d{2}$/', $wk)) {
            return response()->json(['tempat' => (object) []]);
        }
        $sl = preg_match('/^\d{2}:\d{2}$/', $sl) && $sl > $wk ? $sl : null;

        // Pengecekan bentrok tetap melihat rapat semua user, tetapi nama rapat milik user lain
        // tidak ditampilkan ke User biasa (hanya jam yang terpakai).
        $semua  = Rapat::bolehSemua();
        $milikku = (int) auth()->id();

        $hasil = [];
        foreach (TempatRapat::pluck('id_tempat') as $id) {
            $pakai = $this->queryBentrok((int) $id, $tg, $wk, $sl)
                ->orderBy('waktu')
                ->get(['nama_rapat', 'waktu', 'waktu_selesai', 'user_id']);

            $hasil[$id] = [
                'tersedia' => $pakai->isEmpty(),
                'dipakai'  => $pakai->map(fn($r) => (($semua || (int) $r->user_id === $milikku) ? $r->nama_rapat : 'Rapat lain') . ' ('
                    . substr($r->waktu, 0, 5)
                    . ($r->waktu_selesai ? '–' . substr($r->waktu_selesai, 0, 5) : '')
                    . ')')->values(),
            ];
        }

        return response()->json(['tempat' => $hasil]);
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
                ->map(fn($p) => [
                    'nomor_wa' => trim($p->nomor_wa),
                    'nama' => $p->nama_lengkap,
                    'unit' => $p->nama_unit,
                    'jabatan' => $p->jabatan,
                ])->values()
        );
    }

    // ------------------------------------------------------------------

    /** Pastikan rapat ada dan boleh diakses user login; selain itu 404 (tidak membocorkan keberadaannya) */
    private function pastikanMilik(int $id): void
    {
        abort_unless(
            Rapat::terlihat()->where((new Rapat)->getKeyName(), $id)->exists(),
            404
        );
    }

    /** Masukkan job kirim WA ke antrean dengan jeda antar pesan (config/wa.php) */
    private function antrekan($ids): void
    {
        $jeda = max(0, (int) config('wa.jeda', 3));

        foreach ($ids->values() as $i => $idUndangan) {
            KirimUndanganWa::dispatch((int) $idUndangan)->delay(now()->addSeconds($i * $jeda));
        }
    }

    /**
     * Teks tempat di luar kantor yang ikut tampil di pesan WA.
     * Contoh: "Hotel Santika (di luar kantor) - Jl. Merdeka No. 10"
     */
    private function susunTempatLuar(string $nama, string $keterangan): string
    {
        $teks = trim($nama) . ' (di luar kantor)';
        $keterangan = trim($keterangan);
        if ($keterangan !== '') {
            $teks .= ' - ' . $keterangan;
        }

        return mb_substr($teks, 0, self::MAKS_TEMPAT);
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

    /** Apakah kolom rapat.id_tempat sudah boleh NULL (dibutuhkan untuk tempat di luar kantor) */
    private function idTempatBolehNull(): bool
    {
        static $hasil = null;

        if ($hasil === null) {
            $baris = DB::selectOne(
                "SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rapat' AND COLUMN_NAME = 'id_tempat'"
            );
            $hasil = $baris !== null && strtoupper((string) $baris->n) === 'YES';
        }

        return $hasil;
    }

    /**
     * Query rapat lain (belum batal/tertunda) di tempat + tanggal yang jamnya bertabrakan,
     * termasuk jeda Rapat::JEDA_MENIT menit sebelum dan sesudah rapat lain.
     */
    private function queryBentrok(int $idTempat, string $tanggal, string $waktu, ?string $selesai = null)
    {
        $q = Rapat::where('id_tempat', $idTempat)
            ->whereDate('tanggal', $tanggal)
            ->whereRaw('LOWER(status) NOT LIKE ?', ['%batal%'])
            ->whereRaw('LOWER(status) NOT LIKE ?', ['%tunda%']);

        $punyaSelesai = $this->punyaKolom('waktu_selesai');
        $mulai = $waktu . ':00';
        $akhir = ($selesai && $punyaSelesai) ? $selesai . ':00' : $mulai;

        $selesaiLama = $punyaSelesai ? 'COALESCE(waktu_selesai, waktu)' : 'waktu';
        $jeda = sprintf('%02d:%02d:00', intdiv(Rapat::JEDA_MENIT, 60), Rapat::JEDA_MENIT % 60);

        // bentrok bila: mulai_baru <= selesai_lama + jeda  DAN  selesai_baru + jeda >= mulai_lama
        $q->whereRaw("TIME(?) < ADDTIME($selesaiLama, '$jeda')", [$mulai])
          ->whereRaw("ADDTIME(TIME(?), '$jeda') > waktu", [$akhir]);

        return $q;
    }

    /** Apakah tempat sudah dipakai rapat lain pada tanggal dan jam tersebut (termasuk jeda) */
    private function bentrok(int $idTempat, string $tanggal, string $waktu, ?string $selesai = null): bool
    {
        return $this->queryBentrok($idTempat, $tanggal, $waktu, $selesai)->exists();
    }

    /** $nomor harus sudah terurut (SORT_STRING) */
    private function cariKelompokSama(array $nomor): ?KelompokRapat
    {
        sort($nomor, SORT_STRING);

        $kandidat = KelompokRapat::withCount('anggota')->get()->where('anggota_count', count($nomor));
        foreach ($kandidat as $k) {
            $isi = $k->anggota()->pluck('nomor_wa')->map(fn($n) => trim($n))->all();
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
            0,
            100
        );

        $kelompok = KelompokRapat::buat($nama);
        KelompokRapat::isiAnggota($kelompok->id_kelompok, $nama, $peserta);

        return $kelompok;
    }
}