<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIUPIN') - SIUPIN Nur Hidayah</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Pengaman: ikon tidak akan membesar walau file CSS gagal dimuat --}}
    <style>.i{width:18px;height:18px;flex:none;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}</style>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @stack('styles')
    {{-- Harus paling akhir: menyeragamkan gaya halaman lama (jadwal, kelompok, buat rapat) --}}
    <link rel="stylesheet" href="{{ asset('css/tema-halaman.css') }}?v={{ @filemtime(public_path('css/tema-halaman.css')) }}">
</head>
<body class="@yield('body_class')">
    @include('partials.ikon-sprite')
    @include('partials.topbar')
    @include('partials.sidebar')

    <main class="konten">
        <div class="halaman-putih">
            @hasSection('page_head')
                @yield('page_head')
            @else
                <h1>@yield('title')</h1>
                <p class="sub">@yield('subtitle')</p>
            @endif

            @if (session('sukses'))
                <div class="alert sukses">{{ session('sukses') }}</div>
            @endif

            @if (session('galat'))
                <div class="alert gagal">{{ session('galat') }}</div>
            @endif

            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>