@php
    // [label, route, pola route aktif, nama ikon di ikon-sprite]
    $menu = [
        ['Dashboard',       'dashboard',       'dashboard',     'home'],
        ['Buat Rapat',      'rapat.create',    'rapat.create',  'kalender-plus'],
        ['Jadwal Rapat',    'rapat.jadwal',    'rapat.jadwal',  'kalender'],
        ['Rapat Saya',      'rapat.saya',      'rapat.saya',    'berkas'],
        ['Notulensi Rapat', 'notulensi.index', 'notulensi.*',   'catatan'],
        ['Kelompok Rapat',  'kelompok.index',  'kelompok.*',    'users'],
        ['Laporan & Rekap', 'laporan.index',   'laporan.*',     'laporan'],
    ];
@endphp

<aside class="sidebar">
    <nav>
        @foreach ($menu as [$label, $rute, $pola, $ikon])
            <a href="{{ route($rute) }}" class="{{ request()->routeIs($pola) ? 'aktif' : '' }}">
                <svg class="i"><use href="#i-{{ $ikon }}"/></svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</aside>