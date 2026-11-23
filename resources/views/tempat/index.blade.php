{{-- resources/views/tempat/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Tempat Rapat')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rapat.css') }}?v={{ @filemtime(public_path('css/rapat.css')) }}">
@endpush

@section('page_head')
    <div class="rapat-head">
        <svg class="i ikon-besar"><use href="#i-ruang"/></svg>
        <div>
            <h1>Tempat Rapat</h1>
            <p class="sub">Daftar ruangan atau lokasi yang bisa dipakai untuk rapat.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="rapat-wrap">

    {{--
        Pesan sukses (session 'sukses') dan galat (session 'galat') sudah ditampilkan oleh
        layouts/app.blade.php, jadi tidak ditampilkan lagi di sini agar tidak muncul dobel.
        Hanya pesan 'gagal' (kunci milik halaman ini) yang ditampilkan di bawah.
    --}}
    @if (session('gagal'))
        <div class="alert gagal" role="alert">{{ session('gagal') }}</div>
    @endif

    <section class="tp-card">
        <div class="tp-top">
            <div class="tp-title">
                <h3>Daftar Tempat</h3>
                <span class="tp-count">{{ $tempat->count() }}</span>
            </div>

            <div class="tp-tools">
                <form method="GET" action="{{ route('tempat.index') }}" class="tp-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama tempat atau gedung" aria-label="Cari tempat">
                    @if (request('q'))
                        <a href="{{ route('tempat.index') }}" class="tp-reset" title="Hapus pencarian">&times;</a>
                    @endif
                </form>

                <button type="button" class="tp-btn tp-btn-primary" id="btnTambah">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Tempat
                </button>
            </div>
        </div>

        <div class="tp-scroll">
            <table class="tp-table">
                <thead>
                    <tr>
                        <th class="c-no">No</th>
                        <th>Nama Tempat</th>
                        <th>Gedung</th>
                        <th class="c-aksi">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tempat as $i => $t)
                        <tr>
                            <td class="c-no">{{ $i + 1 }}</td>
                            <td class="c-nama">{{ $t->nama_tempat }}</td>
                            <td>
                                @if ($t->gedung)
                                    <span class="tp-badge">{{ $t->gedung }}</span>
                                @else
                                    <span class="tp-kosong">—</span>
                                @endif
                            </td>
                            <td class="c-aksi">
                                <div class="tp-aksi">
                                    <button type="button" class="tp-ikon btn-edit" title="Edit" aria-label="Edit {{ $t->nama_tempat }}"
                                            data-id="{{ $t->id }}"
                                            data-nama="{{ $t->nama_tempat }}"
                                            data-gedung="{{ $t->gedung }}">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </button>

                                    <form method="POST" action="{{ route('tempat.destroy', $t) }}"
                                          onsubmit="return confirm('Hapus tempat &quot;{{ addslashes($t->nama_tempat) }}&quot;?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="tp-ikon tp-ikon-hapus" title="Hapus" aria-label="Hapus {{ $t->nama_tempat }}">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m6 6 1 14h10l1-14"/><path d="M10 11v5M14 11v5"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="tp-empty">
                                {{ request('q') ? 'Tidak ada tempat yang cocok dengan pencarian.' : 'Belum ada tempat rapat.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>

{{-- Dialog tambah / edit --}}
<dialog id="dlgTempat" class="modal">
    <form method="POST" id="formTempat" action="{{ route('tempat.store') }}">
        @csrf
        <input type="hidden" name="_method" value="PUT" id="fMethod" disabled>
        <input type="hidden" name="_mode" id="fMode" value="{{ old('_mode', 'tambah') }}">
        <input type="hidden" name="_id" id="fId" value="{{ old('_id') }}">

        <h3 id="judulDlg">Tambah Tempat</h3>

        @if ($errors->any())
            <div class="alert gagal" role="alert">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <label class="field">
            <span>Nama Tempat <b>*</b></span>
            <input type="text" name="nama_tempat" id="fNama" value="{{ old('nama_tempat') }}" maxlength="100" required>
        </label>

        <label class="field">
            <span>Gedung</span>
            <input type="text" name="gedung" id="fGedung" value="{{ old('gedung') }}" maxlength="100">
        </label>

        <div class="baris-aksi akhir">
            <button type="button" class="btn btn-abu" id="btnBatal">Batal</button>
            <button type="submit" class="btn btn-biru">Simpan</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    const dlg     = document.getElementById('dlgTempat');
    const form    = document.getElementById('formTempat');
    const judul   = document.getElementById('judulDlg');
    const fMethod = document.getElementById('fMethod');
    const fMode   = document.getElementById('fMode');
    const fId     = document.getElementById('fId');
    const fNama   = document.getElementById('fNama');
    const fGedung = document.getElementById('fGedung');

    const urlStore  = @json(route('tempat.store'));
    const urlUpdate = id => @json(url('tempat-rapat')) + '/' + id;

    function modeTambah() {
        form.action     = urlStore;
        fMethod.disabled = true;
        fMode.value     = 'tambah';
        fId.value       = '';
        judul.textContent = 'Tambah Tempat';
    }

    function modeEdit(id) {
        form.action     = urlUpdate(id);
        fMethod.disabled = false;
        fMode.value     = 'edit';
        fId.value       = id;
        judul.textContent = 'Edit Tempat';
    }

    document.getElementById('btnTambah').addEventListener('click', function () {
        modeTambah();
        fNama.value = '';
        fGedung.value = '';
        dlg.showModal();
        fNama.focus();
    });

    document.querySelectorAll('.btn-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modeEdit(btn.dataset.id);
            fNama.value   = btn.dataset.nama;
            fGedung.value = btn.dataset.gedung || '';
            dlg.showModal();
            fNama.focus();
        });
    });

    document.getElementById('btnBatal').addEventListener('click', function () {
        dlg.close();
    });

    // Klik di luar kotak dialog = tutup
    dlg.addEventListener('click', function (e) {
        if (e.target === dlg) dlg.close();
    });

    @if ($errors->any())
        // Validasi gagal: buka lagi dialog dengan mode yang sama
        @if (old('_mode') === 'edit' && old('_id'))
            modeEdit(@json(old('_id')));
        @else
            modeTambah();
        @endif
        dlg.showModal();
    @endif
})();
</script>
@endpush