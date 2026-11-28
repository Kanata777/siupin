<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk - SIUPIN Nur Hidayah</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --navy:#14223a;
            --navy-2:#1b2a3f;
            --hijau:#2f7d4f;
            --hijau-tua:#256440;
            --cahaya:#f1d58a;
            --teks:#1b2a3f;
            --redup:#5b6b7f;
            --garis:#cfd7e0;
            --latar:#f6f8fb;
            --galat:#b3261e;
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            font-family:'IBM Plex Sans',system-ui,sans-serif;
            color:var(--teks);
            background:var(--latar);
            -webkit-font-smoothing:antialiased;
        }

        .halaman{min-height:100vh;display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr)}

        /* ====== Panel kiri ====== */
        .panel{
            position:relative;overflow:hidden;color:#fff;
            background:
                radial-gradient(60% 55% at 22% 18%, rgba(241,213,138,.28), rgba(241,213,138,0) 70%),
                linear-gradient(160deg,var(--navy-2),var(--navy));
            padding:44px 52px;
            display:flex;flex-direction:column;justify-content:space-between;
        }
        .panel .pola{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}
        .panel > *:not(.pola){position:relative}

        .brand{display:flex;align-items:center;gap:12px}
        .brand svg{width:46px;height:46px;flex:none}
        .brand b{display:block;font-size:21px;font-weight:700;letter-spacing:.4px;line-height:1.1}
        .brand small{color:rgba(255,255,255,.7);font-size:13px}

        .pesan{max-width:440px}
        .pesan h2{
            margin:0 0 14px;font-size:clamp(28px,3.2vw,40px);line-height:1.18;font-weight:600;letter-spacing:-.4px;
        }
        .pesan p{margin:0;color:rgba(255,255,255,.74);font-size:15.5px;line-height:1.65;max-width:38ch}

        .kaki{font-size:12.5px;color:rgba(255,255,255,.55)}

        /* ====== Panel kanan (form) ====== */
        .sisi-form{display:flex;align-items:center;justify-content:center;padding:32px 24px}
        .form-wrap{width:100%;max-width:380px}
        .form-wrap h1{margin:0 0 6px;font-size:26px;font-weight:600;letter-spacing:-.3px}
        .sub{margin:0 0 26px;color:var(--redup);font-size:14.5px;line-height:1.55}

        .info{
            margin:0 0 18px;padding:10px 12px;border-radius:8px;font-size:13.5px;
            background:#eaf5ee;color:var(--hijau-tua);border:1px solid #c6e2d1;
        }

        .field{margin-bottom:18px}
        label{display:block;font-size:13.5px;font-weight:500;margin-bottom:7px}
        .kotak{position:relative}
        .kotak > svg.ikon{
            position:absolute;left:13px;top:50%;transform:translateY(-50%);
            width:18px;height:18px;color:#8a98a9;pointer-events:none;
            transition:color .15s;
        }
        .kotak:focus-within > svg.ikon{color:var(--hijau)}

        input[type=email],input[type=password],input[type=text]{
            width:100%;padding:12px 14px 12px 42px;border:1.5px solid var(--garis);border-radius:10px;
            font:inherit;font-size:15px;color:inherit;background:#fff;
            transition:border-color .15s, box-shadow .15s;
        }
        input::placeholder{color:#9aa7b6}
        input:hover{border-color:#b6c2cf}
        input:focus{outline:none;border-color:var(--hijau);box-shadow:0 0 0 4px rgba(47,125,79,.16)}
        input.invalid{border-color:var(--galat)}
        input.invalid:focus{box-shadow:0 0 0 4px rgba(179,38,30,.14)}
        .kotak.sandi input{padding-right:46px}

        .lihat{
            position:absolute;right:6px;top:50%;transform:translateY(-50%);
            width:36px;height:36px;border:0;border-radius:8px;background:transparent;color:#7a8898;
            display:grid;place-items:center;cursor:pointer;
        }
        .lihat:hover{background:#eef2f6;color:var(--teks)}
        .lihat:focus-visible{outline:2px solid var(--hijau);outline-offset:1px}
        .lihat svg{width:19px;height:19px}
        .lihat .mata-tutup{display:none}
        .lihat[aria-pressed="true"] .mata-buka{display:none}
        .lihat[aria-pressed="true"] .mata-tutup{display:block}

        .galat{margin-top:7px;font-size:13px;color:var(--galat);display:flex;gap:6px;align-items:flex-start}
        .galat svg{width:15px;height:15px;flex:none;margin-top:2px}

        .ingat{
            display:flex;align-items:center;gap:10px;margin:2px 0 0;
            font-size:14px;font-weight:400;cursor:pointer;user-select:none;
        }
        .ingat input{
            appearance:none;-webkit-appearance:none;width:19px;height:19px;margin:0;flex:none;
            border:1.5px solid #aab6c3;border-radius:5px;background:#fff;display:grid;place-items:center;cursor:pointer;
            transition:background .15s,border-color .15s;
        }
        .ingat input::after{
            content:"";width:10px;height:6px;border-left:2.4px solid #fff;border-bottom:2.4px solid #fff;
            transform:rotate(-45deg) translate(1px,-1px);opacity:0;
        }
        .ingat input:checked{background:var(--hijau);border-color:var(--hijau)}
        .ingat input:checked::after{opacity:1}
        .ingat input:focus-visible{outline:2px solid var(--hijau);outline-offset:2px}

        button.masuk{
            width:100%;margin-top:26px;padding:13px 16px;border:0;border-radius:10px;
            background:var(--navy-2);color:#fff;font:inherit;font-weight:600;font-size:15.5px;cursor:pointer;
            display:flex;align-items:center;justify-content:center;gap:10px;
            transition:background .15s, transform .08s, box-shadow .15s;
            box-shadow:0 6px 16px rgba(20,34,58,.22);
        }
        button.masuk:hover{background:#26395a}
        button.masuk:active{transform:translateY(1px)}
        button.masuk:focus-visible{outline:3px solid rgba(47,125,79,.45);outline-offset:2px}
        button.masuk[disabled]{opacity:.85;cursor:progress}
        .putar{display:none;width:17px;height:17px;border:2.4px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:putar .7s linear infinite}
        button.masuk.memuat .putar{display:block}
        @keyframes putar{to{transform:rotate(360deg)}}

        .bantuan{margin:22px 0 0;text-align:center;font-size:13px;color:var(--redup)}

        /* ====== Animasi masuk (sekali saat halaman dibuka) ====== */
        .form-wrap{animation:muncul .5s ease-out both}
        @keyframes muncul{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
        @media (prefers-reduced-motion:reduce){
            .form-wrap{animation:none}
            .putar{animation-duration:1.6s}
            *{transition:none !important}
        }

        /* ====== Responsif ====== */
        @media (max-width:880px){
            .halaman{grid-template-columns:1fr;grid-template-rows:auto 1fr}
            .panel{padding:22px 24px 26px;gap:0}
            .pesan,.kaki{display:none}
            .sisi-form{align-items:flex-start;padding:28px 20px 40px}
        }
    </style>
</head>
<body>
<div class="halaman">

    <aside class="panel">
        <!-- Pola bintang delapan titik, sangat halus -->
        <svg class="pola" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
            <defs>
                <pattern id="bintang" width="72" height="72" patternUnits="userSpaceOnUse">
                    <g fill="none" stroke="#fff" stroke-opacity=".09" stroke-width="1.2">
                        <rect x="18" y="18" width="36" height="36"/>
                        <rect x="18" y="18" width="36" height="36" transform="rotate(45 36 36)"/>
                        <circle cx="36" cy="36" r="4" stroke-opacity=".12"/>
                    </g>
                </pattern>
                <linearGradient id="pudar" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#fff" stop-opacity="1"/>
                    <stop offset="1" stop-color="#fff" stop-opacity=".25"/>
                </linearGradient>
                <mask id="topeng"><rect width="100%" height="100%" fill="url(#pudar)"/></mask>
            </defs>
            <rect width="100%" height="100%" fill="url(#bintang)" mask="url(#topeng)"/>
        </svg>

        <div class="brand">
            <svg viewBox="0 0 48 48" aria-hidden="true">
                <rect x="5" y="9" width="32" height="30" rx="3" fill="none" stroke="#fff" stroke-width="3.4"/>
                <path d="M5 19h32M14 5v8M28 5v8" stroke="#fff" stroke-width="3.4" stroke-linecap="round"/>
                <circle cx="34" cy="34" r="10" fill="#2f7d4f" stroke="#14223a" stroke-width="2.4"/>
                <path d="M29 34l3.6 3.6 6.4-7.2" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div><b>SIUPIN</b><small>Nur Hidayah</small></div>
        </div>


        <div class="kaki">Puskomdatin &#64;{{ date('Y') }}</div>
    </aside>

    <main class="sisi-form">
        <div class="form-wrap">
            <h1>Selamat datang kembali</h1>
            <p class="sub">Masuk dengan akun SIUPIN Anda untuk melanjutkan.</p>

            @if (session('status'))
                <div class="info" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.proses') }}" id="formMasuk" novalidate>
                @csrf

                <div class="field">
                    <label for="username">Username</label>
                    <div class="kotak">
                        <svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-3.5 3.6-6 8-6s8 2.5 8 6"/></svg>
                        <input type="text" id="username" name="username" value="{{ old('username') }}"
                               placeholder="Masukkan username" required autofocus autocomplete="username"
                               autocapitalize="none" spellcheck="false"
                               class="@error('username') invalid @enderror"
                               @error('username') aria-invalid="true" aria-describedby="galat-username" @enderror>
                    </div>
                    @error('username')
                        <div class="galat" id="galat-username">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16.2v.1"/></svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="field">
                    <label for="password">Kata sandi</label>
                    <div class="kotak sandi">
                        <svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 018 0v2.5"/></svg>
                        <input type="password" id="password" name="password"
                               placeholder="Masukkan kata sandi" required autocomplete="current-password"
                               class="@error('password') invalid @enderror"
                               @error('password') aria-invalid="true" aria-describedby="galat-password" @enderror>
                        <button type="button" class="lihat" id="lihatSandi" aria-label="Tampilkan kata sandi" aria-pressed="false">
                            <svg class="mata-buka" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="mata-tutup" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.6 10.6 0 0112 6c6.4 0 10 6 10 6a17 17 0 01-3.3 3.9M6.6 7.6A16.6 16.6 0 002 12s3.6 6 10 6c1.5 0 2.8-.3 4-.8"/><path d="M9.9 9.9a3 3 0 004.2 4.2"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <div class="galat" id="galat-password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16.2v.1"/></svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <label class="ingat">
                    <input type="checkbox" name="ingat" value="1" @checked(old('ingat'))>
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="masuk" id="tombolMasuk">
                    <span class="putar" aria-hidden="true"></span>
                    <span class="teks-tombol">Masuk</span>
                </button>
            </form>

            <p class="bantuan">Lupa kata sandi? Hubungi admin SIUPIN.</p>
        </div>
    </main>
</div>

<script>
    (function () {
        var sandi = document.getElementById('password');
        var lihat = document.getElementById('lihatSandi');
        var form  = document.getElementById('formMasuk');
        var tombol = document.getElementById('tombolMasuk');
        var teks = tombol.querySelector('.teks-tombol');

        lihat.addEventListener('click', function () {
            var tampil = sandi.type === 'password';
            sandi.type = tampil ? 'text' : 'password';
            lihat.setAttribute('aria-pressed', tampil ? 'true' : 'false');
            lihat.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });

        function reset() {
            tombol.disabled = false;
            tombol.classList.remove('memuat');
            teks.textContent = 'Masuk';
        }

        form.addEventListener('submit', function () {
            tombol.disabled = true;
            tombol.classList.add('memuat');
            teks.textContent = 'Memproses...';
        });

        // Jika pengguna menekan tombol "kembali" di browser
        window.addEventListener('pageshow', function (e) { if (e.persisted) reset(); });
    })();
</script>
</body>
</html>