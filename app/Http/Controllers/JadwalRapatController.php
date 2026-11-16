<?php

namespace App\Http\Controllers;

use App\Models\KelompokRapatDetail;
use App\Models\Rapat;
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
        Carbon::setLocale('id');

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

        $dasar = $this->dasar($request, $dari, $sampai);

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
                'n' => $n, 'url' => route('rapat.jadwal', $param),
            ];
        }

        // ----- pilihan filter -----
        $units = DB::table('unit_pegawai')->orderBy('nama_unit')->pluck('nama_unit');
        $tempatList = TempatRapat::orderBy('nama_tempat')->get(['id_tempat', 'nama_tempat']);

        $fmt = fn ($t) => Carbon::parse($t)->translatedFormat('d M Y');
        $teksRentang = ($dari && $sampai) ? $fmt($dari) . ' – ' . $fmt($sampai)
            : ($dari ? 'Mulai ' . $fmt($dari) : ($sampai ? 'Sampai ' . $fmt($sampai) : ''));

        $konfig = [
            'urlShow'      => route('rapat.show', '__ID__'),
            'punyaSelesai' => $punyaSelesai,
        ];

        return view('rapat.jadwal', compact(
            'rapat', 'tab', 'status', 'units', 'tempatList',
            'dari', 'sampai', 'semua', 'terbatas', 'teksRentang', 'konfig', 'punyaSelesai'
        ));
    }

    // ======================================================================
    //  DETAIL (JSON) untuk dialog "lihat" dan "ubah"
    // ======================================================================
    public function show(int $id)
    {
        Carbon::setLocale('id');
        $r = $this->satu($id);

        $anggota = KelompokRapatDetail::where('id_kelompok', $r->id_kelompok)
            ->orderBy('nama_lengkap')
            ->get(['nama_lengkap', 'nomor_wa'])
            ->map(fn ($d) => ['nama' => $d->nama_lengkap, 'nomor_wa' => $d->nomor_wa])
            ->values();

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
    //  UBAH rapat (JSON)
    // ======================================================================
    public function update(Request $request, int $id)
    {
        $r = $this->satu($id);

        if (!in_array($r->status_tampil, self::BISA_UBAH, true)) {
            return response()->json(['message' => 'Rapat dengan status ini tidak dapat diubah.'], 422);
        }

        $data = $request->validate([
            'nama_rapat'    => 'required|string|max:100',
            'catatan_rapat' => 'nullable|string|max:255',
            'tanggal'       => 'required|date',
            'waktu'         => 'required|date_format:H:i',
            'waktu_selesai' => 'nullable|date_format:H:i|after:waktu',
            'id_tempat'     => 'required|exists:tempat_rapat,id_tempat',
        ], [
            'required'    => ':attribute wajib diisi.',
            'exists'      => ':attribute tidak ditemukan.',
            'date'        => ':attribute tidak valid.',
            'date_format' => ':attribute tidak valid.',
            'after'       => 'Jam selesai harus setelah jam mulai.',
            'max'         => ':attribute terlalu panjang.',
        ], [
            'nama_rapat'    => 'Nama rapat',
            'catatan_rapat' => 'Agenda / topik',
            'tanggal'       => 'Tanggal',
            'waktu'         => 'Waktu mulai',
            'id_tempat'     => 'Tempat rapat',
        ]);

        $tempat  = TempatRapat::findOrFail($data['id_tempat']);
        $selesai = Rapat::punyaKolom('waktu_selesai') ? ($data['waktu_selesai'] ?? null) : null;

        if (Rapat::bentrok($tempat->id_tempat, $data['tanggal'], $data['waktu'], $selesai, $id)) {
            return response()->json(['message' => 'Tempat sudah dipakai rapat lain pada tanggal dan jam tersebut.'], 422);
        }

        $jadwalBerubah = $r->tanggal->toDateString() !== $data['tanggal']
            || substr($r->waktu, 0, 5) !== $data['waktu'];

        $baru = [
            'nama_rapat'    => $data['nama_rapat'],
            'catatan_rapat' => $data['catatan_rapat'] ?? '',
            'tanggal'       => $data['tanggal'],
            'waktu'         => $data['waktu'] . ':00',
            'tempat_rapat'  => $tempat->nama_tempat,
            'id_tempat'     => $tempat->id_tempat,
        ];
        if (Rapat::punyaKolom('waktu_selesai')) {
            $baru['waktu_selesai'] = $selesai ? $selesai . ':00' : null;
        }
        if ($jadwalBerubah) {
            // jadwal diubah -> pengingat WhatsApp perlu dikirim ulang
            $baru['ingat_rapat']   = 0;
            $baru['ingat_rapat_2'] = 0;
        }

        Rapat::where('id_rapat', $id)->update($baru);
        session()->flash('sukses', 'Rapat berhasil diperbarui.');

        return response()->json(['pesan' => 'Rapat berhasil diperbarui.']);
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
        $r = Rapat::findOrFail($id);

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

    /** Query dasar dengan semua filter, kecuali status */
    private function dasar(Request $request, ?string $dari, ?string $sampai)
    {
        $q = Rapat::query();

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

        return Rapat::query()
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