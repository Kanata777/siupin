{{-- Simpan sebagai: resources/views/absen/scan.blade.php (file baru) --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan Absensi - SIUPIN Nur Hidayah</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;justify-content:center;background:#eef2f6;
             font-family:'IBM Plex Sans',system-ui,sans-serif;color:#1b2a3f;padding:20px 16px}
        .kartu{width:100%;max-width:420px;background:#fff;border:1px solid #dde3ea;border-radius:14px;
             padding:24px 22px;box-shadow:0 6px 24px rgba(27,42,63,.08);align-self:flex-start}
        h1{font-size:19px;margin:0 0 6px}
        p{margin:0 0 14px;color:#5b6b7f;font-size:14px;line-height:1.5}
        #reader{width:100%;border-radius:10px;overflow:hidden;background:#0d1626;min-height:260px}
        .pesan{margin-top:14px;padding:11px 13px;border-radius:10px;font-size:13.5px;line-height:1.5;display:none}
        .pesan.galat{display:block;background:#fdecea;color:#b3261e;border:1px solid #f4c7c3}
        .pesan.info{display:block;background:#eef3fb;color:#1f4e8c;border:1px solid #d3dff2}
        button{width:100%;margin-top:14px;padding:13px;border:0;border-radius:10px;background:#1b2a3f;color:#fff;
               font:inherit;font-weight:600;font-size:15px;cursor:pointer;display:none}
    </style>
</head>
<body>
<main class="kartu">
    <h1>Scan QR Absensi</h1>
    <p>Arahkan kamera ke QR code rapat yang ditampilkan panitia.</p>

    <div id="reader"></div>
    <div id="pesan" class="pesan" role="status"></div>
    <button type="button" id="ulang">Coba buka kamera lagi</button>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
    (function () {
        var pesan = document.getElementById('pesan');
        var ulang = document.getElementById('ulang');
        var scanner = null;
        var sudahPindah = false;

        function tampil(teks, jenis) {
            pesan.textContent = teks;
            pesan.className = 'pesan ' + jenis;
        }

        // Hanya QR absensi dari alamat situs ini (atau alamat absensi di .env) yang dibuka
        var AWALAN = @json($awalan);
        var ASAL = [location.origin];
        var ASAL_ABSEN = @json($asalAbsen);
        if (ASAL_ABSEN) {
            try { ASAL.push(new URL(ASAL_ABSEN).origin); } catch (e) { /* abaikan */ }
        }
        var POLA = new RegExp('^' + (AWALAN ? '/' + AWALAN.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') : '') + '/\\d+/[a-f0-9]{16}$');

        function linkAbsen(teks) {
            try {
                var u = new URL(teks);
                if (ASAL.indexOf(u.origin) >= 0 && POLA.test(u.pathname)) {
                    return u.href;
                }
            } catch (e) { /* bukan URL */ }
            return null;
        }

        function mulai() {
            ulang.style.display = 'none';
            tampil('Membuka kamera…', 'info');

            if (typeof Html5Qrcode === 'undefined') {
                tampil('Pemindai belum termuat. Periksa koneksi internet lalu muat ulang halaman, atau scan QR memakai aplikasi kamera bawaan HP.', 'galat');
                return;
            }

            scanner = new Html5Qrcode('reader');
            scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                function (teks) {
                    if (sudahPindah) return;
                    var link = linkAbsen(teks);
                    if (!link) {
                        tampil('QR ini bukan QR absensi rapat.', 'galat');
                        return;
                    }
                    sudahPindah = true;
                    tampil('QR terbaca, membuka form absen…', 'info');
                    scanner.stop().catch(function () {}).then(function () { location.href = link; });
                },
                function () { /* tidak ada QR di frame: abaikan */ }
            ).then(function () {
                tampil('Kamera aktif. Arahkan ke QR code.', 'info');
            }).catch(function () {
                tampil('Kamera tidak dapat dibuka. Izinkan akses kamera pada browser (halaman harus dibuka lewat HTTPS), atau scan QR memakai aplikasi kamera bawaan HP.', 'galat');
                ulang.style.display = 'block';
            });
        }

        ulang.addEventListener('click', mulai);
        mulai();
    })();
</script>
</body>
</html>