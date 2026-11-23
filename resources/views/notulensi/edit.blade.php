@extends('layouts.app')

@section('title', 'Notulensi Rapat')
@section('subtitle', 'Isi dan kelola notulensi, hasil keputusan, dan rencana tindak lanjut rapat.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/notulensi.css') }}?v={{ @filemtime(public_path('css/notulensi.css')) }}">
@endpush

@section('content')
@php
    use App\Models\TindakLanjutNotulensi as TL;

    $terkunci = $notulensi->sudahFinal();
    $tgl      = \Carbon\Carbon::parse($rapat->tanggal);
    $foto     = $notulensi->berkas->where('jenis', 'foto');
    $lampiran = $notulensi->berkas->where('jenis', 'lampiran');
    $metode   = ucfirst(str_replace('_', ' ', $rapat->metode));

    $baris = old('tl', $notulensi->tindakLanjut->map(fn ($t) => [
        'uraian' => $t->uraian, 'pic' => $t->pic,
        'target_tanggal' => optional($t->target_tanggal)->format('Y-m-d'), 'status' => $t->status,
    ])->all());

    $adaGalatBerkas = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'foto') || str_starts_with($k, 'lampiran'));
    $tabAwal = $adaGalatBerkas ? 'dok' : 'notulen';

    $badge = $notulensi->exists ? ($terkunci ? ['hijau', 'Notulensi final'] : ['kuning', 'Draft']) : ['abu', 'Belum diisi'];
    $pct   = $rapat->jumlah_peserta ? round($hadir->count() / $rapat->jumlah_peserta * 100) : null;
@endphp

@if ($errors->any())
    <div class="alert gagal">
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<section class="kartu nt-info" aria-label="Ringkasan rapat">
    <h2>{{ $rapat->nama_rapat }}</h2>
    <span><svg class="i"><use href="#i-kalender"/></svg>{{ $tgl->translatedFormat('d F Y') }}</span>
    <span>{{ substr($rapat->waktu, 0, 5) }}{{ $rapat->waktu_selesai ? ' – ' . substr($rapat->waktu_selesai, 0, 5) : '' }} WIB</span>
    <span>{{ $rapat->tempat_rapat }}</span>
    <span><svg class="i"><use href="#i-users"/></svg>{{ $rapat->jumlah_peserta ?? '-' }} peserta</span>
    <span class="nt-badge {{ $badge[0] }}">{{ $badge[1] }}</span>
</section>

<form id="formNotulensi" method="post" action="{{ route('notulensi.update', $rapat->id_rapat) }}" enctype="multipart/form-data" novalidate>
    @csrf
    @method('PUT')

    <div class="nt-bar">
        <div class="nt-tabs" role="tablist">
            <button type="button" class="nt-tab" role="tab" data-tab="notulen" aria-selected="{{ $tabAwal === 'notulen' ? 'true' : 'false' }}">Notulensi Rapat</button>
            <button type="button" class="nt-tab" role="tab" data-tab="peserta" aria-selected="false">Peserta &amp; Presensi <small>{{ $hadir->count() }}</small></button>
            <button type="button" class="nt-tab" role="tab" data-tab="dok" aria-selected="{{ $tabAwal === 'dok' ? 'true' : 'false' }}">Dokumentasi <small>{{ $foto->count() + $lampiran->count() }}</small></button>
        </div>
    </div>

    <div class="nt-grid">
        <div>
            {{-- Notulensi --}}
            <div class="kartu" data-pane="notulen" @if ($tabAwal !== 'notulen') hidden @endif>
                @foreach ([
                    'agenda'     => ['Agenda rapat', 'Satu agenda per baris.'],
                    'pembahasan' => ['Jalannya pembahasan', 'Tuliskan pokok bahasan dan masukan peserta.'],
                    'keputusan'  => ['Hasil / keputusan rapat', 'Satu keputusan per baris.'],
                ] as $nama => [$label, $bantu])
                    <div class="nt-field {{ $errors->has($nama) ? 'salah' : '' }}">
                        <label for="{{ $nama }}">{{ $label }} <span class="nt-wajib" title="Wajib saat finalisasi">*</span></label>
                        <textarea id="{{ $nama }}" name="{{ $nama }}" rows="4" @disabled($terkunci)>{{ old($nama, $notulensi->$nama) }}</textarea>
                        @error($nama) <p class="nt-err">{{ $message }}</p> @else <p class="nt-bantu">{{ $bantu }}</p> @enderror
                    </div>
                @endforeach

                <h3 class="nt-sub">Rencana tindak lanjut</h3>
                <div class="nt-tl">
                    <table>
                        <thead>
                            <tr><th class="no">No</th><th>Tindak lanjut</th><th>PIC</th><th class="tgl">Target waktu</th><th class="sts">Status</th><th class="aksi"></th></tr>
                        </thead>
                        <tbody id="barisTl">
                            @foreach ($baris as $i => $b)
                                @include('notulensi._baris_tl', ['i' => $i, 'b' => $b, 'terkunci' => $terkunci])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @unless ($terkunci)
                    <button type="button" class="btn nt-tambah" id="tambahTl">+ Tambah tindak lanjut</button>
                @endunless
            </div>

            {{-- Presensi --}}
            <div class="kartu" data-pane="peserta" hidden>
                <p class="nt-ringkas">
                    <b>{{ $hadir->count() }}</b> hadir
                    @if ($rapat->jumlah_peserta) dari {{ $rapat->jumlah_peserta }} undangan ({{ $pct }}%) @endif
                    <span class="nt-bantu" style="display:block">Data diambil dari form absensi rapat ini.</span>
                </p>
                <div class="nt-hadir">
                    <table>
                        <thead><tr><th>No</th><th>Nama</th><th>Unit</th><th>Waktu absen</th></tr></thead>
                        <tbody>
                            @forelse ($hadir as $n => $h)
                                <tr>
                                    <td>{{ $n + 1 }}</td>
                                    <td>{{ $h->nama_lengkap }}</td>
                                    <td>{{ $h->nama_unit }}</td>
                                    <td>{{ \Carbon\Carbon::parse($h->waktu_absensi)->format('H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="nt-kosong">Belum ada peserta yang mengisi absensi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Dokumentasi --}}
            <div class="kartu" data-pane="dok" @if ($tabAwal !== 'dok') hidden @endif>
                <h3 class="nt-kartu-judul">Foto dokumentasi</h3>
                @if ($foto->isNotEmpty())
                    <div class="nt-foto">
                        @foreach ($foto as $f)
                            <figure>
                                <a href="{{ $f->url }}" target="_blank" rel="noopener"><img src="{{ $f->url }}" alt="{{ $f->nama_asli }}" loading="lazy"></a>
                                @unless ($terkunci)
                                    <label><input type="checkbox" name="hapus_berkas[]" value="{{ $f->id_berkas }}"> Hapus saat disimpan</label>
                                @endunless
                            </figure>
                        @endforeach
                    </div>
                @else
                    <p class="nt-bantu" style="margin-bottom:10px">Belum ada foto.</p>
                @endif
                @unless ($terkunci)
                    <div class="nt-unggah">
                        <input type="file" id="inFoto" name="foto[]" accept="image/jpeg,image/png" multiple aria-label="Tambah foto">
                        <p class="nt-bantu">Format JPG atau PNG, maksimal 5 MB per file.</p>
                    </div>
                @endunless

                <h3 class="nt-kartu-judul" style="margin-top:20px">Lampiran file</h3>
                @if ($lampiran->isNotEmpty())
                    <ul class="nt-berkas">
                        @foreach ($lampiran as $l)
                            <li>
                                <a href="{{ route('notulensi.unduh', $l->id_berkas) }}">{{ $l->nama_asli }}</a>
                                <small>{{ $l->ukuran_teks }}</small>
                                @unless ($terkunci)
                                    <label><input type="checkbox" name="hapus_berkas[]" value="{{ $l->id_berkas }}"> Hapus</label>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="nt-bantu" style="margin-bottom:10px">Belum ada lampiran.</p>
                @endif
                @unless ($terkunci)
                    <div class="nt-unggah">
                        <input type="file" name="lampiran[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" multiple aria-label="Tambah lampiran">
                        <p class="nt-bantu">PDF, Word, Excel, atau PowerPoint, maksimal 10 MB per file.</p>
                    </div>
                @endunless
            </div>
        </div>

        <aside>
            <div class="kartu">
                <h3 class="nt-kartu-judul">Informasi rapat</h3>
                <dl class="nt-dl">
                    <div><dt>Penyelenggara</dt><dd>{{ $rapat->pengundang }}</dd></div>
                    <div><dt>Kelompok</dt><dd>{{ $rapat->nama_kelompok }}</dd></div>
                    <div><dt>Metode</dt><dd>{{ $metode }}</dd></div>
                    <div><dt>Peserta hadir</dt><dd>{{ $hadir->count() }}@if ($rapat->jumlah_peserta) dari {{ $rapat->jumlah_peserta }} @endif</dd></div>
                    @if ($terkunci && $notulensi->difinalkan_pada)
                        <div><dt>Difinalisasi</dt><dd>{{ $notulensi->difinalkan_pada->translatedFormat('d M Y, H:i') }}</dd></div>
                    @endif
                </dl>
            </div>
        </aside>
    </div>

    <div class="nt-aksi">
        <a class="btn" href="{{ route('notulensi.index') }}">Kembali ke daftar</a>
        <span class="jarak"></span>
        @if ($notulensi->exists)
            <a class="btn" href="{{ route('notulensi.cetak', $rapat->id_rapat) }}" target="_blank" rel="noopener">Export PDF</a>
        @endif
        @if ($terkunci)
            <button class="btn btn-biru" type="submit" form="formBuka">Buka untuk ubah</button>
        @else
            <button class="btn" type="submit" name="aksi" value="draft">Simpan draft</button>
            <button class="btn btn-hijau" type="submit" name="aksi" value="final"
                    onclick="return confirm('Finalisasi notulensi? Isinya akan dikunci sampai dibuka kembali.')">Simpan &amp; finalisasi</button>
        @endif
    </div>
</form>

@if ($terkunci)
    <form id="formBuka" method="post" action="{{ route('notulensi.buka', $rapat->id_rapat) }}">@csrf</form>
@endif

@unless ($terkunci)
    <template id="tplTl">
        @include('notulensi._baris_tl', ['i' => '__I__', 'b' => ['uraian' => '', 'pic' => '', 'target_tanggal' => '', 'status' => 'belum'], 'terkunci' => false])
    </template>
@endunless
@endsection

@push('scripts')
<script>
(function () {
    // Tab
    var tabs = document.querySelectorAll('[data-tab]'), panes = document.querySelectorAll('[data-pane]');
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
            panes.forEach(function (p) { p.hidden = p.dataset.pane !== t.dataset.tab; });
        });
    });

    // Tindak lanjut
    var tbody = document.getElementById('barisTl'), tpl = document.getElementById('tplTl'), tambah = document.getElementById('tambahTl');
    function nomori() {
        tbody.querySelectorAll('tr').forEach(function (tr, n) { tr.querySelector('.no').textContent = n + 1; });
    }
    if (tambah) {
        var indeks = tbody.querySelectorAll('tr').length;
        tambah.addEventListener('click', function () {
            var wadah = document.createElement('tbody');
            wadah.innerHTML = tpl.innerHTML.replace(/__I__/g, indeks++);
            var tr = wadah.firstElementChild;
            tbody.appendChild(tr); nomori();
            tr.querySelector('input').focus();
        });
        tbody.addEventListener('click', function (e) {
            var h = e.target.closest('.nt-hapus');
            if (h) { h.closest('tr').remove(); nomori(); }
        });
    }
    nomori();

    // Cek ukuran foto di browser (server tetap memeriksa)
    var inFoto = document.getElementById('inFoto');
    if (inFoto) inFoto.addEventListener('change', function () {
        var besar = Array.prototype.filter.call(inFoto.files, function (f) { return f.size > 5 * 1024 * 1024; });
        if (besar.length) { alert('Foto berikut lebih dari 5 MB dan tidak akan diunggah:\n' + besar.map(function (f) { return f.name; }).join('\n')); inFoto.value = ''; }
    });
})();
</script>
@endpush
