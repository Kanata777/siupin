@extends('layouts.app')

@section('title', 'Kelompok Rapat')
@section('body_class', 'halaman-penuh')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/kelompok.css') }}?v={{ @filemtime(public_path('css/kelompok.css')) }}">
@endpush

@section('page_head')
    <div class="kel-head">
        <svg class="i ikon-besar"><use href="#i-users"/></svg>
        <div>
            <h1>Kelompok Rapat</h1>
            <p class="sub">Kelola daftar kelompok peserta rapat untuk memudahkan pembuatan undangan.</p>
        </div>
    </div>
@endsection

@section('content')
    {{-- ===== Tab perpindahan halaman (setara) ===== --}}
    <div class="kel-tab" role="tablist">
        <a href="{{ route('kelompok.index', array_filter(['pilih' => $pilih ?: null])) }}" data-hal="daftar"
           class="{{ $hal === 'daftar' ? 'aktif' : '' }}" role="tab">
            <svg class="i"><use href="#i-berkas"/></svg> Daftar Kelompok
            <span class="tab-jml">{{ $semuaKelompok->count() }}</span>
        </a>
        <a href="{{ route('kelompok.index', array_filter(['hal' => 'anggota', 'pilih' => $pilih ?: null])) }}" data-hal="anggota"
           class="{{ $hal === 'anggota' ? 'aktif' : '' }}" role="tab">
            <svg class="i"><use href="#i-users"/></svg> Daftar Anggota
            <span class="tab-jml" id="tabJmlAng">…</span>
        </a>
    </div>

    <div class="kel-grid {{ $mode }}">

        {{-- ================= HALAMAN 1: DAFTAR KELOMPOK ================= --}}
        <section class="panel kel-kiri" id="halDaftar">
            <div class="kel-bar">
                <h3><svg class="i"><use href="#i-berkas"/></svg> Daftar Kelompok Rapat</h3>
                <form method="GET" action="{{ route('kelompok.index') }}" class="f-cari">
                    <svg class="i"><use href="#i-cari"/></svg>
                    <input type="search" name="q" value="{{ $kata }}" placeholder="Cari nama kelompok..." autocomplete="off">
                </form>
            </div>

            <div class="kel-aksi-atas">
                <span class="petunjuk">Klik baris kelompok untuk melihat anggotanya.</span>
                <button type="button" class="btn btn-biru" id="btnBuat"><svg class="i"><use href="#i-plus"/></svg> Buat Kelompok Baru</button>
            </div>

            <div class="kel-tabel">
                <table class="tabel-kel">
                    <thead>
                        <tr>
                            <th style="width:46px">No.</th>
                            <th>Nama Kelompok</th>
                            <th>Jumlah Anggota</th>
                            <th class="c" style="width:132px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbKelompok">
                        @forelse ($kelompok as $i => $g)
                            <tr data-id="{{ $g->id_kelompok }}"
                                data-nama="{{ $g->nama_kelompok }}"
                                data-ket="{{ $g->attr('keterangan') }}"
                                title="Klik untuk melihat anggota"
                                class="{{ $g->id_kelompok === $pilih ? 'dipilih' : '' }}">
                                <td>{{ $kelompok->firstItem() + $i }}</td>
                                <td><span class="nama-kel"><span class="ava-kel"><svg class="i"><use href="#i-users"/></svg></span><span class="teks-kel">{{ $g->nama_kelompok }}</span></span></td>
                                <td><span class="chip-jml"><svg class="i"><use href="#i-users"/></svg><span><span class="n">{{ $g->anggota_count }}</span> Orang</span></span></td>
                                <td class="aksi-sel">
                                    <div class="aksi-ikon">
                                        <button type="button" class="ikon-btn biru" data-aksi="edit" title="Ubah kelompok" aria-label="Ubah kelompok"><svg class="i"><use href="#i-pensil"/></svg></button>
                                        <button type="button" class="ikon-btn muda" data-aksi="lihat" title="Lihat detail" aria-label="Lihat detail"><svg class="i"><use href="#i-user"/></svg></button>
                                        <button type="button" class="ikon-btn merah" data-aksi="hapus" title="Hapus kelompok" aria-label="Hapus kelompok"><svg class="i"><use href="#i-sampah"/></svg></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kosong">
                                <svg class="i"><use href="#i-users"/></svg>
                                <span>{{ $kata !== '' ? 'Tidak ada kelompok yang cocok dengan pencarian.' : 'Belum ada kelompok rapat. Klik "Buat Kelompok Baru".' }}</span>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="kel-kaki">
                <div class="info-jumlah">
                    @if ($kelompok->total() > 0)
                        Menampilkan {{ $kelompok->firstItem() }} – {{ $kelompok->lastItem() }} dari {{ $kelompok->total() }} kelompok
                    @else
                        Tidak ada kelompok
                    @endif
                </div>

                @if ($kelompok->total() > 0)
                    @php
                        $now = $kelompok->currentPage();
                        $akhirHal = $kelompok->lastPage();
                        $dariHal = max(1, $now - 2);
                        $sampaiHal = min($akhirHal, $now + 2);
                    @endphp
                    <nav class="paging" aria-label="Halaman">
                        @if ($now > 1)
                            <a href="{{ $kelompok->url(1) }}" aria-label="Halaman pertama">«</a>
                            <a href="{{ $kelompok->url($now - 1) }}" aria-label="Sebelumnya">‹</a>
                        @else
                            <span class="mati">«</span><span class="mati">‹</span>
                        @endif

                        @for ($p = $dariHal; $p <= $sampaiHal; $p++)
                            @if ($p === $now)
                                <span class="aktif">{{ $p }}</span>
                            @else
                                <a href="{{ $kelompok->url($p) }}">{{ $p }}</a>
                            @endif
                        @endfor

                        @if ($now < $akhirHal)
                            <a href="{{ $kelompok->url($now + 1) }}" aria-label="Berikutnya">›</a>
                            <a href="{{ $kelompok->url($akhirHal) }}" aria-label="Halaman terakhir">»</a>
                        @else
                            <span class="mati">›</span><span class="mati">»</span>
                        @endif
                    </nav>
                @endif
            </div>
        </section>

        {{-- ================= HALAMAN 2: DAFTAR ANGGOTA ================= --}}
        <section class="panel kel-anggota" id="halAnggota">
            <div class="kel-bar">
                <h3><svg class="i"><use href="#i-users"/></svg> Daftar Anggota <span id="aJumlah">(0 Orang)</span></h3>
                <button type="button" class="btn btn-biru" id="btnManual" disabled title="Pilih satu kelompok dulu"><svg class="i"><use href="#i-plus"/></svg> Tambah Anggota</button>
            </div>

            <div class="ang-filter">
                <div class="f-cari lebar">
                    <svg class="i"><use href="#i-cari"/></svg>
                    <input type="search" id="cariAnggota" placeholder="Cari nama, unit, atau kelompok..." autocomplete="off">
                </div>
                <select id="fKelompok" aria-label="Filter kelompok">
                    <option value="">Semua Kelompok</option>
                    @foreach ($semuaKelompok as $g)
                        <option value="{{ $g->id_kelompok }}">{{ $g->nama_kelompok }}</option>
                    @endforeach
                </select>
            </div>

            <div class="kel-tabel">
                <table class="tabel-kel tabel-ang">
                    <thead>
                        <tr>
                            <th style="width:46px">No.</th>
                            <th>Nama Pegawai</th>
                            <th>Unit / Jabatan</th>
                            <th>Kelompok</th>
                            <th class="c" style="width:64px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbAnggota">
                        <tr><td colspan="5" class="kosong"><svg class="i"><use href="#i-users"/></svg><span>Memuat anggota…</span></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="kel-aksi-bawah">
                <button type="button" class="btn btn-garis-biru" id="btnPegawai" disabled title="Pilih satu kelompok dulu">
                    <svg class="i"><use href="#i-user-plus"/></svg> Tambah dari Data Pegawai (SIM NH)
                </button>
            </div>
        </section>
    </div>

    {{-- ===== Dialog detail kelompok ===== --}}
    <dialog id="dlgDetail" class="dialog dialog-lebar">
        <div class="dialog-kepala">
            <b>Detail Kelompok</b>
            <button type="button" class="dialog-tutup" data-tutup aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
        </div>
        <div class="dialog-isi">
            <div class="detail-atas">
                <span class="avatar-kel"><svg class="i"><use href="#i-users"/></svg></span>
                <div class="detail-nama">
                    <b id="dNamaKel">-</b>
                    <span class="lencana biru" id="dJumlahKel">0 Anggota</span>
                </div>
            </div>
            <dl class="detail-info">
                <div><dt><svg class="i"><use href="#i-grid"/></svg> Unit / Penyelenggara</dt><dd id="dUnit">-</dd></div>
                <div><dt><svg class="i"><use href="#i-kalender-plus"/></svg> Dibuat oleh</dt><dd id="dOleh">-</dd></div>
                <div><dt><svg class="i"><use href="#i-kalender"/></svg> Tanggal dibuat</dt><dd id="dTgl">-</dd></div>
                @if ($punyaKeterangan)
                    <div><dt><svg class="i"><use href="#i-berkas"/></svg> Keterangan</dt><dd id="dKet">-</dd></div>
                @endif
            </dl>
        </div>
        <div class="dialog-kaki">
            <button type="button" class="btn btn-garis-merah" id="dHapus"><svg class="i"><use href="#i-sampah"/></svg> Hapus</button>
            <button type="button" class="btn btn-garis-biru" id="dEdit"><svg class="i"><use href="#i-pensil"/></svg> Edit</button>
            <button type="button" class="btn" data-tutup>Tutup</button>
        </div>
    </dialog>

    {{-- ===== Dialog buat / ubah kelompok ===== --}}
    <dialog id="dlgKelompok" class="dialog">
        <form id="formKelompok" novalidate>
            <div class="dialog-kepala">
                <b id="kJudul">Buat Kelompok Baru</b>
                <button type="button" class="dialog-tutup" data-tutup aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="dialog-isi">
                <div id="kGalat" class="alert gagal" hidden></div>
                <input type="hidden" id="kId">

                <label for="kNama">Nama Kelompok <span class="wajib">*</span></label>
                <input type="text" id="kNama" maxlength="100" required placeholder="Contoh: Tim Verifikasi RKA">

                @if ($punyaKeterangan)
                    <label for="kKet">Keterangan</label>
                    <textarea id="kKet" rows="3" maxlength="255" placeholder="Tujuan atau deskripsi singkat kelompok"></textarea>
                @endif
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn" data-tutup>Batal</button>
                <button type="submit" class="btn btn-biru" id="kSimpan">Simpan</button>
            </div>
        </form>
    </dialog>

    {{-- ===== Dialog tambah anggota manual ===== --}}
    <dialog id="dlgManual" class="dialog">
        <form id="formManual" novalidate>
            <div class="dialog-kepala">
                <b>Tambah Anggota</b>
                <button type="button" class="dialog-tutup" data-tutup aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="dialog-isi">
                <div id="mGalat" class="alert gagal" hidden></div>

                <label for="mNama">Nama Lengkap <span class="wajib">*</span></label>
                <input type="text" id="mNama" maxlength="100" required>

                <label for="mWa">Nomor WhatsApp <span class="wajib">*</span></label>
                <input type="text" id="mWa" maxlength="20" inputmode="tel" required placeholder="Contoh: 6281234567890">
                <p class="catatan">Gunakan format yang sama dengan nomor di data pegawai (misalnya diawali 62) agar unit anggota dapat terbaca.</p>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn" data-tutup>Batal</button>
                <button type="submit" class="btn btn-biru" id="mSimpan">Tambahkan</button>
            </div>
        </form>
    </dialog>

    {{-- ===== Dialog tambah dari data pegawai ===== --}}
    <dialog id="dlgPegawai" class="dialog dialog-lebar">
        <div class="dialog-kepala">
            <b>Tambah dari Data Pegawai (SIM NH)</b>
            <button type="button" class="dialog-tutup" data-tutup aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
        </div>
        <div class="dialog-isi">
            <div class="peg-filter">
                <div class="f-cari lebar">
                    <svg class="i"><use href="#i-cari"/></svg>
                    <input type="search" id="pCari" placeholder="Cari nama, NIK, unit, atau jabatan..." autocomplete="off">
                </div>
                <select id="pUnit" aria-label="Pilih unit">
                    <option value="">Atau pilih satu unit...</option>
                    @foreach ($units as $u)
                        <option value="{{ $u }}">{{ $u }}</option>
                    @endforeach
                </select>
            </div>

            <label class="peg-semua"><input type="checkbox" id="pSemua"> Pilih semua <span id="pInfo" class="catatan"></span></label>
            <div id="pHasil" class="peg-hasil"></div>
        </div>
        <div class="dialog-kaki">
            <button type="button" class="btn" data-tutup>Batal</button>
            <button type="button" class="btn btn-biru" id="pTambah" disabled>Tambahkan</button>
        </div>
    </dialog>

    <div id="toast" class="toast" role="status" hidden></div>
@endsection

@push('scripts')
    <script>
        window.KELOMPOK = @json($konfig);
    </script>
    <script src="{{ asset('js/kelompok-rapat.js') }}?v={{ @filemtime(public_path('js/kelompok-rapat.js')) }}"></script>
@endpush