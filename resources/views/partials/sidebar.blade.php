@php
    $superAdmin = auth()->user()?->isSuperAdmin() ?? false;

    // [label, route, pola route aktif, nama ikon di ikon-sprite, hanya Super Admin?]
    $menu = [
        ['Dashboard',       'dashboard',       'dashboard',     'home',          false],
        ['Buat Rapat',      'rapat.create',    'rapat.create',  'kalender-plus', false],
        ['Jadwal Rapat',    'rapat.jadwal',    'rapat.jadwal',  'kalender',      true],
        ['Rapat Saya',      'rapat.saya',      'rapat.saya',    'berkas',        false],
        ['Tempat Rapat',    'tempat.index',    'tempat.*',      'ruang',         false],
        ['Kelompok Rapat',  'kelompok.index',  'kelompok.*',    'users',         false],
        ['Notulensi Rapat', 'notulensi.index', 'notulensi.*',   'catatan',       false],
        ['Status WA',       'wa.index',        'wa.*',          'berkas',        false],
        ['Laporan & Rekap', 'laporan.index',   'laporan.*',     'laporan',       true],
    ];
@endphp

<aside class="sidebar">
    <nav>
        @foreach ($menu as [$label, $rute, $pola, $ikon, $hanyaAdmin])
            {{-- Menu hanya disembunyikan untuk kenyamanan; pembatasan sebenarnya ada di backend --}}
            @continue($hanyaAdmin && !$superAdmin)
            <a href="{{ route($rute) }}" class="{{ request()->routeIs($pola) ? 'aktif' : '' }}">
                <svg class="i"><use href="#i-{{ $ikon }}"/></svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</aside>
