@extends('layouts.app')

@section('title', 'Buat Rapat / Undangan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rapat.css') }}?v={{ @filemtime(public_path('css/rapat.css')) }}">
    <style>
        /* ===== Pemilih waktu (mulai - selesai) ===== */
        .wk { --wk-pagi:#0f8b8d; --wk-siang:#d98e04; --wk-garis:rgba(120,130,150,.28); --wk-aksen:#2f6fed; }
        .wk-label { display:block; margin-bottom:6px; font-weight:600; }
        .wk-baris { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:8px 10px; align-items:end; }
        .wk-kolom small { display:block; margin-bottom:3px; opacity:.7; font-size:.78rem; }
        .wk-pilih { position:relative; }
        .wk-pilih svg.i { position:absolute; left:9px; width:15px; height:15px; top:50%; transform:translateY(-50%); pointer-events:none; opacity:.65; }
        .wk-pilih select, .wk-pilih input {
            box-sizing:border-box; width:100%; height:36px; padding:0 8px 0 30px; border:1px solid var(--wk-garis);
            border-radius:7px; background:transparent; color:inherit; font:inherit;
            font-variant-numeric:tabular-nums; font-size:.92rem; cursor:pointer;
        }
        .wk-pilih select:focus-visible, .wk-pilih input:focus-visible { outline:2px solid var(--wk-aksen); outline-offset:1px; }
        .wk-pilih select option:disabled { color:#9aa3b2; }
        .wk-sampai { padding-bottom:11px; opacity:.55; }

        .wk-preset { display:flex; flex-wrap:wrap; gap:6px; margin:8px 0 2px; }
        .wk-chip {
            padding:3px 10px; border:1px solid var(--wk-garis); border-radius:999px;
            background:transparent; color:inherit; font:inherit; font-size:.78rem; cursor:pointer;
        }
        .wk-chip:hover { border-color:var(--wk-aksen); }
        .wk-chip.aktif { background:var(--wk-aksen); border-color:var(--wk-aksen); color:#fff; }
        .wk-chip:focus-visible { outline:2px solid var(--wk-aksen); outline-offset:2px; }

        .wk-garis { margin:10px 2px 2px; }
        .wk-jalur { position:relative; height:8px; border-radius:4px; background:var(--wk-garis); }
        .wk-isi {
            position:absolute; top:0; bottom:0; left:0; width:0; border-radius:4px;
            background:var(--wk-pagi); transition:left .2s ease, width .2s ease, background .2s ease;
        }
        .wk-isi.siang { background:var(--wk-siang); }
        .wk-isi.fullday { background:linear-gradient(90deg, var(--wk-pagi) 0, var(--wk-pagi) 40%, var(--wk-siang) 62%); }
        .wk-batas { position:absolute; top:-5px; bottom:-5px; left:62.5%; width:0; border-left:2px dashed rgba(90,100,120,.6); }
        .wk-skala { position:relative; height:16px; margin-top:4px; font-size:.7rem; opacity:.75; font-variant-numeric:tabular-nums; }
        .wk-skala span { position:absolute; transform:translateX(-50%); white-space:nowrap; }
        .wk-skala span:first-child { transform:none; }
        .wk-skala span:last-child { transform:translateX(-100%); }

        .wk-ringkas { display:flex; flex-wrap:wrap; align-items:center; gap:6px 10px; margin-top:6px; }
        .wk-ringkas .bantu-inline { opacity:.8; }
        .wk-hint { margin:4px 0 0; font-size:.76rem; opacity:.65; }
        .wk-tgl { margin-top:6px; font-size:.85rem; opacity:.8; }

        /* ===== Input tanggal ===== */
        .wk-tanggal { position:relative; }
        .wk-tanggal svg.i { position:absolute; left:11px; top:50%; transform:translateY(-50%); pointer-events:none; opacity:.65; }
        .wk-tanggal input {
            width:100%; height:44px; padding:0 12px 0 36px; border:1px solid var(--wk-garis);
            border-radius:8px; background:transparent; color:inherit; font:inherit;
        }
        .wk-tanggal input:focus-visible { outline:2px solid var(--wk-aksen); outline-offset:1px; }

        /* ===== Ringkasan rapat ===== */
        .ringkasan-rapat {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:6px 16px; margin:12px 0 0; padding:8px 12px;
            border:1px solid var(--wk-garis); border-radius:8px; font-size:.85rem;
        }
        .ringkasan-rapat div { min-width:0; }
        .ringkasan-rapat dt { font-size:.72rem; opacity:.65; margin:0 0 1px; }
        .ringkasan-rapat dd { margin:0; font-weight:600; font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }

        .sembunyi-visual { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }

        /* ===== Tata letak ringkas: lebar dibatasi & kolom menyesuaikan layar ===== */
        .rapat-wrap .isi-panel label[for] { font-size:.86rem; margin:8px 0 3px; }
        .rapat-wrap .isi-panel input[type=text],
        .rapat-wrap .isi-panel input[type=url],
        .rapat-wrap .isi-panel textarea,
        .rapat-wrap .isi-panel select { font-size:.9rem; padding-top:6px; padding-bottom:6px; }
        .rapat-wrap .isi-panel .wk-pilih select,
        .rapat-wrap .isi-panel .wk-pilih input { padding-top:0; padding-bottom:0; font-size:.92rem; }
        .rapat-wrap textarea { min-height:0; resize:vertical; }
        .rapat-wrap .aksi-bar { margin-top:12px; }
        .rapat-wrap .panel-plain { margin-top:14px; }
        .rapat-wrap .tempat-metode { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:4px 14px; align-items:end; }
        .rapat-wrap .tempat-metode .baris-inline { margin-top:8px; }

        /* ===== Tanggal / Mulai / Selesai: dibuat kecil (menimpa gaya global rapat.css) ===== */
        .rapat-wrap .wk .wk-baris { grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:6px 10px; }
        .rapat-wrap .wk .wk-kolom small { font-size:.72rem; margin-bottom:2px; }
        .rapat-wrap .wk .wk-pilih select,
        .rapat-wrap .wk .wk-pilih input {
            height:28px !important; min-height:0 !important; padding:0 4px 0 24px !important;
            font-size:.8rem !important; line-height:1.2 !important; border-radius:6px !important; margin:0 !important;
        }
        .rapat-wrap .wk .wk-pilih svg.i {
            left:7px !important; top:50% !important; margin:0 !important;
            width:12px !important; height:12px !important; transform:translateY(-50%) !important;
        }
        .rapat-wrap .wk .wk-chip { padding:2px 8px; font-size:.72rem; }
        .rapat-wrap .wk .wk-label { font-size:.86rem; margin-bottom:4px; }
        @@media (prefers-reduced-motion:reduce) { .wk-isi { transition:none; } }
    </style>
@endpush

@section('page_head')
    <div class="rapat-head">
        <svg class="i ikon-besar"><use href="#i-kalender-plus"/></svg>
        <div>
            <h1>Buat Rapat / Undangan</h1>
            <p class="sub">Buat undangan rapat, pilih peserta (pegawai/kelompok), atur jadwal, dan kirim undangan melalui WhatsApp.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="rapat-wrap">

    @php
        // Jam operasional rapat (sama dengan konstanta di RapatController)
        $jamBuka    = '07:00';
        $jamTutup   = '15:00';
        // Penentuan sesi otomatis:
        //   selesai <= batas  -> Sesi Pagi
        //   mulai   >= batas  -> Sesi Siang
        //   selain itu        -> Fullday (melewati batas)
        $batasSiang = '12:00';
        $langkah    = 15; // interval pilihan waktu (menit)

        $keMenit = fn ($t) => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
        $keJam   = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        $bukaM  = $keMenit($jamBuka);
        $tutupM = $keMenit($jamTutup);

        $slotMulai = $slotSelesai = [];
        for ($m = $bukaM; $m < $tutupM; $m += $langkah)          { $slotMulai[]   = $keJam($m); }
        for ($m = $bukaM + $langkah; $m <= $tutupM; $m += $langkah) { $slotSelesai[] = $keJam($m); }

        // Bawaan: Fullday 07:00 - 15:00
        $wMulai   = substr((string) old('waktu', $jamBuka), 0, 5);
        $wSelesai = substr((string) old('waktu_selesai', $jamTutup), 0, 5);

        // Jaga-jaga bila nilai lama tidak persis di kelipatan interval
        if (preg_match('/^\d{2}:\d{2}$/', $wMulai) && !in_array($wMulai, $slotMulai, true)
            && $keMenit($wMulai) >= $bukaM && $keMenit($wMulai) < $tutupM) {
            $slotMulai[] = $wMulai; sort($slotMulai);
        }
        if (preg_match('/^\d{2}:\d{2}$/', $wSelesai) && !in_array($wSelesai, $slotSelesai, true)
            && $keMenit($wSelesai) > $bukaM && $keMenit($wSelesai) <= $tutupM) {
            $slotSelesai[] = $wSelesai; sort($slotSelesai);
        }
    @endphp

    @if ($errors->any())
        <div class="alert gagal" role="alert">
            <ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('rapat.store') }}" id="formRapat" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="peserta_json" id="pesertaJson" value="{{ old('peserta_json') }}">

        <div class="grid-dua">

            {{-- ================= DETAIL RAPAT ================= --}}
            <section class="panel">
                <h3 class="judul-panel"><svg class="i"><use href="#i-edit"/></svg> Detail Rapat</h3>

                <div class="isi-panel">
                    <label for="nama_rapat">Nama Rapat <span class="wajib">*</span></label>
                    <input type="text" id="nama_rapat" name="nama_rapat" maxlength="100" required
                           value="{{ old('nama_rapat') }}" placeholder="Contoh: Rapat Koordinasi Program SDM 2027">

                    <label for="catatan_rapat">Agenda / Topik</label>
                    <textarea id="catatan_rapat" name="catatan_rapat" rows="2" maxlength="255"
                              placeholder="Pembahasan rapat">{{ old('catatan_rapat') }}</textarea>

                    {{-- ===== Jadwal: tanggal, mulai, selesai dalam satu baris ===== --}}
                    <div class="wk" style="margin-top:8px">
                        <span class="wk-label" id="labelWaktu">Jadwal Rapat <span class="wajib">*</span></span>
                        <span class="sembunyi-visual" id="teksTanggal">—</span>
                        {{-- elemen lama; dipertahankan agar skrip lain tidak error --}}
                        <span id="teksWaktu" class="sembunyi-visual" aria-hidden="true">—</span>

                        <div class="wk-baris" role="group" aria-labelledby="labelWaktu">
                            <div class="wk-kolom">
                                <small>Tanggal</small>
                                <div class="wk-pilih">
                                    <svg class="i"><use href="#i-kalender"/></svg>
                                    <input type="date" id="tanggal" name="tanggal" required
                                           min="{{ now()->toDateString() }}" aria-label="Tanggal rapat"
                                           value="{{ old('tanggal', now()->toDateString()) }}">
                                </div>
                            </div>
                            <div class="wk-kolom">
                                <small>Mulai</small>
                                <div class="wk-pilih">
                                    <svg class="i"><use href="#i-jam"/></svg>
                                    <select id="waktu" name="waktu" required aria-label="Waktu mulai">
                                        @foreach ($slotMulai as $s)
                                            <option value="{{ $s }}" @selected($s === $wMulai)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @if ($punya['selesai'])
                                <div class="wk-kolom">
                                    <small>Selesai</small>
                                    <div class="wk-pilih">
                                        <svg class="i"><use href="#i-jam"/></svg>
                                        <select id="waktu_selesai" name="waktu_selesai" required aria-label="Waktu selesai">
                                            @foreach ($slotSelesai as $s)
                                                <option value="{{ $s }}" @selected($s === $wSelesai)>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($punya['selesai'])
                            <div class="wk-preset" role="group" aria-label="Pilihan cepat">
                                <button type="button" class="wk-chip" data-mulai="07:00" data-selesai="12:00">Pagi 07:00–12:00</button>
                                <button type="button" class="wk-chip" data-mulai="13:00" data-selesai="15:00">Siang 13:00–15:00</button>
                                <button type="button" class="wk-chip" data-mulai="07:00" data-selesai="15:00">Fullday 07:00–15:00</button>
                            </div>
                        @endif

                        {{-- Garis waktu visual --}}
                        <div class="wk-garis" aria-hidden="true">
                            <div class="wk-jalur">
                                <div class="wk-isi" id="garisIsi"></div>
                                <span class="wk-batas"></span>
                            </div>
                            <div class="wk-skala">
                                <span style="left:0">07:00</span>
                                <span style="left:25%">09:00</span>
                                <span style="left:62.5%">12:00</span>
                                <span style="left:100%">15:00</span>
                            </div>
                        </div>

                        {{-- Sesi ditentukan otomatis dari jam mulai & selesai --}}
                        <div class="wk-ringkas">
                            <label class="judul" style="margin:0">Sesi Rapat</label>
                            <span id="badgeSesi" class="badge-sesi" aria-live="polite">—</span>
                            <span class="bantu-inline" id="infoSesi"></span>
                        </div>
                        <p class="wk-hint">
                            Jam rapat {{ $jamBuka }}–{{ $jamTutup }}. Pagi: selesai paling lambat {{ $batasSiang }}. Siang: mulai {{ $batasSiang }} atau lebih. Fullday: melewati {{ $batasSiang }}.
                        </p>
                    </div>

                    <div class="tempat-metode">
                    <div>
                    <label for="id_tempat">Tempat Rapat <span class="wajib">*</span></label>
                    <div class="baris-tempat">
                        <div class="ikon-input">
                            <svg class="i"><use href="#i-ruang"/></svg>
                            <select id="id_tempat" name="id_tempat" required>
                                <option value="">— Pilih tempat —</option>
                                @foreach ($tempat as $t)
                                    <option value="{{ $t->id_tempat }}" @selected(old('id_tempat') == $t->id_tempat)>
                                        {{ $t->nama_tempat }}{{ $t->gedung ? ' (' . $t->gedung . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <span id="badgeTempat" class="badge-tempat"></span>
                    </div>
                    </div>

                    <div class="baris-inline">
                        <label class="judul">Metode Rapat</label>
                        <label class="radio">
                            <input type="radio" name="metode" value="tatap_muka" @checked(old('metode', 'tatap_muka') === 'tatap_muka')>
                            <svg class="i ikon-biru"><use href="#i-users"/></svg> Tatap Muka
                        </label>
                        <label class="radio">
                            <input type="radio" name="metode" value="daring" @checked(old('metode') === 'daring')>
                            <svg class="i"><use href="#i-ruang"/></svg> Daring (Online)
                        </label>
                    </div>
                    </div>

                    <div id="barisLink" hidden>
                        <label for="link_rapat">Link Rapat Online</label>
                        <input type="url" id="link_rapat" name="link_rapat" value="{{ old('link_rapat') }}"
                               placeholder="https://meet.google.com/... atau https://zoom.us/...">
                    </div>

                    @if ($punya['lampiran'])
                        <div class="baris-inline">
                            <label class="judul">Lampiran (Opsional)</label>
                            <label class="btn-file">
                                <input type="file" id="lampiran" name="lampiran" accept=".pdf,.doc,.docx" class="sembunyi">
                                <svg class="i"><use href="#i-klip"/></svg> <span id="namaFile">Pilih File</span>
                            </label>
                            <span class="bantu-inline">Undangan, TOR, atau dokumen pendukung (PDF/DOC)</span>
                        </div>
                    @endif
                </div>
            </section>

            {{-- ================= PESERTA RAPAT ================= --}}
            <section class="panel">
                <h3 class="judul-panel"><svg class="i"><use href="#i-users"/></svg> Peserta Rapat</h3>

                <div class="isi-panel">
                    <div class="tab-peserta-bar">
                        <button type="button" class="tab-peserta aktif" data-tab="pilih">
                            <svg class="i"><use href="#i-users"/></svg> Pilih Peserta
                        </button>
                        <button type="button" class="tab-peserta" data-tab="kelompok">
                            <svg class="i"><use href="#i-layers"/></svg> Buat Kelompok
                        </button>
                    </div>

                    {{-- Tab: Pilih Peserta --}}
                    <div id="tabPilih" class="isi-tab">
                        <div class="kotak-pilih">
                            <div class="kotak-cari">
                                <svg class="i"><use href="#i-cari"/></svg>
                                <input type="search" id="cariPegawai" placeholder="Cari pegawai (nama, NIK, unit, atau jabatan)..." autocomplete="off">
                                <div id="hasilCari" class="hasil-cari" hidden></div>
                            </div>

                            <div class="acc" data-jenis="unit">
                                <div class="acc-kepala">
                                    <label class="acc-judul"><input type="checkbox" class="acc-semua"> <svg class="i ikon-biru"><use href="#i-gedung"/></svg> Direktorat / Unit</label>
                                    <button type="button" class="acc-panah" aria-expanded="false" aria-label="Buka daftar unit"><svg class="i"><use href="#i-chevron"/></svg></button>
                                </div>
                                <div class="acc-isi" hidden>
                                    @forelse ($units as $u)
                                        <label class="acc-item"><input type="checkbox" class="acc-cek" data-jenis="unit" data-nilai="{{ $u }}"> {{ $u }}</label>
                                    @empty
                                        <span class="kosong-kecil">Belum ada data unit.</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="acc" data-jenis="jabatan">
                                <div class="acc-kepala">
                                    <label class="acc-judul"><input type="checkbox" class="acc-semua"> <svg class="i ikon-biru"><use href="#i-users"/></svg> Jabatan</label>
                                    <button type="button" class="acc-panah" aria-expanded="false" aria-label="Buka daftar jabatan"><svg class="i"><use href="#i-chevron"/></svg></button>
                                </div>
                                <div class="acc-isi" hidden>
                                    @forelse ($jabatan as $j)
                                        <label class="acc-item"><input type="checkbox" class="acc-cek" data-jenis="jabatan" data-nilai="{{ $j }}"> {{ $j }}</label>
                                    @empty
                                        <span class="kosong-kecil">Belum ada data jabatan.</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="acc" data-jenis="kelompok">
                                <div class="acc-kepala">
                                    <label class="acc-judul"><input type="checkbox" class="acc-semua"> <svg class="i ikon-biru"><use href="#i-users"/></svg> Kelompok Rapat <small>(Tersimpan)</small></label>
                                    <button type="button" class="acc-panah" aria-expanded="false" aria-label="Buka daftar kelompok"><svg class="i"><use href="#i-chevron"/></svg></button>
                                </div>
                                <div class="acc-isi" id="isiKelompok" hidden></div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab: Buat Kelompok --}}
                    <div id="tabKelompok" class="isi-tab" hidden>
                        <div class="kotak-pilih">
                            <p class="bantu">Simpan peserta yang sedang terpilih sebagai kelompok, agar bisa dipakai lagi di rapat berikutnya.</p>
                            <label for="namaKelompok">Nama Kelompok</label>
                            <input type="text" id="namaKelompok" maxlength="100" placeholder="Contoh: Tim Verifikasi RKA">
                            <p class="bantu">Anggota: <b id="jumlahAnggota">0 orang</b> (dari Peserta Terpilih di bawah). Pilih peserta lewat tab "Pilih Peserta".</p>
                            <div class="aksi-kelompok">
                                <button type="button" class="btn btn-biru" id="btnSimpanKelompok">Simpan Kelompok</button>
                            </div>
                        </div>
                    </div>

                    <div class="peserta-terpilih">
                        <div class="peserta-judul" id="judulPeserta">Peserta Terpilih (0 orang)</div>
                        <div class="peserta-chip" id="chipPeserta"></div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ================= RINGKASAN ================= --}}
        <dl class="ringkasan-rapat" aria-label="Ringkasan rapat">
            <div><dt>Hari &amp; tanggal</dt><dd id="rgTanggal">—</dd></div>
            <div><dt>Waktu</dt><dd id="rgWaktu">—</dd></div>
            <div><dt>Sesi</dt><dd id="rgSesi">—</dd></div>
            <div><dt>Tempat</dt><dd id="rgTempat">Belum dipilih</dd></div>
            <div><dt>Peserta</dt><dd id="rgPeserta">0 orang</dd></div>
        </dl>

        <div class="aksi-bar">
            <button type="reset" class="btn"><svg class="i"><use href="#i-ulang"/></svg> Reset</button>
            <span class="spasi"></span>
            <button type="submit" name="aksi" value="draft" class="btn"><svg class="i"><use href="#i-simpan"/></svg> Simpan Draft</button>
            <button type="submit" name="aksi" value="kirim" class="btn btn-hijau btn-kirim"><svg class="i"><use href="#i-wa"/></svg> Kirim Undangan (WA)</button>
        </div>
    </form>

    {{-- ================= STATUS PENGIRIMAN WA ================= --}}
    <section class="panel panel-plain" id="panelStatusWa">
        <div class="kepala-plain">
            <h3><svg class="i"><use href="#i-wa"/></svg> Status Pengiriman Undangan <small>(WhatsApp)</small></h3>
            <div class="aksi-status">
                <select id="pilihRapatWa" aria-label="Pilih rapat" @disabled($daftarRapat->isEmpty())>
                    @forelse ($daftarRapat as $r)
                        <option value="{{ $r->getKey() }}" @selected($rapatAktif == $r->getKey())>
                            {{ $r->nama_rapat }} — {{ \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') }}
                        </option>
                    @empty
                        <option value="">Belum ada undangan terkirim</option>
                    @endforelse
                </select>
                <select id="filterStatusWa" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="pending">Pending</option>
                    <option value="terkirim">Terkirim</option>
                    <option value="gagal">Gagal</option>
                </select>
                <button type="button" class="btn btn-biru" id="btnUlangSemua" hidden>
                    <svg class="i"><use href="#i-ulang"/></svg> Kirim Ulang yang Gagal
                </button>
            </div>
        </div>

        <div class="ringkas-wa">
            <span class="status-wa">Total: <b id="wa-total">0</b></span>
            <span class="status-wa terkirim">Terkirim: <b id="wa-terkirim">0</b></span>
            <span class="status-wa pending">Pending: <b id="wa-pending">0</b></span>
            <span class="status-wa gagal">Gagal: <b id="wa-gagal">0</b></span>
        </div>

        <div class="tabel-scroll">
            <table class="tabel-polos tabel-kel">
                <thead>
                    <tr>
                        <th style="width:56px">No</th><th>Nama</th><th>Nomor WA</th>
                        <th class="c">Status</th><th>Waktu Kirim</th><th>Keterangan</th><th class="c">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbodyStatusWa">
                    <tr><td colspan="7" class="kosong">Belum ada data pengiriman.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <div id="toast" class="toast" role="status" hidden></div>
</div>
@endsection

@push('scripts')
    <script>
        window.SIUPIN = @json($konfig);
    </script>
    <script src="{{ asset('js/buat-rapat.js') }}?v={{ @filemtime(public_path('js/buat-rapat.js')) }}"></script>

    {{-- Pemilih waktu, penentuan sesi otomatis, garis waktu, dan ringkasan --}}
    <script>
        (function () {
            var BUKA  = @json($jamBuka);
            var TUTUP = @json($jamTutup);
            var BATAS = @json($batasSiang);

            var form    = document.getElementById('formRapat');
            var tanggal = document.getElementById('tanggal');
            var mulai   = document.getElementById('waktu');
            var selesai = document.getElementById('waktu_selesai'); // null bila kolom tidak ada
            var tempat  = document.getElementById('id_tempat');
            var badge   = document.getElementById('badgeSesi');
            var info    = document.getElementById('infoSesi');
            var isi     = document.getElementById('garisIsi');
            var teksTgl = document.getElementById('teksTanggal');
            var judulPs = document.getElementById('judulPeserta');
            var chips   = document.querySelectorAll('.wk-chip');

            var rg = {
                tanggal: document.getElementById('rgTanggal'),
                waktu:   document.getElementById('rgWaktu'),
                sesi:    document.getElementById('rgSesi'),
                tempat:  document.getElementById('rgTempat'),
                peserta: document.getElementById('rgPeserta')
            };

            var LABEL = { pagi: 'Sesi Pagi', siang: 'Sesi Siang', fullday: 'Fullday' };

            function menit(t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1]); }

            function durasi(m, s) {
                var d = menit(s) - menit(m), j = Math.floor(d / 60), mn = d % 60;
                return (j ? j + ' jam' : '') + (j && mn ? ' ' : '') + (mn ? mn + ' menit' : '');
            }

            // Mengembalikan: 'pagi' | 'siang' | 'fullday' | ''
            function tentukan(m, s) {
                if (!m) return '';
                if (!s) return m >= BATAS ? 'siang' : 'pagi';
                if (s <= m) return '';
                if (s <= BATAS) return 'pagi';
                if (m >= BATAS) return 'siang';
                return 'fullday';
            }

            // Jam selesai yang <= jam mulai tidak boleh dipilih
            function aturOpsiSelesai() {
                if (!selesai) return;
                var m = mulai.value, opsi = selesai.options, i;
                for (i = 0; i < opsi.length; i++) opsi[i].disabled = m && opsi[i].value <= m;

                if (m && (!selesai.value || selesai.value <= m)) {
                    // Geser otomatis: mulai + 1 jam, mentok di jam tutup
                    var target = Math.min(menit(m) + 60, menit(TUTUP));
                    for (i = 0; i < opsi.length; i++) {
                        if (!opsi[i].disabled && menit(opsi[i].value) >= target) { selesai.value = opsi[i].value; break; }
                    }
                    if (selesai.value <= m) {
                        for (i = opsi.length - 1; i >= 0; i--) if (!opsi[i].disabled) { selesai.value = opsi[i].value; break; }
                    }
                }
            }

            function gambarGaris(m, s, k) {
                var total = menit(TUTUP) - menit(BUKA);
                var a = Math.max(0, menit(m) - menit(BUKA));
                var b = s ? Math.min(total, menit(s) - menit(BUKA)) : a + 8;
                isi.style.left  = (a / total * 100) + '%';
                isi.style.width = (Math.max(b - a, 0) / total * 100) + '%';
                isi.className = 'wk-isi' + (k ? ' ' + k : '');
            }

            function formatTanggal(v) {
                if (!v) return '';
                var p = v.split('-');
                var d = new Date(+p[0], +p[1] - 1, +p[2]);
                if (isNaN(d)) return '';
                return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            }

            function segarkan() {
                aturOpsiSelesai();

                var m = mulai.value;
                var s = selesai ? selesai.value : '';
                var k = tentukan(m, s);

                // validasi tambahan (cadangan; server tetap memeriksa)
                if (selesai) selesai.setCustomValidity(s && m && s <= m ? 'Waktu selesai harus setelah waktu mulai.' : '');

                // badge sesi (kelas 'seharian' dipertahankan sebagai alias untuk CSS lama)
                badge.className = 'badge-sesi' + (k ? ' ' + k : '') + (k === 'fullday' ? ' seharian' : '');
                badge.textContent = k ? LABEL[k] : '—';
                info.textContent = (k && s) ? (m + ' sampai ' + s + ', durasi ' + durasi(m, s)) : '';

                // garis waktu + preset aktif
                gambarGaris(m, s, k);
                chips.forEach(function (c) {
                    c.classList.toggle('aktif', c.dataset.mulai === m && c.dataset.selesai === s);
                });

                // teks tanggal & ringkasan
                var tgl = formatTanggal(tanggal.value);
                teksTgl.textContent = tgl || '—';
                document.getElementById('teksWaktu').textContent = s ? m + ' – ' + s : m;

                rg.tanggal.textContent = tgl || '—';
                rg.waktu.textContent   = s ? m + ' – ' + s : (m || '—');
                rg.sesi.textContent    = k ? LABEL[k] : '—';
                rg.tempat.textContent  = tempat.value ? tempat.options[tempat.selectedIndex].text.trim() : 'Belum dipilih';
            }

            function segarkanPeserta() {
                var cocok = (judulPs.textContent || '').match(/\((\d+)\s*orang\)/);
                rg.peserta.textContent = (cocok ? cocok[1] : '0') + ' orang';
            }

            // Pilihan cepat
            chips.forEach(function (c) {
                c.addEventListener('click', function () {
                    mulai.value = c.dataset.mulai;
                    if (selesai) { aturOpsiSelesai(); selesai.value = c.dataset.selesai; }
                    segarkan();
                });
            });

            [tanggal, mulai, selesai, tempat].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', segarkan);
                el.addEventListener('change', segarkan);
            });

            if (window.MutationObserver) {
                new MutationObserver(segarkanPeserta).observe(judulPs, { childList: true, characterData: true, subtree: true });
            }

            // Reset mengembalikan nilai bawaan form (07:00 - 15:00 = Fullday)
            form.addEventListener('reset', function () { setTimeout(function () { segarkan(); segarkanPeserta(); }, 0); });

            segarkan();
            segarkanPeserta();
        })();
    </script>
@endpush