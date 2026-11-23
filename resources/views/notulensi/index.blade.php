@extends('layouts.app')

@section('title', 'Notulensi Rapat')
@section('subtitle', 'Rapat yang sudah berlangsung dan status notulensinya.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/notulensi.css') }}?v={{ @filemtime(public_path('css/notulensi.css')) }}">
@endpush

@section('content')
    @php
        $tabs = ['semua' => 'Semua', 'belum' => 'Belum diisi', 'draft' => 'Draft', 'final' => 'Final'];
        $lencana = ['draft' => ['kuning', 'Draft'], 'final' => ['hijau', 'Final']];
    @endphp

    <div class="nt-bar">
        <nav class="nt-tabs" aria-label="Filter status notulensi">
            @foreach ($tabs as $kunci => $label)
                <a class="nt-tab {{ $tab === $kunci ? 'aktif' : '' }}"
                   href="{{ route('notulensi.index', array_filter(['s' => $kunci === 'semua' ? null : $kunci, 'q' => $q])) }}">
                    {{ $label }} <small>{{ $hitung[$kunci] }}</small>
                </a>
            @endforeach
        </nav>

        <form class="nt-cari" method="get" action="{{ route('notulensi.index') }}">
            @if ($tab !== 'semua') <input type="hidden" name="s" value="{{ $tab }}"> @endif
            <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama rapat" aria-label="Cari nama rapat">
            <button class="btn" type="submit">Cari</button>
        </form>
    </div>

    <div class="kartu nt-tabel">
        <table>
            <thead>
                <tr>
                    <th>Rapat</th>
                    <th>Tanggal</th>
                    <th>Tempat</th>
                    <th>Notulensi</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rapat as $r)
                    @php
                        $st = $r->status_notulensi;
                        [$warna, $teks] = $lencana[$st] ?? ['abu', 'Belum diisi'];
                    @endphp
                    <tr>
                        <td>{{ $r->nama_rapat }}<small>{{ $r->nama_kelompok }}</small></td>
                        <td>{{ \Carbon\Carbon::parse($r->tanggal)->translatedFormat('d F Y') }}<small>{{ substr($r->waktu, 0, 5) }} WIB</small></td>
                        <td>{{ $r->tempat_rapat }}</td>
                        <td><span class="nt-badge {{ $warna }}">{{ $teks }}</span></td>
                        <td class="aksi">
                            <a class="btn {{ $st ? '' : 'btn-biru' }}" href="{{ route('notulensi.edit', $r->id_rapat) }}">
                                {{ $st === 'final' ? 'Lihat' : ($st === 'draft' ? 'Lanjutkan' : 'Isi notulensi') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="nt-kosong">Belum ada rapat pada tampilan ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="nt-halaman">{{ $rapat->links() }}</div>
@endsection
