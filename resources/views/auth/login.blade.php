<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk - SIUPIN Nur Hidayah</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
             background:#eef2f6;font-family:'IBM Plex Sans',system-ui,sans-serif;color:#1b2a3f;padding:16px}
        .kartu-login{width:100%;max-width:400px;background:#fff;border:1px solid #dde3ea;border-radius:14px;
             padding:32px 28px;box-shadow:0 6px 24px rgba(27,42,63,.08)}
        .brand{display:flex;align-items:center;gap:12px;margin-bottom:22px}
        .brand svg{width:44px;height:44px;flex:none}
        .brand b{display:block;font-size:20px;letter-spacing:.3px}
        .brand small{color:#5b6b7f;font-size:13px}
        h1{font-size:18px;margin:0 0 4px}
        p.sub{margin:0 0 20px;color:#5b6b7f;font-size:13.5px}
        label{display:block;font-size:13px;font-weight:500;margin:14px 0 6px}
        input[type=email],input[type=password]{width:100%;padding:10px 12px;border:1px solid #c9d2dc;border-radius:8px;
             font:inherit;font-size:14px;color:inherit;background:#fff}
        input:focus{outline:2px solid #2f7d4f55;border-color:#2f7d4f}
        .ingat{display:flex;align-items:center;gap:8px;margin:14px 0 0;font-size:13px;font-weight:400}
        .galat{margin-top:6px;font-size:12.5px;color:#b3261e}
        button.masuk{width:100%;margin-top:22px;padding:11px;border:0;border-radius:8px;background:#1b2a3f;color:#fff;
             font:inherit;font-weight:600;font-size:14.5px;cursor:pointer}
        button.masuk:hover{background:#2a3d57}
    </style>
</head>
<body>
    <main class="kartu-login">
        <div class="brand">
            <svg viewBox="0 0 48 48" aria-hidden="true">
                <rect x="5" y="9" width="32" height="30" rx="3" fill="none" stroke="#1b2a3f" stroke-width="3.4"/>
                <path d="M5 19h32M14 5v8M28 5v8" stroke="#1b2a3f" stroke-width="3.4" stroke-linecap="round"/>
                <circle cx="34" cy="34" r="10" fill="#2f7d4f" stroke="#fff" stroke-width="2.4"/>
                <path d="M29 34l3.6 3.6 6.4-7.2" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div><b>SIUPIN</b><small>Nur Hidayah</small></div>
        </div>

        <h1>Masuk</h1>
        <p class="sub">Gunakan akun SIUPIN Anda untuk melanjutkan.</p>

        <form method="POST" action="{{ route('login.proses') }}" novalidate>
            @csrf

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email') <div class="galat">{{ $message }}</div> @enderror

            <label for="password">Kata sandi</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            @error('password') <div class="galat">{{ $message }}</div> @enderror

            <label class="ingat">
                <input type="checkbox" name="ingat" value="1" @checked(old('ingat'))>
                Ingat saya di perangkat ini
            </label>

            <button type="submit" class="masuk">Masuk</button>
        </form>
    </main>
</body>
</html>
