<header class="topbar">
    <button class="burger" type="button" onclick="document.body.classList.toggle('buka')" aria-label="Menu">
        <svg class="i"><use href="#i-menu"/></svg>
    </button>

    <a href="{{ route('dashboard') }}" class="brand">
        <svg class="brand-logo" viewBox="0 0 48 48" aria-hidden="true">
            <rect x="5" y="9" width="32" height="30" rx="3" fill="none" stroke="#1b2a3f" stroke-width="3.4"/>
            <path d="M5 19h32M14 5v8M28 5v8" stroke="#1b2a3f" stroke-width="3.4" stroke-linecap="round"/>
            <circle cx="34" cy="34" r="10" fill="#2f7d4f" stroke="#fff" stroke-width="2.4"/>
            <path d="M29 34l3.6 3.6 6.4-7.2" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="brand-teks"><b>SIUPIN</b><small>Nur Hidayah</small></span>
    </a>

    <div class="tagline">Sistem undangan, presensi dan notulensi rapat</div>

    <div class="topbar-kanan">
        <button class="bell" type="button" aria-label="Notifikasi">
            <svg class="i"><use href="#i-lonceng"/></svg>
            <span class="bell-titik"></span>
        </button>
        <div class="user">
            <span class="user-ava"><svg class="i"><use href="#i-user"/></svg></span>
            <span class="user-nama">{{ auth()->user()->name ?? 'SDM YNH' }}</span>
        </div>
    </div>
</header>