<?php

// Simpan sebagai: app/Http/Controllers/JadwalRapatController.php (ganti SELURUH isi file lama)

namespace App\Http\Controllers;

use App\Jobs\KirimPembaruanRapatWa;
use App\Jobs\KirimUndanganWa;
use App\Models\KelompokRapat;
use App\Models\KelompokRapatDetail;
use App\Models\Pegawai;
use App\Models\Rapat;
use App\Models\RapatUndangan;
use App\Models\TempatRapat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalRapatController extends Controller
{
    /** Jumlah baris per halaman (7 agar muat dalam satu layar, seperti desain) */
    private const PER_HALAMAN = 7;

    /** kunci status => [label, warna lencana] */
    private const LABEL = [
        'mendatang'   => ['Mendatang',   'biru'],
        'berlangsung' => ['Berlangsung', 'hijau'],
        'selesai'     => ['Selesai',     'abu'],
        'terlewat'    => ['Terlewat',    'kuning'],
        'tertunda'    => ['Tertunda',    'oranye'],
        'draft'       => ['Draft',       'abu'],
        'dibatalkan'  => ['Dibatalkan',  'merah'],
        'lainnya'     => ['Lainnya',     'abu'],
    ];

    /** Rapat dengan status tampil ini masih boleh diubah */
    private const BISA_UBAH = ['mendatang', 'terlewat', 'tertunda', 'draft', 'lainnya'];

    /** Rapat yang kelompoknya memiliki anggota dengan kondisi unit tertentu (%s diisi kondisi) */
    private const UNIT_ADA = "EXISTS (SELECT 1 FROM kelompok_rapat_detail d
                JOIN pegawai p ON p.nomor_wa = d.nomor_wa COLLATE utf8mb4_general_ci
                WHERE d.id_kelompok = rapat.id_kelompok AND %s)";

    // ======================================================================
    //  DAFTAR JADWAL
    // ======================================================================
    public function index(Request $request)
    {
        // Jadwal Rapat global hanya untuk Super Admin; User biasa dialihkan ke Rapat Saya
        if (!Rapat::bolehSemua()) {
            return redirect()->route('rapat.saya');
        }

        return $this->tampilkanDaftar($request, false);
    }

    // ======================================================================
    //  RAPAT SAYA: hanya rapat yang dibuat user yang sedang login
    // ======================================================================
    public function saya(Request $request)
    {
        return $this->tampilkanDaftar($request, true);
    }

    /** Daftar rapat (dipakai bersama oleh Jadwal Rapat dan Rapat Saya; tampilan sama) */
    private function tampilkanDaftar(Request $request, bool $saya)
    {
        Carbon::setLocale('id');

        $rute     = $saya ? 'rapat.saya' : 'rapat.jadwal';
        $judul    = $saya ? 'Rapat Saya' : 'Jadwal Rapat';
        $subjudul = $saya
            ? 'Daftar rapat/undangan yang Anda buat, beserta status pelaksanaannya.'
            : 'Daftar rencana, pelaksanaan, dan status seluruh rapat/undangan.';

        $punyaSelesai = Rapat::punyaKolom('waktu_selesai');
        $caseSql      = $this->ekspresiStatus($punyaSelesai);

        // ----- periode: default = bulan berjalan -----
        $dari   = $this->tanggalValid($request->query('dari'));
        $sampai = $this->tanggalValid($request->query('sampai'));
        $semua  = $request->boolean('semua');

        if (!$dari && !$sampai && !$semua) {
            $dari   = now()->startOfMonth()->toDateString();
            $sampai = now()->endOfMonth()->toDateString();
        }
        $terbatas = (bool) ($dari || $sampai);

        $dasar = $this->dasar($request, $dari, $sampai, $saya);

        // Untuk pesan kosong "Belum ada rapat yang Anda buat" (tanpa melihat filter/periode)
        $punyaRapat = $saya ? Rapat::milikSaya()->exists() : Rapat::terlihat()->exists();

        // ----- jumlah per status (untuk tab) -----
        $hitung = (clone $dasar)
            ->selectRaw("($caseSql) AS st, COUNT(*) AS n")
            ->groupBy('st')
            ->pluck('n', 'st')
            ->all();

        $status = (string) $request->query('status');
        if (!array_key_exists($status, self::LABEL)) {
            $status = '';
        }

        // ----- daftar rapat -----
        $query = (clone $dasar)->selectRaw(
            "rapat.*, ($caseSql) AS status_tampil,
             COALESCE(rapat.jumlah_peserta,
                 (SELECT COUNT(*) FROM kelompok_rapat_detail dd WHERE dd.id_kelompok = rapat.id_kelompok)) AS jumlah_tampil"
        );
        if ($status !== '') {
            $query->whereRaw("($caseSql) = ?", [$status]);
        }

        $arah  = $terbatas ? 'asc' : 'desc';
        $rapat = $query->orderBy('rapat.tanggal', $arah)
            ->orderBy('rapat.waktu', $arah)
            ->orderBy('rapat.id_rapat', $arah)
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        // ----- tab status -----
        $definisi = [
            ''            => ['Semua',       'kalender-plus'],
            'mendatang'   => ['Mendatang',   'jam'],
            'berlangsung' => ['Berlangsung', 'play'],
            'selesai'     => ['Selesai',     'cek'],
            'terlewat'    => ['Terlewat',    'jam'],
            'tertunda'    => ['Tertunda',    'jam'],
            'draft'       => ['Draft',       'edit'],
            'dibatalkan'  => ['Dibatalkan',  'silang'],
        ];
        $wajib = ['', 'mendatang', 'berlangsung', 'selesai', 'dibatalkan'];
        $tab   = [];

        foreach ($definisi as $kunci => $d) {
            $kunci = (string) $kunci;
            $n = $kunci === '' ? array_sum($hitung) : (int) ($hitung[$kunci] ?? 0);
            if (!in_array($kunci, $wajib, true) && $n === 0 && $status !== $kunci) {
                continue;
            }
            $param = $request->except(['page', 'status']);
            if ($kunci !== '') {
                $param['status'] = $kunci;
            }
            $tab[] = [
                'kunci' => $kunci, 'label' => $d[0], 'ikon' => $d[1],
                'n' => $n, 'url' => route($rute, $param),
            ];
        }

        // ----- pilihan filter -----
        // Hanya unit yang punya pegawai ber-nomor WA (unit kosong tidak ditampilkan di dropdown)
        $units = DB::table('unit_pegawai as u')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pegawai as p')
                    ->whereRaw('TRIM(p.nama_unit) = TRIM(u.nama_unit) COLLATE utf8mb4_general_ci')
                    ->whereNotNull('p.nomor_wa')
                    ->where('p.nomor_wa', '<>', '');
            })
            ->orderBy('u.nama_unit')
            ->pluck('u.nama_unit');
        $tempatList = TempatRapat::orderBy('nama_tempat')->get(['id_tempat', 'nama_tempat']);

        $fmt = fn ($t) => Carbon::parse($t)->translatedFormat('d M Y');
        $teksRentang = ($dari && $sampai) ? $fmt($dari) . ' – ' . $fmt($sampai)
            : ($dari ? 'Mulai ' . $fmt($dari) : ($sampai ? 'Sampai ' . $fmt($sampai) : ''));

        $konfig = [
            'urlShow'      => route('rapat.show', '__ID__'),
            'urlPegawai'   => route('rapat.pegawai'),
            'punyaSelesai' => $punyaSelesai,
        ];

        return view('rapat.jadwal', compact(
            'rapat', 'tab', 'status', 'units', 'tempatList',
            'dari', 'sampai', 'semua', 'terbatas', 'teksRentang', 'konfig', 'punyaSelesai',
            'saya', 'rute', 'judul', 'subjudul', 'punyaRapat'
        ));
    }

    // ======================================================================
    //  DETAIL (JSON) untuk dialog "lihat" dan "ubah"
    // ======================================================================
    public function show(int $id)
    {
        Carbon::setLocale('id');
        $r = $this->satu($id);

        $detail = KelompokRapatDetail::where('id_kelompok', $r->id_kelompok)
            ->orderBy('nama_lengkap')
            ->get(['nama_lengkap', 'nomor_wa']);

        // Unit tiap anggota diambil dari data pegawai (dipakai fitur "hapus per unit" di dialog ubah)
        $unitPegawai = Pegawai::whereIn('nomor_wa', $detail->pluck('nomor_wa')->all())
            ->get(['nomor_wa', 'nama_unit'])
            ->mapWithKeys(fn ($p) => [trim($p->nomor_wa) => $p->nama_unit]);

        $anggota = $detail->map(fn ($d) => [
            'nama'     => $d->nama_lengkap,
            'nomor_wa' => $d->nomor_wa,
            'unit'     => $unitPegawai[trim($d->nomor_wa)] ?? '',
        ])->values();

        [$label, $warna] = self::LABEL[$r->status_tampil] ?? self::LABEL['lainnya'];
        if ($r->status_tampil === 'lainnya') {
            $label = (string) $r->status;
        }

        return response()->json([
            'id'            => $r->id_rapat,
            'nama_rapat'    => $r->nama_rapat,
            'catatan_rapat' => $r->catatan_rapat,
            'tanggal'       => $r->tanggal->toDateString(),
            'tanggal_teks'  => $r->tanggal->translatedFormat('l, d F Y'),
            'waktu'         => substr($r->waktu, 0, 5),
            'waktu_selesai' => $r->waktu_selesai ? substr($r->waktu_selesai, 0, 5) : null,
            'id_tempat'     => $r->id_tempat,
            'tempat_rapat'  => $r->tempat_rapat,
            'pengundang'    => $r->pengundang,
            'nama_kelompok' => $r->nama_kelompok,
            'jumlah'        => $r->jumlah_peserta ?? $anggota->count(),
            'status'        => $r->status,
            'status_tampil' => $r->status_tampil,
            'status_label'  => $label,
            'status_warna'  => $warna,
            'bisa_ubah'     => in_array($r->status_tampil, self::BISA_UBAH, true),
            'link_rapat'    => $r->link_undangan_rapat,
            'lampiran_url'  => $r->lampiran_path ? asset('storage/' . $r->lampiran_path) : null,
            'anggota'       => $anggota,
        ]);
    }

    // ======================================================================
    //  UBAH rapat (JSON): data rapat + peserta + kirim WA
    // ======================================================================
    public function update(Request $request, int $id)
    {
        $r = $this->satu($id);

        if (!in_array($r->status_tampil, self::BISA_UBAH, true)) {
            return response()->json(['message' => 'Rapat dengan status ini tidak dapat diubah.'], 422);
        }

        $data = $request->validate([
            'nama_rapat'                         => 'required|string|max:100',
            'catatan_rapat'                      => 'nullable|string|max:255',
            'tanggal'                            => 'required|date',
            'waktu'                              => 'required|date_format:H:i',
            'waktu_selesai'                      => 'nullable|date_format:H:i|after:waktu',
            'id_tempat'                          => 'required|exists:tempat_rapat,id_tempat',
            'peserta'                            => 'required|array|min:1|max:3000',
            'peserta.*.nomor_wa'                 => 'required|string|max:20',
            'peserta.*.nama'                     => 'required|string|max:100',
            'kirim_pembaruan'                    => 'nullable|boolean',
            'kelompok_unit'                      => 'nullable|array|max:50',
            'kelompok_unit.*.unit'               => 'required|string|max:100',
            'kelompok_unit.*.peserta'            => 'required|array|min:1|max:3000',
            'kelompok_unit.*.peserta.*.nomor_wa' => 'required|string|max:20',
            'kelompok_unit.*.peserta.*.nama'     => 'required|string|max:100',
        ], [
            'required'         => ':attribute wajib diisi.',
            'exists'           => ':attribute tidak ditemukan.',
            'date'             => ':attribute tidak valid.',
            'date_format'      => ':attribute tidak valid.',
            'after'            => 'Jam selesai harus setelah jam mulai.',
            'max'              => ':attribute terlalu panjang.',
            'peserta.required' => 'Pilih minimal satu peserta.',
            'peserta.min'      => 'Pilih minimal satu peserta.',
        ], [
            'nama_rapat'    => 'Nama rapat',
            'catatan_rapat' => 'Agenda / topik',
            'tanggal'       => 'Tanggal',
            'waktu'         => 'Waktu mulai',
            'id_tempat'     => 'Tempat rapat',
            'peserta'       => 'Peserta',
        ]);

        $punyaSelesai = Rapat::punyaKolom('waktu_selesai');
        $tempat  = TempatRapat::findOrFail($data['id_tempat']);
        $selesai = $punyaSelesai ? ($data['waktu_selesai'] ?? null) : null;

        if (Rapat::bentrok($tempat->id_tempat, $data['tanggal'], $data['waktu'], $selesai, $id)) {
            return response()->json(['message' => 'Tempat sudah dipakai rapat lain pada tanggal dan jam tersebut.'], 422);
        }

        // ----- apa saja yang berubah? -----
        $waktuLama   = substr((string) $r->waktu, 0, 5);
        $selesaiLama = ($punyaSelesai && $r->waktu_selesai) ? substr((string) $r->waktu_selesai, 0, 5) : null;

        $waktuBerubah = $r->tanggal->toDateString() !== $data['tanggal']
            || $waktuLama !== $data['waktu']
            || ($punyaSelesai && $selesaiLama !== $selesai);
        $tempatBerubah = (int) $r->id_tempat !== (int) $tempat->id_tempat;
        $jadwalBerubah = $waktuBerubah || $tempatBerubah;

        // data sebelum diubah (untuk teks "sebelumnya" di pesan WA)
        $lama = [
            'tanggal' => $r->tanggal->toDateString(),
            'waktu'   => $waktuLama,
            'selesai' => $selesaiLama,
            'tempat'  => $r->tempat_rapat,
        ];

        // ----- peserta -----
        $peserta = collect($data['peserta'])
            ->map(fn ($p) => ['nomor_wa' => trim($p['nomor_wa']), 'nama' => trim($p['nama'])])
            ->unique('nomor_wa')->values();
        $nomorBaru = $peserta->pluck('nomor_wa');

        $lamaNomor = KelompokRapatDetail::where('id_kelompok', $r->id_kelompok)
            ->pluck('nomor_wa')->map(fn ($n) => trim($n))->values();

        $ditambah = $peserta->reject(fn ($p) => $lamaNomor->contains($p['nomor_wa']))->values();
        $tetap    = $peserta->filter(fn ($p) => $lamaNomor->contains($p['nomor_wa']))->values();
        $dihapus  = $lamaNomor->diff($nomorBaru)->values();
        $pesertaBerubah = $ditambah->isNotEmpty() || $dihapus->isNotEmpty();

        // ----- kelompok tersendiri per unit (hanya anggota yang masih ada di daftar peserta) -----
        $kelompokUnit = collect($data['kelompok_unit'] ?? [])->map(function ($g) use ($nomorBaru) {
            $isi = collect($g['peserta'])
                ->map(fn ($p) => ['nomor_wa' => trim($p['nomor_wa']), 'nama' => trim($p['nama'])])
                ->unique('nomor_wa')
                ->filter(fn ($p) => $nomorBaru->contains($p['nomor_wa']))
                ->values();

            return ['unit' => trim($g['unit']), 'peserta' => $isi];
        })->filter(fn ($g) => $g['peserta']->isNotEmpty())->values();

        // Draft tidak pernah mengirim WA
        $kirimBoleh     = $r->status_tampil !== 'draft';
        $kirimPembaruan = $request->boolean('kirim_pembaruan', true);

        $idBaru      = [];   // id rapat_undangan yang dapat undangan penuh (anggota baru)
        $idPembaruan = [];   // id rapat_undangan yang dapat pemberitahuan perubahan (peserta lama)

        DB::transaction(function () use (
            $id, $data, $tempat, $selesai, $punyaSelesai, $waktuBerubah, $pesertaBerubah,
            $peserta, $nomorBaru, $ditambah, $tetap, $dihapus, $kirimBoleh, $kirimPembaruan, $jadwalBerubah,
            $kelompokUnit,
            &$idBaru, &$idPembaruan
        ) {
            $baru = [
                'nama_rapat'    => $data['nama_rapat'],
                'catatan_rapat' => $data['catatan_rapat'] ?? '',
                'tanggal'       => $data['tanggal'],
                'waktu'         => $data['waktu'] . ':00',
                'tempat_rapat'  => $tempat->nama_tempat,
                'id_tempat'     => $tempat->id_tempat,
            ];
            if ($punyaSelesai) {
                $baru['waktu_selesai'] = $selesai ? $selesai . ':00' : null;
            }
            if ($waktuBerubah) {
                // jadwal diubah -> pengingat WhatsApp otomatis perlu dikirim ulang
                $baru['ingat_rapat']   = 0;
                $baru['ingat_rapat_2'] = 0;
            }

            // Anggota berubah -> pakai/buat kelompok lain. Kelompok lama TIDAK diubah
            // karena bisa dipakai bersama rapat lain.
            if ($pesertaBerubah) {
                $kelompok = $this->cariKelompokSama($nomorBaru->all())
                    ?? $this->buatKelompokOtomatis($data['nama_rapat'], $data['tanggal'], $peserta);

                $baru['id_kelompok']    = $kelompok->id_kelompok;
                $baru['nama_kelompok']  = $kelompok->nama_kelompok;
                $baru['jumlah_peserta'] = $peserta->count();
            }

            Rapat::where('id_rapat', $id)->update($baru);

            // Menambahkan antar unit -> buat kelompok tersendiri untuk tiap unit
            if ($kelompokUnit->count() >= 2) {
                foreach ($kelompokUnit as $g) {
                    $this->buatKelompokUnit($g['unit'], $data['nama_rapat'], $data['tanggal'], $g['peserta']);
                }
            }

            if (!$kirimBoleh) {
                return;
            }

            // Anggota yang dikeluarkan: hapus dari tabel status WA
            if ($dihapus->isNotEmpty()) {
                RapatUndangan::where('id_rapat', $id)
                    ->whereIn('nomor_wa', $dihapus->all())->delete();
            }

            // Anggota baru -> undangan penuh (job membaca data rapat terbaru)
            if ($ditambah->isNotEmpty()) {
                $idBaru = $this->siapkanUndangan($id, $ditambah)->all();
            }

            // Peserta lama -> pemberitahuan perubahan, hanya bila jadwal/tempat berubah
            if ($jadwalBerubah && $kirimPembaruan && $tetap->isNotEmpty()) {
                $idPembaruan = $this->siapkanUndangan($id, $tetap)->all();
            }
        });

        // Dispatch SETELAH transaksi commit, dengan jeda acak antar pesan (config/wa.php)
        $tunda = $this->jadwalTunda(count($idBaru) + count($idPembaruan));
        $urut  = 0;
        foreach ($idBaru as $idU) {
            KirimUndanganWa::dispatch((int) $idU)->delay(now()->addSeconds($tunda[$urut++]));
        }
        foreach ($idPembaruan as $idU) {
            KirimPembaruanRapatWa::dispatch((int) $idU, $lama)->delay(now()->addSeconds($tunda[$urut++]));
        }

        $pesan = 'Rapat berhasil diperbarui.';
        if ($ditambah->isNotEmpty() && $kirimBoleh) {
            $pesan .= ' Undangan WA dikirim ke ' . count($idBaru) . ' anggota baru.';
        }
        if ($idPembaruan) {
            $pesan .= ' Pemberitahuan perubahan dikirim ke ' . count($idPembaruan) . ' peserta.';
        }
        if ($dihapus->isNotEmpty()) {
            $pesan .= ' ' . $dihapus->count() . ' peserta dihapus.';
        }
        if ($kelompokUnit->count() >= 2) {
            $pesan .= ' Kelompok per unit dibuat untuk ' . $kelompokUnit->count() . ' unit.';
        }

        session()->flash('sukses', $pesan);

        return response()->json(['pesan' => $pesan]);
    }

    // ======================================================================
    //  AKSI STATUS
    // ======================================================================

    /** Batalkan rapat + catat di rapat_batal_tertunda */
    public function batal(int $id)
    {
        $r = $this->satu($id);

        if (in_array($r->status_tampil, ['selesai', 'dibatalkan'], true)) {
            return back()->with('galat', 'Rapat dengan status ini tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($r, $id) {
            Rapat::where('id_rapat', $id)->update(['status' => Rapat::NILAI_BATAL]);

            $sudahTercatat = DB::table('rapat_batal_tertunda')
                ->where('id_rapat', $id)
                ->whereRaw('LOWER(status) LIKE ?', ['%batal%'])
                ->exists();

            if (!$sudahTercatat) {
                DB::table('rapat_batal_tertunda')->insert([
                    'id_rapat'             => $id,
                    'nama_rapat'           => $r->nama_rapat,
                    'tempat_rapat'         => $r->tempat_rapat,
                    'waktu'                => $r->waktu,
                    'tanggal'              => $r->tanggal->toDateString(),
                    'id_kelompok'          => $r->id_kelompok,
                    'nama_kelompok'        => $r->nama_kelompok,
                    'jumlah_peserta'       => (int) ($r->jumlah_peserta ?? 0),
                    'status'               => Rapat::NILAI_BATAL,
                    'pengundang'           => $r->pengundang,
                    'ingat_batal_tertunda' => 0,
                ]);
            }
        });

        return back()->with('sukses', 'Rapat "' . $r->nama_rapat . '" dibatalkan.');
    }

    /** Tandai rapat sudah terlaksana */
    public function selesai(int $id)
    {
        $r = $this->satu($id);

        if (!in_array($r->status_tampil, ['berlangsung', 'terlewat'], true)) {
            return back()->with('galat', 'Hanya rapat yang sedang berlangsung atau terlewat yang bisa ditandai selesai.');
        }

        Rapat::where('id_rapat', $id)->update(['status' => Rapat::NILAI_SELESAI]);

        return back()->with('sukses', 'Rapat "' . $r->nama_rapat . '" ditandai selesai.');
    }

    /** Aktifkan kembali rapat yang dibatalkan (jika tanggalnya belum lewat) */
    public function aktifkan(int $id)
    {
        $r = $this->satu($id);

        if ($r->status_tampil !== 'dibatalkan') {
            return back()->with('galat', 'Rapat ini tidak dalam status dibatalkan.');
        }
        if ($r->tanggal->toDateString() < now()->toDateString()) {
            return back()->with('galat', 'Rapat yang tanggalnya sudah lewat tidak dapat diaktifkan kembali.');
        }
        if (Rapat::bentrok((int) $r->id_tempat, $r->tanggal->toDateString(), substr($r->waktu, 0, 5),
                $r->waktu_selesai ? substr($r->waktu_selesai, 0, 5) : null, $id)) {
            return back()->with('galat', 'Tempat sudah dipakai rapat lain pada jam tersebut, rapat tidak dapat diaktifkan.');
        }

        DB::transaction(function () use ($id) {
            Rapat::where('id_rapat', $id)->update([
                'status'        => Rapat::STATUS_TERJADWAL,
                'ingat_batal'   => 0,
            ]);
            DB::table('rapat_batal_tertunda')
                ->where('id_rapat', $id)
                ->whereRaw('LOWER(status) LIKE ?', ['%batal%'])
                ->delete();
        });

        return back()->with('sukses', 'Rapat "' . $r->nama_rapat . '" diaktifkan kembali.');
    }

    /** Salin rapat: buka halaman Buat Rapat dengan isian terisi */
    public function salin(int $id)
    {
        $r = Rapat::terlihat()->where('rapat.id_rapat', $id)->firstOrFail();

        $anggota = KelompokRapatDetail::where('id_kelompok', $r->id_kelompok)
            ->get(['nomor_wa', 'nama_lengkap'])
            ->map(fn ($d) => ['nomor_wa' => trim($d->nomor_wa), 'nama' => $d->nama_lengkap])
            ->values()->all();

        $input = [
            'nama_rapat'    => $r->nama_rapat,
            'catatan_rapat' => $r->catatan_rapat,
            'id_tempat'     => $r->id_tempat,
            'waktu'         => substr($r->waktu, 0, 5),
            'peserta_json'  => json_encode($anggota),
            'link_rapat'    => $r->link_undangan_rapat,
            'metode'        => $r->link_undangan_rapat ? 'daring' : 'tatap_muka',
        ];
        if (Rapat::punyaKolom('waktu_selesai') && $r->waktu_selesai) {
            $input['waktu_selesai'] = substr($r->waktu_selesai, 0, 5);
        }

        return redirect()->route('rapat.create')
            ->withInput($input)
            ->with('sukses', 'Data rapat disalin. Atur tanggal barunya lalu simpan atau kirim undangan.');
    }

    // ======================================================================
    //  BANTUAN
    // ======================================================================

    /**
     * Jadwal jeda (detik dari sekarang) untuk $jumlah pesan WA: jeda acak antar pesan
     * sesuai profil di config/wa.php, ditambah istirahat tiap per_batch pesan.
     * Pesan pertama langsung dijalankan (jeda 0).
     *
     * @return int[]
     */
    private function jadwalTunda(int $jumlah): array
    {
        $semua  = (array) config('wa.profil', []);
        $profil = (string) request()->input('jeda');
        if (!isset($semua[$profil])) {
            $profil = (string) config('wa.profil_bawaan', 'normal');
        }

        [$a, $b] = $semua[$profil] ?? [3, 3];
        $min = max(0, min($a, $b));
        $max = max($a, $b);

        $perBatch  = max(0, (int) config('wa.per_batch', 0));
        $jedaBatch = max(0, (int) config('wa.jeda_batch', 0));

        $hasil = [];
        $tunda = 0;
        for ($i = 0; $i < $jumlah; $i++) {
            if ($i > 0) {
                $tunda += random_int($min, $max);
                if ($perBatch > 0 && $i % $perBatch === 0) {
                    $tunda += $jedaBatch;
                }
            }
            $hasil[] = $tunda;
        }

        return $hasil;
    }

    /**
     * Pastikan tiap peserta punya baris di rapat_undangan, set ke PENDING,
     * lalu kembalikan id-id barisnya.
     */
    private function siapkanUndangan(int $idRapat, $peserta)
    {
        $nomor = $peserta->pluck('nomor_wa')->all();
        $now   = now();

        $ada = RapatUndangan::where('id_rapat', $idRapat)
            ->whereIn('nomor_wa', $nomor)->pluck('nomor_wa')->all();

        $baris = $peserta
            ->reject(fn ($p) => in_array($p['nomor_wa'], $ada, true))
            ->map(fn ($p) => [
                'id_rapat'   => $idRapat,
                'nomor_wa'   => $p['nomor_wa'],
                'nama'       => $p['nama'],
                'status'     => RapatUndangan::PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values();

        foreach ($baris->chunk(500) as $c) {
            RapatUndangan::insert($c->all());
        }

        // PENDING lagi agar job tidak melewatinya (job undangan berhenti bila status sudah TERKIRIM)
        RapatUndangan::where('id_rapat', $idRapat)->whereIn('nomor_wa', $nomor)
            ->update(['status' => RapatUndangan::PENDING, 'error' => null, 'updated_at' => $now]);

        return RapatUndangan::where('id_rapat', $idRapat)->whereIn('nomor_wa', $nomor)
            ->orderBy('id')->pluck('id');
    }

    /** Cari kelompok tersimpan yang anggotanya persis sama (disalin dari RapatController) */
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

    /** Buat kelompok otomatis untuk rapat yang anggotanya diubah */
    private function buatKelompokOtomatis(string $namaRapat, string $tanggal, $peserta): KelompokRapat
    {
        // akhiran "rev" agar nama tidak bentrok dengan kelompok otomatis saat rapat dibuat
        $nama = mb_substr(
            KelompokRapat::PREFIX_OTOMATIS . $namaRapat . ' (' . date('d-m-Y', strtotime($tanggal)) . ') rev '
                . now()->format('dHis'),
            0,
            100
        );

        $kelompok = KelompokRapat::buat($nama);
        KelompokRapat::isiAnggota($kelompok->id_kelompok, $nama, $peserta);

        return $kelompok;
    }

    /** Kelompok tersendiri per unit (dilewati bila sudah ada kelompok dengan anggota persis sama) */
    private function buatKelompokUnit(string $unit, string $namaRapat, string $tanggal, $peserta): void
    {
        if ($this->cariKelompokSama($peserta->pluck('nomor_wa')->all())) {
            return;
        }

        $nama = mb_substr(
            'Unit ' . $unit . ' - ' . $namaRapat . ' (' . date('d-m-Y', strtotime($tanggal)) . ')',
            0,
            100
        );

        $kelompok = KelompokRapat::buat($nama);
        KelompokRapat::isiAnggota($kelompok->id_kelompok, $nama, $peserta);
    }

    /** Query dasar dengan semua filter, kecuali status */
    private function dasar(Request $request, ?string $dari, ?string $sampai, bool $saya = false)
    {
        // Rapat Saya: hanya milik user login. Jadwal global (Super Admin): semua.
        $q = $saya ? Rapat::milikSaya() : Rapat::terlihat();

        if ($dari)   { $q->where('rapat.tanggal', '>=', $dari); }
        if ($sampai) { $q->where('rapat.tanggal', '<=', $sampai); }

        if ($tempat = (int) $request->query('tempat')) {
            $q->where('rapat.id_tempat', $tempat);
        }

        if ($unit = trim((string) $request->query('unit'))) {
            $q->whereRaw(sprintf(self::UNIT_ADA, 'p.nama_unit = ?'), [$unit]);
        }

        if ($kata = trim((string) $request->query('q'))) {
            $like = '%' . addcslashes($kata, '%_\\') . '%';
            $q->where(function ($w) use ($like) {
                $w->where('rapat.nama_rapat', 'like', $like)
                  ->orWhere('rapat.catatan_rapat', 'like', $like)
                  ->orWhere('rapat.nama_kelompok', 'like', $like)
                  ->orWhere('rapat.tempat_rapat', 'like', $like)
                  ->orWhere('rapat.pengundang', 'like', $like)
                  ->orWhereRaw(sprintf(self::UNIT_ADA, 'p.nama_unit LIKE ?'), [$like]);
            });
        }

        return $q;
    }

    /** Satu rapat + status_tampil */
    private function satu(int $id): Rapat
    {
        $caseSql = $this->ekspresiStatus(Rapat::punyaKolom('waktu_selesai'));

        // terlihat(): rapat milik user lain = 404 (berlaku untuk detail, ubah, batal, selesai, aktifkan)
        return Rapat::query()
            ->terlihat()
            ->selectRaw("rapat.*, ($caseSql) AS status_tampil")
            ->where('rapat.id_rapat', $id)
            ->firstOrFail();
    }

    /**
     * Ekspresi SQL yang mengubah nilai kolom status + tanggal/jam menjadi:
     * mendatang | berlangsung | selesai | terlewat | tertunda | draft | dibatalkan | lainnya
     */
    private function ekspresiStatus(bool $punyaSelesai): string
    {
        $hari  = now()->toDateString();   // aman: berasal dari PHP, bukan input pengguna
        $jam   = now()->format('H:i:s');
        $akhir = $punyaSelesai
            ? "COALESCE(rapat.waktu_selesai, ADDTIME(rapat.waktu, '02:00:00'))"
            : "ADDTIME(rapat.waktu, '02:00:00')";

        return "CASE
            WHEN LOWER(rapat.status) LIKE '%batal%'  THEN 'dibatalkan'
            WHEN LOWER(rapat.status) LIKE '%tunda%'  THEN 'tertunda'
            WHEN LOWER(rapat.status) = 'draft'       THEN 'draft'
            WHEN LOWER(rapat.status) LIKE '%belum%' OR LOWER(rapat.status) LIKE '%jadwal%' THEN
                CASE
                    WHEN rapat.tanggal > '$hari' THEN 'mendatang'
                    WHEN rapat.tanggal < '$hari' THEN 'terlewat'
                    WHEN '$jam' < rapat.waktu    THEN 'mendatang'
                    WHEN '$jam' <= $akhir        THEN 'berlangsung'
                    ELSE 'terlewat'
                END
            WHEN LOWER(rapat.status) LIKE '%laksana%' OR LOWER(rapat.status) LIKE '%selesai%' THEN 'selesai'
            WHEN LOWER(rapat.status) LIKE '%berlangsung%' THEN 'berlangsung'
            ELSE 'lainnya'
        END";
    }

    private function tanggalValid($nilai): ?string
    {
        $nilai = (string) $nilai;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $nilai;
        }
        return null;
    }
}