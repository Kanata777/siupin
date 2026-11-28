{{-- resources/views/pengguna/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Tambah User')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rapat.css') }}?v={{ @filemtime(public_path('css/rapat.css')) }}">
@endpush

@section('page_head')
    <div class="rapat-head">
        <svg class="i ikon-besar"><use href="#i-users"/></svg>
        <div>
            <h1>Tambah User</h1>
            <p class="sub">Buat akun dengan username dan kata sandi untuk masuk ke dashboard user.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="rapat-wrap">

    <section class="tp-card">
        <div class="tp-top">
            <div class="tp-title">
                <h3>Daftar User</h3>
                <span class="tp-count">{{ $pengguna->count() }}</span>
            </div>

            <div class="tp-tools">
                <form method="GET" action="{{ route('pengguna.index') }}" class="tp-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau username" aria-label="Cari user">
                    @if (request('q'))
                        <a href="{{ route('pengguna.index') }}" class="tp-reset" title="Hapus pencarian">&times;</a>
                    @endif
                </form>

                <button type="button" class="tp-btn tp-btn-primary" id="btnTambah">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah User
                </button>
            </div>
        </div>

        <div class="tp-scroll">
            <table class="tp-table">
                <thead>
                    <tr>
                        <th class="c-no">No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Peran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengguna as $i => $p)
                        <tr>
                            <td class="c-no">{{ $i + 1 }}</td>
                            <td class="c-nama">{{ $p->name }}</td>
                            <td>{{ $p->username }}</td>
                            <td>
                                <span class="tp-badge">{{ $p->isSuperAdmin() ? 'Super Admin' : 'User' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="tp-empty">
                                {{ request('q') ? 'Tidak ada user yang cocok dengan pencarian.' : 'Belum ada user.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>

{{-- Dialog tambah user --}}
<dialog id="dlgUser" class="modal">
    <form method="POST" action="{{ route('pengguna.store') }}" autocomplete="off">
        @csrf
        <h3>Tambah User</h3>

        @if ($errors->any())
            <div class="alert gagal" role="alert">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <label class="field">
            <span>Nama <b>*</b></span>
            <input type="text" name="name" id="fNama" value="{{ old('name') }}" maxlength="100" required>
        </label>

        <label class="field">
            <span>Username <b>*</b></span>
            <input type="text" name="username" value="{{ old('username') }}" maxlength="50"
                   pattern="[A-Za-z0-9._\-]+" autocapitalize="none" spellcheck="false" required>
        </label>

        <label class="field">
            <span>Kata sandi <b>*</b> (minimal 8 karakter)</span>
            <input type="password" name="password" minlength="8" autocomplete="new-password" required>
        </label>

        <label class="field">
            <span>Ulangi kata sandi <b>*</b></span>
            <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
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
    const dlg = document.getElementById('dlgUser');

    document.getElementById('btnTambah').addEventListener('click', function () {
        dlg.querySelectorAll('input[type=text], input[type=password]').forEach(i => i.value = '');
        dlg.showModal();
        document.getElementById('fNama').focus();
    });

    document.getElementById('btnBatal').addEventListener('click', () => dlg.close());

    // Klik di luar kotak dialog = tutup
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });

    @if ($errors->any())
        // Validasi gagal: buka lagi dialog agar pesan galat terlihat
        dlg.showModal();
    @endif
})();
</script>
@endpush