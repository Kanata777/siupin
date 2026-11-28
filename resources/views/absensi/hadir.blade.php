{{-- Simpan sebagai: resources/views/absensi/hadir.blade.php (file baru) --}}
@extends('layouts.app')

@section('title', 'Daftar Hadir')
@section('subtitle', $rapat->nama_rapat . ' · ' . $rapat->tanggal->translatedFormat('d F Y') . ', ' . $jam . ($rapat->tempat_rapat ? ' · ' . $rapat->tempat_rapat : ''))

@push('styles')
    <style>
        .abs-grid { display:grid; grid-template-columns:minmax(0,320px) minmax(0,1fr); gap:16px; margin-bottom:16px; align-items:stretch; }
        .abs-dua { grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; }
        .abs-kartu { background:#fff; border:1px solid #d3dcea; border-radius:10px; padding:16px 18px; }
        .abs-kartu h3 { margin:0 0 12px; font-size:14.5px; font-weight:700; color:#14305e; }
        .abs-qrcard { text-align:center; }
        .abs-judul-rapat { margin:0 0 12px; font-size:13.5px; font-weight:600; }
        .abs-qr { display:flex; justify-content:center; padding:12px; background:#fff; border:1px solid #e6ecf5; border-radius:10px; }
        .abs-qr img, .abs-qr canvas { max-width:100%; height:auto; }
        .abs-bantu { margin:10px 0 0; font-size:12.5px; line-height:1.5; color:#64748b; }
        .abs-aksi { display:flex; flex-wrap:wrap; justify-content:center; gap:8px; margin-top:12px; }
        .abs-btn { display:inline-flex; align-items:center; justify-content:center; height:36px; padding:0 14px; border:1px solid #d3dcea; border-radius:7px;
                   background:#fff; color:#1f2937; font:inherit; font-size:13px; font-weight:600; cursor:pointer; }
        .abs-btn:hover { background:#f1f5fb; }

        .abs-angka { display:flex; align-items:baseline; flex-wrap:wrap; gap:6px 10px; }
        .abs-angka > b { font-size:44px; line-height:1; color:#1b2a3f; }
        .abs-angka span { font-size:14px; color:#64748b; }
        .abs-angka span b { color:#1b2a3f; }
        .abs-bar { height:10px; margin:14px 0 6px; border-radius:6px; background:#e6ecf5; overflow:hidden; }
        .abs-bar i { display:block; height:100%; width:0; background:#2f7d4f; transition:width .3s ease; }

        .abs-scroll { max-height:420px; overflow:auto; border:1px solid #e6ecf5; border-radius:8px; }
        .abs-scroll table { width:100%; border-collapse:collapse; }
        .abs-scroll th { position:sticky; top:0; padding:8px 12px; background:#f6f9fd; border-bottom:1px solid #d3dcea; font-size:12.5px; font-weight:700; text-align:left; color:#14305e; }
        .abs-scroll td { padding:8px 12px; border-bottom:1px solid #eef2f8; font-size:13px; }
        .abs-scroll tbody tr:last-child td { border-bottom:0; }
        .abs-scroll .kosong { padding:22px 12px; text-align:center; color:#64748b; }
        .abs-scroll small { color:#64748b; }

        @@media (max-width:900px) {
            .abs-grid, .abs-dua { grid-template-columns:1fr; }
        }

        /* Cetak: hanya QR yang dicetak */
        @@media print {
            body * { visibility:hidden; }
            .abs-qrcard, .abs-qrcard * { visibility:visible; }
            .abs-qrcard { position:absolute; left:0; top:0; width:100%; border:0; }
            .abs-aksi, .abs-bantu { display:none; }
        }
    </style>
@endpush

@section('content')
    @if ($alasanTutup)
        <div class="alert info">Peserta belum bisa absen: {{ $alasanTutup }}</div>
    @endif

    <div class="abs-grid">
        <section class="abs-kartu abs-qrcard">
            <h3>Scan untuk absen</h3>
            <p class="abs-judul-rapat">{{ $rapat->nama_rapat }}</p>
            <div id="absQr" class="abs-qr"></div>
            <p class="abs-bantu">
                Peserta scan QR ini langsung di lokasi rapat, lalu memasukkan nomor WhatsApp yang menerima undangan.
                @if ($berganti) QR berganti otomatis setiap menit, jadi tampilkan di layar atau proyektor (tidak untuk dicetak atau difoto lalu dibagikan). @endif
            </p>
            <p class="abs-bantu">Halaman scan: <a href="{{ $urlScan }}" target="_blank" rel="noopener">{{ $urlScan }}</a></p>
            @unless ($berganti)
                <div class="abs-aksi">
                    <button type="button" class="abs-btn" onclick="window.print()">Cetak QR</button>
                </div>
            @endunless
        </section>

        <section class="abs-kartu">
            <h3>Kehadiran</h3>
            <div class="abs-angka">
                <b id="jmlHadir">0</b>
                <span>hadir dari <b id="jmlUndangan">0</b> undangan (<span id="persen">0</span>%)</span>
            </div>
            <div class="abs-bar"><i id="barHadir"></i></div>
            <p class="abs-bantu" id="infoUpdate">Hanya anggota yang diundang ke rapat ini yang dihitung. Daftar diperbarui otomatis setiap 10 detik.</p>
        </section>
    </div>

    <div class="abs-grid abs-dua">
        <section class="abs-kartu">
            <h3>Sudah hadir</h3>
            <div class="abs-scroll">
                <table>
                    <thead><tr><th style="width:46px">No</th><th>Nama</th><th style="width:70px">Waktu</th></tr></thead>
                    <tbody id="tbHadir"></tbody>
                </table>
            </div>
        </section>

        <section class="abs-kartu">
            <h3>Belum hadir</h3>
            <div class="abs-scroll">
                <table>
                    <thead><tr><th style="width:46px">No</th><th>Nama</th><th>Nomor WA</th></tr></thead>
                    <tbody id="tbBelum"></tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        (function () {
            var URL_QR    = @json($urlQr);
            var URL_DATA  = @json($urlData);
            var awal      = @json($awal);

            // ---- QR ----
            var kotakQr = document.getElementById('absQr');
            var qr = null;
            if (typeof QRCode !== 'undefined') {
                qr = new QRCode(kotakQr, { text: URL_QR, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
            } else {
                kotakQr.textContent = 'QR belum termuat. Periksa koneksi internet lalu muat ulang halaman.';
            }

            // ---- Tampilan daftar ----
            function isiTabel(tbody, baris, kosong) {
                tbody.replaceChildren();
                if (!baris.length) {
                    var tr = document.createElement('tr');
                    var td = document.createElement('td');
                    td.colSpan = 3;
                    td.className = 'kosong';
                    td.textContent = kosong;
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                    return;
                }
                baris.forEach(function (kolom, i) {
                    var tr = document.createElement('tr');
                    [String(i + 1)].concat(kolom).forEach(function (teks) {
                        var td = document.createElement('td');
                        td.textContent = teks;
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
            }

            function tampil(d) {
                // QR berganti tiap menit: segarkan bila alamat QR dari server berubah
                if (d.qr && qr && d.qr !== URL_QR) { URL_QR = d.qr; qr.makeCode(d.qr); }

                document.getElementById('jmlHadir').textContent = d.jumlah_hadir;
                document.getElementById('jmlUndangan').textContent = d.jumlah_undangan;
                document.getElementById('persen').textContent = d.persen;
                document.getElementById('barHadir').style.width = d.persen + '%';

                isiTabel(document.getElementById('tbHadir'),
                    d.hadir.map(function (h) { return [h.unit ? h.nama + ' (' + h.unit + ')' : h.nama, h.waktu]; }),
                    'Belum ada yang hadir.');
                isiTabel(document.getElementById('tbBelum'),
                    d.belum.map(function (b) { return [b.nama, b.nomor]; }),
                    'Semua undangan sudah hadir.');
            }

            function muat() {
                fetch(URL_DATA, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                    .then(tampil)
                    .catch(function () { /* abaikan; coba lagi pada putaran berikutnya */ });
            }

            tampil(awal);
            setInterval(muat, 10000);
        })();
    </script>
@endpush