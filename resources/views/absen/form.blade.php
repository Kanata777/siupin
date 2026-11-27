{{-- Simpan sebagai: resources/views/absen/form.blade.php (file baru) --}}
@php
    $pesanGalat = [
        'tidak_valid'     => 'Nomor WhatsApp tidak valid.',
        'tidak_terdaftar' => 'Nomor ini tidak terdaftar dalam undangan rapat. Hubungi panitia bila Anda merasa diundang.',
        'gagal'           => 'Kehadiran belum berhasil disimpan. Silakan coba lagi atau hubungi panitia.',
    ];
    $selesaiAbsen = $hasil && in_array($hasil['status'] ?? '', ['ok', 'sudah'], true);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Absensi Rapat - SIUPIN Nur Hidayah</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;
             background:#eef2f6;font-family:'IBM Plex Sans',system-ui,sans-serif;color:#1b2a3f;padding:20px 16px}
        .kartu{width:100%;max-width:420px;background:#fff;border:1px solid #dde3ea;border-radius:14px;
             padding:24px 22px;box-shadow:0 6px 24px rgba(27,42,63,.08)}
        .brand{display:flex;align-items:center;gap:10px;margin-bottom:18px;font-weight:700;font-size:15px}
        .brand small{display:block;font-weight:400;color:#5b6b7f;font-size:12px}
        .brand svg{width:34px;height:34px;flex:none}
        h1{font-size:19px;line-height:1.3;margin:0 0 10px}
        ul.info{list-style:none;margin:0 0 18px;padding:0;color:#5b6b7f;font-size:13.5px;line-height:1.7}
        ul.info b{color:#1b2a3f;font-weight:500}
        label{display:block;font-size:13.5px;font-weight:500;margin-bottom:7px}
        input[type=tel]{width:100%;padding:13px 14px;border:1.5px solid #cfd7e0;border-radius:10px;font:inherit;font-size:17px;letter-spacing:.3px}
        input[type=tel]:focus{outline:none;border-color:#2f7d4f;box-shadow:0 0 0 4px rgba(47,125,79,.16)}
        button{width:100%;margin-top:16px;padding:14px;border:0;border-radius:10px;background:#2f7d4f;color:#fff;
               font:inherit;font-weight:600;font-size:16px;cursor:pointer}
        button:hover{background:#256440}
        button[disabled]{opacity:.7;cursor:progress}
        .bantu{margin:10px 0 0;font-size:12.5px;color:#5b6b7f}
        .kotak{margin:0 0 16px;padding:12px 14px;border-radius:10px;font-size:14px;line-height:1.5}
        .kotak.galat{background:#fdecea;color:#b3261e;border:1px solid #f4c7c3}
        .kotak.info{background:#eef3fb;color:#1f4e8c;border:1px solid #d3dff2}
        .sukses{text-align:center;padding:8px 0 4px}
        .sukses svg{width:64px;height:64px}
        .sukses h2{margin:10px 0 4px;font-size:19px}
        .sukses p{margin:0;color:#5b6b7f;font-size:14px}
        .sukses .nama{margin-top:12px;font-size:17px;font-weight:600;color:#1b2a3f}
    </style>
</head>
<body>
<main class="kartu">
    <div class="brand">
        <svg viewBox="0 0 48 48" aria-hidden="true">
            <rect x="5" y="9" width="32" height="30" rx="3" fill="none" stroke="#1b2a3f" stroke-width="3.4"/>
            <path d="M5 19h32M14 5v8M28 5v8" stroke="#1b2a3f" stroke-width="3.4" stroke-linecap="round"/>
            <circle cx="34" cy="34" r="10" fill="#2f7d4f" stroke="#fff" stroke-width="2.4"/>
            <path d="M29 34l3.6 3.6 6.4-7.2" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div>SIUPIN <small>Absensi Rapat</small></div>
    </div>

    <h1>{{ $rapat->nama_rapat }}</h1>
    <ul class="info">
        <li><b>{{ $rapat->tanggal->translatedFormat('l, d F Y') }}</b></li>
        <li>Pukul <b>{{ $jam }}</b></li>
        @if ($rapat->tempat_rapat)
            <li>Tempat: <b>{{ $rapat->tempat_rapat }}</b></li>
        @endif
    </ul>

    @if ($tutup)
        <div class="kotak info">{{ $tutup }}</div>

    @elseif ($selesaiAbsen)
        <div class="sukses">
            <svg viewBox="0 0 64 64" aria-hidden="true">
                <circle cx="32" cy="32" r="30" fill="#2f7d4f"/>
                <path d="M19 33l9 9 17-19" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            @if ($hasil['status'] === 'ok')
                <h2>Kehadiran tercatat</h2>
                <p>Terima kasih, Anda sudah masuk daftar hadir.</p>
            @else
                <h2>Anda sudah absen</h2>
                <p>Kehadiran Anda sudah tercatat pukul {{ $hasil['waktu'] }}.</p>
            @endif
            <div class="nama">{{ $hasil['nama'] }}</div>
        </div>

    @else
        @if ($hasil && isset($pesanGalat[$hasil['status'] ?? '']))
            <div class="kotak galat" role="alert">{{ $pesanGalat[$hasil['status']] }}</div>
        @endif
        @error('nomor_wa')
            <div class="kotak galat" role="alert">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('absen.simpan', ['id' => $rapat->id_rapat, 'kode' => request()->route('kode')]) }}" id="formAbsen">
            @csrf
            <label for="nomor_wa">Nomor WhatsApp Anda</label>
            <input type="tel" id="nomor_wa" name="nomor_wa" inputmode="numeric" autocomplete="tel"
                   placeholder="Contoh: 0812xxxxxxx" value="{{ old('nomor_wa') }}" required autofocus>
            <p class="bantu">Gunakan nomor yang menerima undangan rapat ini.</p>
            <button type="submit" id="tombol">Saya Hadir</button>
        </form>
    @endif
</main>

<script>
    (function () {
        var f = document.getElementById('formAbsen');
        if (!f) return;
        f.addEventListener('submit', function () {
            var t = document.getElementById('tombol');
            t.disabled = true;
            t.textContent = 'Memproses...';
        });
    })();
</script>
</body>
</html>