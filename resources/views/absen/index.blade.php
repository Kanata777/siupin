{{-- Simpan sebagai: resources/views/absensi/index.blade.php (file baru) --}}
@extends('layouts.app')

@section('title', 'Daftar Hadir')
@section('subtitle', 'Pilih rapat untuk menampilkan QR absensi dan melihat peserta yang sudah hadir.')

@push('styles')
    <style>
        .abs-filter { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:0 0 16px; }
        .abs-filter label { font-size:13px; font-weight:600; }
        .abs-filter input[type=date] { height:38px; padding:0 12px; border:1px solid #d3dcea; border-radius:7px; font:inherit; font-size:13.5px; }
        .abs-btn { display:inline-flex; align-items:center; justify-content:center; height:38px; padding:0 16px; border:1px solid #d3dcea; border-radius:7px;
                   background:#fff; color:#1f2937; font:inherit; font-size:13.5px; font-weight:600; text-decoration:none; cursor:pointer; }
        .abs-btn:hover { background:#f1f5fb; }
        .abs-btn.utama { background:#1a5fd0; border-color:#1a5fd0; color:#fff; }
        .abs-btn.utama:hover { filter:brightness(.92); background:#1a5fd0; }
        .abs-tabel { overflow-x:auto; border:1px solid #d3dcea; border-radius:9px; background:#fff; }
        .abs-tabel table { width:100%; border-collapse:collapse; }
        .abs-tabel th { padding:10px 14px; background:#f6f9fd; border-bottom:1px solid #d3dcea; font-size:12.5px; font-weight:700; text-align:left; color:#14305e; white-space:nowrap; }
        .abs-tabel td { padding:11px 14px; border-bottom:1px solid #e6ecf5; font-size:13.5px; vertical-align:middle; }
        .abs-tabel tbody tr:last-child td { border-bottom:0; }
        .abs-tabel .c { text-align:center; }
        .abs-tabel .kosong { padding:30px 14px; text-align:center; color:#64748b; }
    </style>
@endpush

@section('content')
    <form method="GET" action="{{ route('hadir.index') }}" class="abs-filter">
        <label for="tanggal">Tanggal rapat</label>
        <input type="date" id="tanggal" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()">
        <a href="{{ route('hadir.index') }}" class="abs-btn">Hari ini</a>
    </form>

    <div class="abs-tabel">
        <table>
            <thead>
                <tr>
                    <th style="width:90px">Waktu</th>
                    <th>Nama Rapat</th>
                    <th>Tempat</th>
                    <th class="c">Hadir / Undangan</th>
                    <th class="c" style="width:190px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rapat as $r)
                    <tr>
                        <td>{{ substr((string) $r->waktu, 0, 5) }}</td>
                        <td>{{ $r->nama_rapat }}</td>
                        <td>{{ $r->tempat_rapat }}</td>
                        <td class="c">{{ $r->jml_hadir }} / {{ $r->jml_undangan }}</td>
                        <td class="c">
                            <a href="{{ route('hadir.show', $r->id_rapat) }}" class="abs-btn utama">Daftar Hadir &amp; QR</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="kosong">Tidak ada rapat pada {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection