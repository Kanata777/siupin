@extends('layouts.app')

@section('title', 'Jadwal Rapat')
@section('body_class', 'halaman-penuh')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/jadwal.css') }}?v={{ @filemtime(public_path('css/jadwal.css')) }}">
@endpush

@section('page_head')
    <div class="jadwal-head">
        <div class="jadwal-judul">
            <svg class="i ikon-besar"><use href="#i-kalender"/></svg>
            <div>
                <h1>Jadwal Rapat</h1>
                <p class="sub">Daftar rencana, pelaksanaan, dan status seluruh rapat/undangan.</p>
            </div>
        </div>
        <a href="{{ route('rapat.create') }}" class="btn btn-biru btn-besar">
            <svg class="i"><use href="#i-plus"/></svg> Buat Rapat / Undangan
        </a>
    </div>
@endsection

@section('content')

    {{-- ===== Tab status ===== --}}
    <nav class="tab-status" aria-label="Filter status">
        @foreach ($tab as $t)
            <a href="{{ $t['url'] }}" class="tab-s {{ $status === $t['kunci'] ? 'aktif' : '' }}">
                <svg class="i"><use href="#i-{{ $t['ikon'] }}"/></svg>
                {{ $t['label'] }} ({{ $t['n'] }})
            </a>
        @endforeach
    </nav>

    {{-- ===== Filter ===== --}}
    <form method="GET" action="{{ route('rapat.jadwal') }}" id="formFilter" class="filter-bar">
        @if ($status !== '') <input type="hidden" name="status" value="{{ $status }}"> @endif
        @if ($semua && !$dari && !$sampai) <input type="hidden" name="semua" value="1"> @endif

        <div class="f-ikon">
            <svg class="i"><use href="#i-cari"/></svg>
            <input type="search" name="q" id="fCari" value="{{ request('q') }}" placeholder="Cari nama rapat, agenda, atau unit..." autocomplete="off">
        </div>

        <div class="f-ikon">
            <svg class="i"><use href="#i-gedung"/></svg>
            <select name="unit" aria-label="Filter unit">
                <option value="">Semua Unit</option>
                @foreach ($units as $u)
                    <option value="{{ $u }}" @selected(request('unit') === $u)>{{ $u }}</option>
                @endforeach
            </select>
        </div>

        <div class="f-ikon">
            <svg class="i"><use href="#i-pin"/></svg>
            <select name="tempat" aria-label="Filter tempat">
                <option value="">Semua Tempat</option>
                @foreach ($tempatList as $t)
                    <option value="{{ $t->id_tempat }}" @selected((int) request('tempat') === $t->id_tempat)>{{ $t->nama_tempat }}</option>
                @endforeach
            </select>
        </div>

        <div class="f-ikon f-tanggal">
            <svg class="i"><use href="#i-kalender"/></svg>
            <div class="teks {{ $teksRentang === '' ? 'kosong' : '' }}" id="teksRentang">{{ $teksRentang !== '' ? $teksRentang : 'Pilih Rentang Tanggal' }}</div>
            <input type="date" name="dari" id="fDari" class="tindih tindih-kiri" value="{{ $dari }}" aria-label="Dari tanggal">
            <input type="date" name="sampai" id="fSampai" class="tindih tindih-kanan" value="{{ $sampai }}" aria-label="Sampai tanggal">
        </div>

        <a href="{{ route('rapat.jadwal') }}" class="btn-ikon-filter" title="Atur ulang filter" aria-label="Atur ulang filter">
            <svg class="i"><use href="#i-ulang"/></svg>
        </a>
        <button type="submit" hidden>Cari</button>
    </form>

    {{-- ===== Tabel ===== --}}
    @php
        $lencana = [
            'mendatang'   => ['Mendatang',   'biru',   'kalender-plus'],
            'berlangsung' => ['Berlangsung', 'hijau',  'play'],
            'selesai'     => ['Selesai',     'abu',    'cek'],
            'terlewat'    => ['Terlewat',    'kuning', 'jam'],
            'tertunda'    => ['Tertunda',    'oranye', 'jam'],
            'draft'       => ['Draft',       'abu',    'edit'],
            'dibatalkan'  => ['Dibatalkan',  'merah',  'silang'],
        ];
        $hariIni = now()->toDateString();
    @endphp

    <div class="tabel-bungkus">
        <table class="tabel-jadwal">
            <thead>
                <tr>
                    <th style="width:48px">No</th>
                    <th>Nama Rapat / Agenda</th>
                    <th>Tanggal &amp; Waktu</th>
                    <th>Tempat Rapat</th>
                    <th>Peserta</th>
                    <th>Status</th>
                    <th class="c">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rapat as $i => $r)
                    @php
                        $st = $r->status_tampil;
                        [$lbl, $warna, $ikonSt] = $lencana[$st] ?? [(string) $r->status, 'abu', 'jam'];
                        $mulai = substr($r->waktu, 0, 5);
                        $akhir = $r->waktu_selesai ? substr($r->waktu_selesai, 0, 5) : null;
                    @endphp
                    <tr>
                        <td>{{ $rapat->firstItem() + $i }}</td>

                        <td>
                            <div class="nama-rapat">
                                <svg class="i ikon-rapat"><use href="#i-kalender-plus"/></svg>
                                <div>
                                    <b>{{ $r->nama_rapat }}</b>
                                    <small>{{ \Illuminate\Support\Str::limit($r->catatan_rapat, 70) }}</small>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="tgl-waktu">
                                <span><svg class="i"><use href="#i-kalender"/></svg> {{ $r->tanggal->translatedFormat('d M Y') }}</span>
                                <span><svg class="i"><use href="#i-jam"/></svg> {{ $mulai }}{{ $akhir ? ' – ' . $akhir : '' }} WIB</span>
                            </div>
                        </td>

                        <td><span class="tempat"><svg class="i"><use href="#i-pin"/></svg> {{ $r->tempat_rapat }}</span></td>

                        <td><span class="peserta"><svg class="i"><use href="#i-users"/></svg> {{ $r->jumlah_tampil }}</span></td>

                        <td><span class="lencana {{ $warna }}"><svg class="i"><use href="#i-{{ $ikonSt }}"/></svg> {{ $lbl }}</span></td>

                        <td class="aksi-sel">
                            <div class="aksi-ikon">
                                {{-- Lihat --}}
                                <button type="button" class="ikon-btn biru" data-lihat="{{ $r->id_rapat }}" title="Lihat detail" aria-label="Lihat detail">
                                    <svg class="i"><use href="#i-mata"/></svg>
                                </button>

                                {{-- Ubah / Notulensi --}}
                                @if (in_array($st, ['mendatang', 'terlewat', 'tertunda', 'draft', 'lainnya'], true))
                                    <button type="button" class="ikon-btn" data-edit="{{ $r->id_rapat }}" title="Ubah rapat" aria-label="Ubah rapat">
                                        <svg class="i"><use href="#i-pensil"/></svg>
                                    </button>
                                @elseif ($st === 'berlangsung')
                                    <span class="ikon-btn mati" title="Rapat sedang berlangsung"><svg class="i"><use href="#i-pensil"/></svg></span>
                                @elseif ($st === 'selesai')
                                    <a class="ikon-btn" href="{{ route('notulensi.index', ['rapat' => $r->id_rapat]) }}" title="Notulensi rapat" aria-label="Notulensi rapat">
                                        <svg class="i"><use href="#i-catatan"/></svg>
                                    </a>
                                @endif

                                {{-- Tandai selesai --}}
                                @if (in_array($st, ['berlangsung', 'terlewat'], true))
                                    <form method="POST" action="{{ route('rapat.selesai', $r->id_rapat) }}" class="satu"
                                          onsubmit="return confirm('Tandai rapat ini sebagai selesai?')">
                                        @csrf
                                        <button type="submit" class="ikon-btn hijau" title="Tandai selesai" aria-label="Tandai selesai"><svg class="i"><use href="#i-centang"/></svg></button>
                                    </form>
                                @endif

                                {{-- Batalkan --}}
                                @if (in_array($st, ['mendatang', 'terlewat', 'tertunda', 'draft', 'lainnya'], true))
                                    <form method="POST" action="{{ route('rapat.batal', $r->id_rapat) }}" class="satu"
                                          onsubmit="return confirm('Batalkan rapat ini? Peserta tidak akan menerima undangan lagi.')">
                                        @csrf
                                        <button type="submit" class="ikon-btn merah" title="Batalkan rapat" aria-label="Batalkan rapat"><svg class="i"><use href="#i-x"/></svg></button>
                                    </form>
                                @endif

                                {{-- Salin (rapat selesai) --}}
                                @if ($st === 'selesai')
                                    <a class="ikon-btn" href="{{ route('rapat.salin', $r->id_rapat) }}" title="Salin menjadi rapat baru" aria-label="Salin rapat">
                                        <svg class="i"><use href="#i-salin"/></svg>
                                    </a>
                                @endif

                                {{-- Aktifkan kembali (rapat dibatalkan) --}}
                                @if ($st === 'dibatalkan')
                                    @if ($r->tanggal->toDateString() >= $hariIni)
                                        <form method="POST" action="{{ route('rapat.aktifkan', $r->id_rapat) }}" class="satu"
                                              onsubmit="return confirm('Aktifkan kembali rapat ini?')">
                                            @csrf
                                            <button type="submit" class="ikon-btn" title="Aktifkan kembali" aria-label="Aktifkan kembali"><svg class="i"><use href="#i-riwayat"/></svg></button>
                                        </form>
                                    @else
                                        <span class="ikon-btn mati" title="Tanggal sudah lewat"><svg class="i"><use href="#i-riwayat"/></svg></span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="kosong">Tidak ada rapat yang sesuai dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ===== Footer: info + halaman ===== --}}
    <div class="tabel-kaki">
        <div class="info-jumlah">
            @if ($rapat->total() > 0)
                Menampilkan {{ $rapat->firstItem() }} – {{ $rapat->lastItem() }} dari {{ $rapat->total() }} rapat
            @else
                Tidak ada rapat
            @endif
            @if ($terbatas)
                · <a href="{{ route('rapat.jadwal', array_merge(request()->except(['page', 'dari', 'sampai']), ['semua' => 1])) }}">Lihat semua tanggal</a>
            @endif
        </div>

        @if ($rapat->lastPage() >= 1 && $rapat->total() > 0)
            @php
                $now = $rapat->currentPage();
                $akhirHal = $rapat->lastPage();
                $dariHal = max(1, $now - 2);
                $sampaiHal = min($akhirHal, $now + 2);
            @endphp
            <nav class="paging" aria-label="Halaman">
                @if ($now > 1)
                    <a href="{{ $rapat->url(1) }}" aria-label="Halaman pertama">«</a>
                    <a href="{{ $rapat->url($now - 1) }}" aria-label="Sebelumnya">‹</a>
                @else
                    <span class="mati">«</span><span class="mati">‹</span>
                @endif

                @for ($p = $dariHal; $p <= $sampaiHal; $p++)
                    @if ($p === $now)
                        <span class="aktif">{{ $p }}</span>
                    @else
                        <a href="{{ $rapat->url($p) }}">{{ $p }}</a>
                    @endif
                @endfor

                @if ($now < $akhirHal)
                    <a href="{{ $rapat->url($now + 1) }}" aria-label="Berikutnya">›</a>
                    <a href="{{ $rapat->url($akhirHal) }}" aria-label="Halaman terakhir">»</a>
                @else
                    <span class="mati">›</span><span class="mati">»</span>
                @endif
            </nav>
        @endif
    </div>

    {{-- ===== Dialog detail ===== --}}
    <dialog id="dlgDetail" class="dialog dialog-lebar">
        <div class="dialog-kepala">
            <div>
                <b id="dNama">Detail Rapat</b>
                <span id="dStatus" class="lencana abu"></span>
            </div>
            <button type="button" class="dialog-tutup" data-tutup aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
        </div>
        <div class="dialog-isi">
            <p id="dAgenda" class="agenda"></p>
            <dl class="info-grid">
                <div><dt>Tanggal</dt><dd id="dTanggal"></dd></div>
                <div><dt>Waktu</dt><dd id="dWaktu"></dd></div>
                <div><dt>Tempat</dt><dd id="dTempat"></dd></div>
                <div><dt>Pengundang</dt><dd id="dPengundang"></dd></div>
                <div><dt>Kelompok</dt><dd id="dKelompok"></dd></div>
                <div><dt>Peserta</dt><dd id="dJumlah"></dd></div>
                <div id="dLinkBaris" hidden><dt>Link Rapat</dt><dd><a id="dLink" href="#" target="_blank" rel="noopener noreferrer"></a></dd></div>
                <div id="dLampiranBaris" hidden><dt>Lampiran</dt><dd><a id="dLampiran" href="#" target="_blank" rel="noopener noreferrer">Unduh lampiran</a></dd></div>
            </dl>
            <h4 class="sub-judul">Daftar Peserta</h4>
            <ol id="dAnggota" class="daftar-anggota"></ol>
        </div>
    </dialog>

    {{-- ===== Dialog ubah ===== --}}
    <dialog id="dlgEdit" class="dialog dialog-lebar">
        <form id="formEdit" novalidate>
            <div class="dialog-kepala">
                <b>Ubah Rapat</b>
                <button type="button" class="dialog-tutup" data-tutup aria-label="Tutup"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="dialog-isi">
                <div id="eGalat" class="alert gagal" hidden></div>
                <input type="hidden" id="eId">

                <label for="eNama">Nama Rapat <span class="wajib">*</span></label>
                <input type="text" id="eNama" maxlength="100" required>

                <label for="eAgenda">Agenda / Topik</label>
                <textarea id="eAgenda" rows="2" maxlength="255"></textarea>

                <div class="dua-kolom">
                    <div>
                        <label for="eTanggal">Tanggal <span class="wajib">*</span></label>
                        <input type="date" id="eTanggal" required>
                    </div>
                    <div>
                        <label for="eWaktu">Waktu Mulai <span class="wajib">*</span></label>
                        <input type="time" id="eWaktu" required>
                    </div>
                </div>

                @if ($punyaSelesai)
                    <label for="eSelesai">Waktu Selesai</label>
                    <input type="time" id="eSelesai">
                @endif

                <label for="eTempat">Tempat Rapat <span class="wajib">*</span></label>
                <select id="eTempat" required>
                    <option value="">— Pilih tempat —</option>
                    @foreach ($tempatList as $t)
                        <option value="{{ $t->id_tempat }}">{{ $t->nama_tempat }}</option>
                    @endforeach
                </select>

                <p class="catatan-edit">Peserta tidak diubah di sini. Untuk peserta berbeda, buat rapat baru atau salin rapat ini.</p>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn" data-tutup>Batal</button>
                <button type="submit" class="btn btn-biru" id="eSimpan">Simpan Perubahan</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        window.JADWAL = @json($konfig);
    </script>
    <script src="{{ asset('js/jadwal-rapat.js') }}?v={{ @filemtime(public_path('js/jadwal-rapat.js')) }}"></script>
@endpush    