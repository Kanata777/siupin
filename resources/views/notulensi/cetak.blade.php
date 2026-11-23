<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Notulensi {{ $rapat->nama_rapat }}</title>
    <style>
        @page{size:A4;margin:18mm 16mm}
        *{box-sizing:border-box}
        body{margin:0;font:12pt/1.5 "IBM Plex Sans","Segoe UI",Arial,sans-serif;color:#222b38}
        h1{margin:0;font-size:17pt;color:#1b2a3f}
        h2{margin:18px 0 6px;font-size:12.5pt;color:#1b2a3f;border-bottom:1px solid #d9dde3;padding-bottom:3px}
        .kop{border-bottom:2px solid #1b2a3f;padding-bottom:8px;margin-bottom:12px}
        .kop small{display:block;color:#667085}
        .draft{display:inline-block;margin-top:6px;padding:1px 8px;border:1px solid #a86a00;color:#a86a00;font-size:10pt}
        table{width:100%;border-collapse:collapse;font-size:11pt}
        th,td{padding:5px 8px;border:1px solid #d9dde3;text-align:left;vertical-align:top}
        th{background:#eceff3}
        .meta td{border:0;padding:2px 8px 2px 0}.meta td:first-child{width:130px;color:#667085}
        .teks{white-space:pre-line;margin:0}
        .foot{margin-top:22px;font-size:9.5pt;color:#667085}
        tr{page-break-inside:avoid}
        @media screen{body{max-width:800px;margin:24px auto;padding:0 16px}}
    </style>
</head>
<body onload="window.print()">
    <div class="kop">
        <small>SIUPIN Nur Hidayah</small>
        <h1>Notulensi Rapat</h1>
        @unless ($notulensi->sudahFinal()) <span class="draft">DRAFT, belum difinalisasi</span> @endunless
    </div>

    <table class="meta">
        <tr><td>Nama rapat</td><td>{{ $rapat->nama_rapat }}</td></tr>
        <tr><td>Hari, tanggal</td><td>{{ \Carbon\Carbon::parse($rapat->tanggal)->translatedFormat('l, d F Y') }}</td></tr>
        <tr><td>Waktu</td><td>{{ substr($rapat->waktu, 0, 5) }}{{ $rapat->waktu_selesai ? ' – ' . substr($rapat->waktu_selesai, 0, 5) : '' }} WIB</td></tr>
        <tr><td>Tempat</td><td>{{ $rapat->tempat_rapat }}</td></tr>
        <tr><td>Penyelenggara</td><td>{{ $rapat->pengundang }}</td></tr>
        <tr><td>Peserta hadir</td><td>{{ $hadir->count() }}@if ($rapat->jumlah_peserta) dari {{ $rapat->jumlah_peserta }} undangan @endif</td></tr>
    </table>

    <h2>Agenda rapat</h2><p class="teks">{{ $notulensi->agenda }}</p>
    <h2>Jalannya pembahasan</h2><p class="teks">{{ $notulensi->pembahasan }}</p>
    <h2>Hasil / keputusan rapat</h2><p class="teks">{{ $notulensi->keputusan }}</p>

    @if ($notulensi->tindakLanjut->isNotEmpty())
        <h2>Rencana tindak lanjut</h2>
        <table>
            <thead><tr><th>No</th><th>Tindak lanjut</th><th>PIC</th><th>Target waktu</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($notulensi->tindakLanjut as $n => $t)
                    <tr>
                        <td>{{ $n + 1 }}</td><td>{{ $t->uraian }}</td><td>{{ $t->pic }}</td>
                        <td>{{ $t->target_tanggal?->translatedFormat('d M Y') }}</td>
                        <td>{{ \App\Models\TindakLanjutNotulensi::STATUS[$t->status] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($hadir->isNotEmpty())
        <h2>Daftar hadir</h2>
        <table>
            <thead><tr><th>No</th><th>Nama</th><th>Unit</th></tr></thead>
            <tbody>
                @foreach ($hadir as $n => $h)
                    <tr><td>{{ $n + 1 }}</td><td>{{ $h->nama_lengkap }}</td><td>{{ $h->nama_unit }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="foot">Dicetak dari SIUPIN pada {{ now()->translatedFormat('d F Y, H:i') }}.</p>
</body>
</html>
