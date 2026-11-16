@extends('layouts.app')

@section('title', 'Laporan & Rekap')
@section('body_class', 'halaman-penuh')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/laporan.css') }}?v={{ @filemtime(public_path('css/laporan.css')) }}">
@endpush

@section('page_head')
    <div class="lap-head">
        <svg class="i ikon-besar"><use href="#i-laporan"/></svg>
        <div>
            <h1>Laporan &amp; Rekap</h1>
            <p class="sub">Pilih laporan, tentukan periode, lalu lihat pratinjau atau unduh hasilnya.</p>
        </div>
    </div>
@endsection

@section('content')
<form method="GET" action="{{ route('laporan.index') }}" id="formLaporan">
    <input type="hidden" name="lihat" id="inLihat" value="{{ $lihat }}">

    {{-- ===== Filter ===== --}}
    <div class="lap-filter">
        <div class="f-ikon f-tanggal">
            <svg class="i"><use href="#i-kalender"/></svg>
            <div class="teks {{ $teksRentang === '' ? 'kosong' : '' }}" id="teksRentang">{{ $teksRentang !== '' ? $teksRentang : 'Semua tanggal' }}</div>
            <input type="date" name="dari" id="fDari" class="tindih tindih-kiri" value="{{ $f['dari'] }}" aria-label="Dari tanggal">
            <input type="date" name="sampai" id="fSampai" class="tindih tindih-kanan" value="{{ $f['sampai'] }}" aria-label="Sampai tanggal">
        </div>

        <div class="f-ikon">
            <svg class="i"><use href="#i-gedung"/></svg>
            <select name="unit" aria-label="Filter unit">
                <option value="">Semua Unit</option>
                @foreach ($units as $u)
                    <option value="{{ $u }}" @selected($f['unit'] === $u)>{{ $u }}</option>
                @endforeach
            </select>
        </div>

        <div class="f-ikon">
            <svg class="i"><use href="#i-pin"/></svg>
            <select name="tempat" aria-label="Filter tempat">
                <option value="">Semua Tempat</option>
                @foreach ($tempatList as $t)
                    <option value="{{ $t->id_tempat }}" @selected($f['tempat'] === (int) $t->id_tempat)>{{ $t->nama_tempat }}</option>
                @endforeach
            </select>
        </div>

        <div class="f-ikon">
            <svg class="i"><use href="#i-cek"/></svg>
            <select name="status" aria-label="Filter status rapat">
                <option value="">Semua Status</option>
                @foreach ($statusList as $kunci => $label)
                    <option value="{{ $kunci }}" @selected($f['status'] === $kunci)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <a href="{{ route('laporan.index') }}" class="btn-ikon-filter" title="Atur ulang filter" aria-label="Atur ulang filter">
            <svg class="i"><use href="#i-ulang"/></svg>
        </a>
    </div>

    <div class="lap-preset">
        Periode cepat:
        @foreach ($preset as $nama => $url)
            <a href="{{ $url }}">{{ $nama }}</a>
        @endforeach
        <span class="lap-catatan">Filter berlaku sesuai keterangan pada tiap laporan.</span>
    </div>

    <div class="lap-grid">

        {{-- ================= PILIH LAPORAN ================= --}}
        <section class="panel lap-kiri">
            <div class="lap-bar">
                <h3><svg class="i"><use href="#i-berkas"/></svg> Pilih Laporan</h3>
                <label class="pilih-semua"><input type="checkbox" id="cekSemua"> Pilih semua</label>
            </div>

            <div class="lap-daftar">
                @foreach ($grup as $gKunci => $gLabel)
                    @php $isi = array_filter($daftar, fn ($d) => $d['grup'] === $gKunci); @endphp
                    @if (count($isi))
                        <div class="lap-grup">{{ $gLabel }}</div>
                        @foreach ($isi as $kunci => $d)
                            <div class="lap-item {{ $lihat === $kunci ? 'aktif' : '' }}" data-jenis="{{ $kunci }}" data-total="{{ $d['total'] }}">
                                <input type="checkbox" name="jenis[]" value="{{ $kunci }}" class="cek-jenis"
                                       @checked(in_array($kunci, $terpilih, true)) aria-label="Pilih {{ $d['judul'] }}">
                                <span class="lap-ikon"><svg class="i"><use href="#i-{{ $d['ikon'] }}"/></svg></span>
                                <div class="lap-teks">
                                    <b>{{ $d['judul'] }}</b>
                                    <small>{{ $d['deskripsi'] }}</small>
                                    <em>Filter: {{ $d['filter'] }}</em>
                                </div>
                                <span class="lap-jml"><b>{{ number_format($d['total'], 0, ',', '.') }}</b><small>baris</small></span>
                                <button type="button" class="btn-lihat" data-lihat="{{ $kunci }}"><svg class="i"><use href="#i-mata"/></svg> Lihat</button>
                            </div>
                        @endforeach
                    @endif
                @endforeach
            </div>
        </section>

        {{-- ================= KANAN ================= --}}
        <div class="lap-kanan">

            <section class="panel lap-pratinjau">
                <div class="lap-bar">
                    <h3><svg class="i"><use href="#i-mata"/></svg> <span id="pJudul">Pratinjau Laporan</span></h3>
                    <span class="lap-info" id="pInfo"></span>
                </div>
                <div class="lap-tabel" id="pTabel">
                    <div class="kosong-lap">
                        <svg class="i"><use href="#i-laporan"/></svg>
                        <span>Klik <b>Lihat</b> pada salah satu laporan untuk melihat isinya di sini.</span>
                    </div>
                </div>
            </section>

            <section class="panel lap-unduh">
                <div class="lap-bar">
                    <h3><svg class="i"><use href="#i-simpan"/></svg> Unduh Laporan</h3>
                    <span class="lap-info" id="uRingkas">Belum ada laporan dipilih</span>
                </div>

                <div class="format-grid">
                    <label class="format">
                        <input type="radio" name="format" value="xlsx" checked>
                        <span class="format-kartu"><svg class="i"><use href="#i-laporan"/></svg><b>Excel (.xlsx)</b><small>Satu berkas, satu lembar per laporan</small></span>
                    </label>
                    <label class="format">
                        <input type="radio" name="format" value="csv">
                        <span class="format-kartu"><svg class="i"><use href="#i-berkas"/></svg><b>CSV</b><small>ZIP bila lebih dari satu laporan</small></span>
                    </label>
                    <label class="format">
                        <input type="radio" name="format" value="pdf">
                        <span class="format-kartu"><svg class="i"><use href="#i-catatan"/></svg><b>PDF / Cetak</b><small>Simpan sebagai PDF dari dialog cetak</small></span>
                    </label>
                </div>

                <button type="submit" id="btnUnduh" class="btn btn-biru btn-unduh" formaction="{{ route('laporan.unduh') }}" disabled>
                    <svg class="i"><use href="#i-simpan"/></svg> <span id="btnTeks">Unduh Laporan Terpilih</span>
                </button>
            </section>
        </div>
    </div>
</form>
@endsection

@push('scripts')
    <script>
        window.LAPORAN = @json($konfig);
    </script>
    <script src="{{ asset('js/laporan.js') }}?v={{ @filemtime(public_path('js/laporan.js')) }}"></script>
@endpush