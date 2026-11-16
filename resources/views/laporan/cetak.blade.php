<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan SIUPIN Nur Hidayah</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 11px/1.45 "Segoe UI", Arial, sans-serif; color: #1c2b44; }
        .bar { position: sticky; top: 0; display: flex; align-items: center; gap: 12px; padding: 10px 16px; background: #1a5fd0; color: #fff; }
        .bar button { padding: 7px 16px; border: 0; border-radius: 6px; background: #fff; color: #1a5fd0; font-weight: 700; cursor: pointer; }
        .halaman { padding: 16px; }
        .kop { border-bottom: 2px solid #1a5fd0; padding-bottom: 8px; margin-bottom: 14px; }
        .kop h1 { margin: 0; font-size: 20px; color: #1a5fd0; }
        .kop .sub { font-weight: 600; font-size: 13px; }
        .kop small { color: #5c6b82; }
        section + section { page-break-before: always; }
        h2 { margin: 0 0 4px; font-size: 14px; color: #14305e; }
        h2 small { font-weight: 400; color: #5c6b82; }
        .catatan { margin: 0 0 6px; color: #a85a00; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #aab7ca; padding: 3px 6px; vertical-align: top; text-align: left; }
        th { background: #e3edfb; color: #14305e; }
        td:first-child, th:first-child { width: 32px; text-align: center; }
        @media print { .bar { display: none; } .halaman { padding: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
        <span>Pada dialog cetak, pilih printer <b>"Simpan sebagai PDF"</b> untuk mendapatkan berkas PDF.</span>
    </div>

    <div class="halaman">
        <header class="kop">
            <h1>SIUPIN Nur Hidayah</h1>
            <div class="sub">Laporan &amp; Rekap Rapat</div>
            <small>{{ $filter }} · Dicetak {{ $dicetak }} oleh {{ $pengguna }}</small>
        </header>

        @foreach ($data as $d)
            <section>
                <h2>{{ $d['judul'] }} <small>({{ number_format($d['total'], 0, ',', '.') }} baris)</small></h2>

                @if ($d['total'] > count($d['baris']))
                    <p class="catatan">Menampilkan {{ number_format(count($d['baris']), 0, ',', '.') }} dari {{ number_format($d['total'], 0, ',', '.') }} baris. Unduh Excel atau CSV untuk data lengkap.</p>
                @endif

                <table>
                    <thead>
                        <tr>@foreach ($d['kolom'] as $k)<th>{{ $k }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @forelse ($d['baris'] as $i => $b)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                @foreach ($b as $sel)<td>{{ $sel }}</td>@endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($d['kolom']) }}" style="text-align:center">Tidak ada data pada filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>

    <script>
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 500); });
    </script>
</body>
</html>