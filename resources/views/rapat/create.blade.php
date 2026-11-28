@extends('layouts.app')

@section('title', 'Buat Rapat / Undangan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rapat.css') }}?v={{ @filemtime(public_path('css/rapat.css')) }}">
    <style>
        /* =====================================================================
           Halaman Buat Rapat — semua gaya dibatasi di .rapat-buat
           ===================================================================== */
        .rapat-buat {
            --rb-line:#d3dcea; --rb-line-soft:#e6ecf5; --rb-soft:#f6f9fd;
            --rb-blue:var(--biru,#1a5fd0); --rb-navy:var(--navy,#14305e);
            --rb-ink:var(--teks,#1f2937); --rb-mute:var(--abu,#64748b);
            --rb-h:38px; --rb-r:7px;
            --wk-pagi:#0f8b8d; --wk-siang:#d98e04;
        }
        .rapat-buat *, .rapat-buat *::before, .rapat-buat *::after { box-sizing:border-box; }
        .rapat-buat [hidden] { display:none !important; }

        /* ---------- Panel ---------- */
        .rapat-buat .panel { background:#fff; border:1px solid var(--rb-line); border-radius:10px; }
        .rapat-buat .grid-dua { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(0,1fr); gap:16px; align-items:stretch; }
        .rapat-buat .grid-dua > .panel { display:flex; flex-direction:column; min-width:0; }
        .rapat-buat .judul-panel {
            display:flex; align-items:center; gap:10px; margin:0; height:46px; padding:0 18px;
            background:var(--rb-soft); border-bottom:1px solid var(--rb-line); border-radius:9px 9px 0 0;
            font-size:14.5px; font-weight:700; color:var(--rb-navy);
        }
        .rapat-buat .judul-panel .i { width:19px; height:19px; color:var(--rb-blue); }
        .rapat-buat .isi-panel { flex:1; display:flex; flex-direction:column; gap:14px; padding:18px; }

        /* ---------- Label & kolom isian ---------- */
        .rapat-buat .f { min-width:0; }
        .rapat-buat .f-baris { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; align-items:start; }
        .rapat-buat .isi-panel label[for],
        .rapat-buat .f-label { display:block; margin:0 0 6px; font-size:12.5px; font-weight:600; color:var(--rb-ink); }
        .rapat-buat .wajib { color:#d92d27; }
        .rapat-buat .kosong-kecil { font-size:13px; color:var(--rb-mute); }
        .rapat-buat p.bantu { margin:0; font-size:12.5px; line-height:1.45; color:var(--rb-mute); }
        .rapat-buat .bantu-inline { font-size:12px; color:var(--rb-mute); }

        /* ---------- Input / select / textarea ---------- */
        .rapat-buat input[type=text], .rapat-buat input[type=search], .rapat-buat input[type=tel],
        .rapat-buat input[type=url], .rapat-buat input[type=date], .rapat-buat select {
            display:block; width:100%; height:var(--rb-h); margin:0; padding:0 12px;
            border:1px solid var(--rb-line); border-radius:var(--rb-r); background:#fff; color:var(--rb-ink);
            font:inherit; font-size:13.5px; line-height:normal;
        }
        .rapat-buat textarea {
            display:block; width:100%; min-height:72px; margin:0; padding:9px 12px; resize:vertical;
            border:1px solid var(--rb-line); border-radius:var(--rb-r); background:#fff; color:var(--rb-ink);
            font:inherit; font-size:13.5px; line-height:1.45;
        }
        .rapat-buat input::placeholder, .rapat-buat textarea::placeholder { color:#94a1b6; }
        .rapat-buat input:focus-visible, .rapat-buat select:focus-visible, .rapat-buat textarea:focus-visible {
            outline:2px solid rgba(26,95,208,.35); outline-offset:0; border-color:var(--rb-blue);
        }

        /* Pilihan dikunci (mis. Tempat Rapat saat metode Daring) */
        .rapat-buat select:disabled { background:#f1f5f9; color:#94a3b8; cursor:not-allowed; }
        .rapat-buat .ikon-input:has(select:disabled) > .i { opacity:.45; }

        /* ---------- Input dengan ikon di kiri ---------- */
        .rapat-buat .ikon-input, .rapat-buat .wk-pilih { position:relative; margin:0; min-width:0; }
        .rapat-buat .ikon-input { flex:1; }
        .rapat-buat .ikon-input > .i, .rapat-buat .wk-pilih > svg.i {
            position:absolute; left:11px; top:50%; transform:translateY(-50%);
            width:16px; height:16px; color:#5b6b86; pointer-events:none; z-index:1;
        }
        .rapat-buat .ikon-input select, .rapat-buat .wk-pilih select, .rapat-buat .wk-pilih input { padding-left:34px; cursor:pointer; font-variant-numeric:tabular-nums; }
        .rapat-buat .ikon-input select:disabled { cursor:not-allowed; }
        .rapat-buat .wk-pilih select option:disabled { color:#9aa3b2; }

        /* ---------- Jadwal ---------- */
        .rapat-buat .wk {
            display:flex; flex-direction:column; gap:12px; padding:14px 16px;
            background:var(--rb-soft); border:1px solid var(--rb-line); border-radius:9px;
        }
        .rapat-buat .wk-label { display:block; margin:0; font-size:13px; font-weight:700; color:var(--rb-navy); }
        .rapat-buat .wk-baris { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; }
        .rapat-buat .wk-kolom small { display:block; margin:0 0 5px; font-size:12px; color:var(--rb-mute); }

        .rapat-buat .wk-preset { display:flex; flex-wrap:wrap; gap:8px; margin:0; }
        .rapat-buat .wk-chip {
            height:28px; padding:0 12px; border:1px solid var(--rb-line); border-radius:6px;
            background:#fff; color:var(--rb-ink); font:inherit; font-size:12.5px; font-weight:500; cursor:pointer;
        }
        .rapat-buat .wk-chip:hover { border-color:var(--rb-blue); color:var(--rb-blue); }
        .rapat-buat .wk-chip.aktif { background:var(--rb-blue); border-color:var(--rb-blue); color:#fff; }
        .rapat-buat .wk-chip:focus-visible { outline:2px solid rgba(26,95,208,.45); outline-offset:2px; }
        .rapat-buat .wk-chip:disabled { opacity:.45; background:#f1f5f9; cursor:not-allowed; }
        .rapat-buat .wk-chip:disabled:hover { border-color:var(--rb-line); color:var(--rb-ink); }

        .rapat-buat .wk-garis { margin:2px 2px 0; }
        .rapat-buat .wk-jalur { position:relative; height:8px; border-radius:4px; background:#dbe3ef; }
        .rapat-buat .wk-isi {
            position:absolute; top:0; bottom:0; left:0; width:0; border-radius:4px; background:var(--wk-pagi);
            transition:left .2s ease, width .2s ease, background .2s ease;
        }
        .rapat-buat .wk-isi.siang { background:var(--wk-siang); }
        .rapat-buat .wk-isi.fullday { background:linear-gradient(90deg,var(--wk-pagi) 0,var(--wk-pagi) 40%,var(--wk-siang) 62%); }
        .rapat-buat .wk-batas { position:absolute; top:-5px; bottom:-5px; left:62.5%; width:0; border-left:2px dashed rgba(90,100,120,.55); }
        .rapat-buat .wk-skala { position:relative; height:16px; margin-top:6px; font-size:11.5px; color:var(--rb-mute); font-variant-numeric:tabular-nums; }
        .rapat-buat .wk-skala span { position:absolute; transform:translateX(-50%); white-space:nowrap; }
        .rapat-buat .wk-skala span:first-child { transform:none; }
        .rapat-buat .wk-skala span:last-child { transform:translateX(-100%); }

        .rapat-buat .wk-ringkas { display:flex; flex-wrap:wrap; align-items:center; gap:6px 10px; margin:0; }
        .rapat-buat .wk-ringkas .judul { margin:0; font-size:12.5px; font-weight:600; }
        .rapat-buat .badge-sesi {
            display:inline-flex; align-items:center; height:24px; padding:0 10px; border-radius:5px;
            background:#e8edf5; color:#475569; font-size:12px; font-weight:700;
        }
        .rapat-buat .badge-sesi.pagi { background:#d6efef; color:#0b6b6d; }
        .rapat-buat .badge-sesi.siang { background:#fcebc7; color:#865800; }
        .rapat-buat .badge-sesi.fullday, .rapat-buat .badge-sesi.seharian { background:#dbe6fb; color:#1a4fa8; }
        .rapat-buat .wk-hint { margin:0; font-size:12px; line-height:1.45; color:var(--rb-mute); }
        .rapat-buat .wk-peringatan { color:#865800; font-weight:600; }

        .sembunyi-visual { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }

        /* ---------- Tempat & metode ---------- */
        .rapat-buat .baris-tempat { display:flex; align-items:center; gap:8px; margin:0; }
        .rapat-buat .badge-tempat { display:none; } /* info yang sama sudah tampil di baris status di bawahnya */

        .rapat-buat .metode-grup {
            display:grid; grid-template-columns:1fr 1fr; height:var(--rb-h);
            border:1px solid var(--rb-line); border-radius:var(--rb-r); overflow:hidden; background:#fff;
        }
        .rapat-buat .metode-grup .radio {
            position:relative; display:flex; align-items:center; justify-content:center; gap:7px; margin:0;
            font-size:13px; font-weight:600; color:var(--rb-ink); cursor:pointer; white-space:nowrap;
        }
        .rapat-buat .metode-grup .radio + .radio { border-left:1px solid var(--rb-line); }
        .rapat-buat .metode-grup .radio .i { width:16px; height:16px; color:#5b6b86; }
        .rapat-buat .metode-grup .radio input { position:absolute; opacity:0; width:1px; height:1px; margin:0; pointer-events:none; }
        .rapat-buat .metode-grup .radio:hover { background:#f3f7fd; }
        .rapat-buat .metode-grup .radio:has(input:checked) { background:#e4edfc; color:var(--rb-blue); }
        .rapat-buat .metode-grup .radio:has(input:checked) .i { color:var(--rb-blue); }
        .rapat-buat .metode-grup .radio:has(input:focus-visible) { outline:2px solid rgba(26,95,208,.45); outline-offset:-2px; }

        .rapat-buat .status-tempat { display:flex; flex-wrap:wrap; align-items:center; gap:4px 8px; margin:-8px 0 0; font-size:12.5px; }
        .rapat-buat .status-tempat:empty { display:none; }
        .rapat-buat .st-badge { padding:2px 9px; border-radius:5px; font-weight:700; font-size:12px; }
        .rapat-buat .st-badge.ok { background:rgba(22,163,74,.14); color:#15803d; }
        .rapat-buat .st-badge.penuh { background:rgba(220,38,38,.14); color:#b91c1c; }
        .rapat-buat .st-badge.cek { background:rgba(120,130,150,.18); color:var(--rb-ink); }
        .rapat-buat .st-badge.peringatan { background:rgba(217,142,4,.18); color:#865800; }
        .rapat-buat .st-info { color:var(--rb-mute); }
        .rapat-buat .st-info.peringatan { color:#865800; }

        .rapat-buat .tempat-luar { display:flex; flex-direction:column; gap:10px; padding:14px 16px; background:var(--rb-soft); border:1px dashed #b9c7dc; border-radius:9px; }
        .rapat-buat .tempat-luar-judul { font-size:12.5px; line-height:1.4; color:var(--rb-mute); }

        /* ---------- Lampiran ---------- */
        .rapat-buat .lampiran-baris { display:flex; align-items:center; flex-wrap:wrap; gap:6px 12px; }
        .rapat-buat .btn-file {
            position:relative; display:inline-flex; align-items:center; gap:7px; height:var(--rb-h); margin:0; padding:0 14px;
            border:1px solid var(--rb-line); border-radius:var(--rb-r); background:#fff;
            font-size:13px; font-weight:600; color:var(--rb-ink); cursor:pointer; max-width:100%;
        }
        .rapat-buat .btn-file:hover { background:#f1f5fb; }
        .rapat-buat .btn-file .i { width:16px; height:16px; flex:none; }
        .rapat-buat .btn-file span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .rapat-buat .sembunyi { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; }

        /* ---------- Tab peserta ---------- */
        .rapat-buat .tab-peserta-bar { display:flex; margin:0; padding:0; background:none; border-radius:0; border-bottom:1px solid var(--rb-line); gap:0; }
        .rapat-buat .tab-peserta {
            flex:1; display:flex; align-items:center; justify-content:center; gap:8px; height:40px; margin:0 0 -1px;
            padding:0 10px; background:transparent; border:0; border-bottom:2px solid transparent; border-radius:0; box-shadow:none;
            font:inherit; font-size:13.5px; font-weight:600; color:var(--rb-mute); cursor:pointer;
        }
        .rapat-buat .tab-peserta .i { width:16px; height:16px; }
        .rapat-buat .tab-peserta:hover { color:var(--rb-ink); }
        .rapat-buat .tab-peserta.aktif { background:transparent; box-shadow:none; color:var(--rb-blue); border-bottom-color:var(--rb-blue); }

        /* kotak pilih: pencarian + akordeon + peserta luar dalam satu bingkai */
        .rapat-buat .kotak-pilih { padding:0; border:1px solid var(--rb-line); border-radius:9px; }
        .rapat-buat #tabKelompok .kotak-pilih { display:flex; flex-direction:column; gap:12px; padding:16px; }
        .rapat-buat .aksi-kelompok { display:flex; gap:8px; }

        .rapat-buat .kotak-cari { position:relative; margin:0; padding:12px; border-bottom:1px solid var(--rb-line-soft); }
        .rapat-buat .kotak-cari > .i { position:absolute; left:24px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#5b6b86; pointer-events:none; z-index:1; }
        .rapat-buat .kotak-cari input[type=search] { padding-left:36px; }
        .rapat-buat .hasil-cari {
            position:absolute; left:12px; right:12px; top:calc(100% - 6px); z-index:20; margin:0; padding:6px;
            background:#fff; border:1px solid var(--rb-line); border-radius:9px; box-shadow:0 6px 18px rgba(20,48,94,.14);
            max-height:260px; overflow:auto;
        }
        .rapat-buat .hasil-cari .kosong-kecil { display:block; padding:10px; }
        .rapat-buat .hasil-item { position:relative; display:flex; flex-direction:column; align-items:flex-start; width:100%; padding:8px 34px 8px 10px; border:0; border-radius:7px; background:transparent; text-align:left; font:inherit; cursor:pointer; }
        .rapat-buat .hasil-item:hover { background:#eaf1fc; }
        .rapat-buat .hasil-item b { font-size:13px; }
        .rapat-buat .hasil-item small { font-size:12px; color:var(--rb-mute); }
        .rapat-buat .hasil-item.sudah { opacity:.6; }
        .rapat-buat .hasil-item em { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#1f8f3d; font-style:normal; font-weight:700; }

        /* akordeon */
        .rapat-buat .acc { padding:0 14px; border-top:0; border-bottom:1px solid var(--rb-line-soft); }
        .rapat-buat .acc-kepala { display:flex; align-items:center; justify-content:space-between; min-height:44px; }
        .rapat-buat .acc-judul { display:flex; align-items:center; gap:9px; margin:0; font-size:13.5px; font-weight:700; color:var(--rb-ink); cursor:pointer; }
        .rapat-buat .acc-judul .i { width:18px; height:18px; flex:none; }
        .rapat-buat .acc-judul small { font-size:12.5px; font-weight:400; color:var(--rb-mute); }
        .rapat-buat .acc-panah { display:grid; place-items:center; width:30px; height:30px; padding:0; border:0; border-radius:6px; background:none; color:var(--rb-mute); cursor:pointer; transition:transform .15s; }
        .rapat-buat .acc-panah:hover { background:#eef3fb; color:var(--rb-ink); }
        .rapat-buat .acc-panah .i { width:17px; height:17px; stroke-width:2.2; }
        .rapat-buat .acc-panah.buka { transform:rotate(180deg); }
        .rapat-buat .acc-isi { max-height:180px; overflow:auto; padding:0 4px 10px 27px; }
        .rapat-buat .acc-item { display:flex; align-items:center; gap:9px; margin:0; padding:6px 0; font-size:13px; font-weight:500; cursor:pointer; }
        .rapat-buat .acc-judul input[type=checkbox], .rapat-buat .acc-item input[type=checkbox] {
            appearance:none; -webkit-appearance:none; flex:none; width:16px; height:16px; margin:0;
            border:1.5px solid #b4c4dc; border-radius:4px; background:#fff center/12px no-repeat; cursor:pointer;
        }
        .rapat-buat .acc-judul input[type=checkbox]:checked, .rapat-buat .acc-item input[type=checkbox]:checked {
            background-color:var(--rb-blue); border-color:var(--rb-blue);
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E");
        }

        /* peserta dari luar */
        .rapat-buat .peserta-luar { display:flex; flex-direction:column; gap:10px; margin:0; padding:14px; background:var(--rb-soft); border-top:0; border-radius:0 0 8px 8px; }
        .rapat-buat .peserta-luar-judul { display:flex; align-items:center; flex-wrap:wrap; gap:4px 8px; font-size:13px; font-weight:700; color:var(--rb-ink); }
        .rapat-buat .peserta-luar-judul .i { width:17px; height:17px; }
        .rapat-buat .peserta-luar-judul small { font-size:12px; font-weight:400; color:var(--rb-mute); }
        .rapat-buat .peserta-luar-baris { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) auto; gap:8px; margin:0; align-items:stretch; }

        /* peserta terpilih: mengisi sisa tinggi panel */
        .rapat-buat .peserta-terpilih { flex:1; display:flex; flex-direction:column; min-height:120px; }
        .rapat-buat .peserta-judul { margin:0 0 6px; padding:0; background:none; border-radius:0; font-size:12.5px; font-weight:600; color:var(--rb-ink); }
        .rapat-buat .peserta-kepala { display:flex; align-items:center; justify-content:space-between; gap:10px; min-height:28px; margin:0 0 6px; }
        .rapat-buat .peserta-kepala .peserta-judul { margin:0; }
        .rapat-buat .btn-hapus-semua {
            display:inline-flex; align-items:center; gap:5px; height:28px; margin:0; padding:0 10px;
            border:1px solid #f1c4c2; border-radius:6px; background:#fff; color:#d92d27;
            font:inherit; font-size:12.5px; font-weight:600; cursor:pointer;
        }
        .rapat-buat .btn-hapus-semua:hover { background:#fde5e4; }
        .rapat-buat .btn-hapus-semua .i { width:13px; height:13px; stroke-width:2.4; }
        .rapat-buat .btn-hapus-semua:focus-visible { outline:2px solid rgba(217,45,39,.4); outline-offset:2px; }
        .rapat-buat .cari-terpilih { position:relative; display:flex; align-items:center; gap:10px; margin:0 0 8px; }
        .rapat-buat .cari-terpilih > .i { position:absolute; left:11px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#5b6b86; pointer-events:none; z-index:1; }
        .rapat-buat .cari-terpilih input[type=search] { flex:1; padding-left:34px; }
        .rapat-buat .cari-terpilih-info { font-size:12px; color:var(--rb-mute); white-space:nowrap; }
        .rapat-buat .peserta-chip { flex:1; display:flex; flex-wrap:wrap; align-content:flex-start; gap:8px; min-height:84px; max-height:280px; overflow-y:auto; padding:10px; background:#fff; border:1px solid var(--rb-line); border-radius:9px; }
        .rapat-buat .chip-orang { display:inline-flex; align-items:center; gap:6px; height:28px; padding:0 6px 0 9px; background:#eef3fb; border:1px solid #d3dff2; border-radius:6px; font-size:12.5px; font-weight:500; color:var(--rb-ink); }
        .rapat-buat .chip-orang .i { width:14px; height:14px; color:var(--rb-blue); }
        .rapat-buat .chip-orang .x { display:grid; place-items:center; width:18px; height:18px; padding:0; border:0; border-radius:4px; background:none; color:#4a5c7a; cursor:pointer; }
        .rapat-buat .chip-orang .x:hover { background:#fde5e4; color:#d92d27; }
        .rapat-buat .chip-orang .x .i { width:12px; height:12px; color:inherit; stroke-width:2.4; }
        .rapat-buat .chip-orang.lebih { padding:0 12px; background:#fff; border-color:var(--rb-blue); color:var(--rb-blue); font:inherit; font-size:12.5px; font-weight:700; cursor:pointer; }
        .rapat-buat .chip-orang .tag-luar { font-style:normal; padding:0 6px; border-radius:4px; background:#fdf0cf; color:#8a5a00; font-size:10.5px; font-weight:700; }

        /* ---------- Kelompok sementara ---------- */
        .rapat-buat #tabKelompok .kotak-cari { padding:0; border:0; }
        .rapat-buat #tabKelompok .kotak-cari > .i { left:12px; }
        .rapat-buat #tabKelompok .hasil-cari { left:0; right:0; top:calc(100% + 4px); }
        .rapat-buat .grup-luar { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) auto; gap:8px; align-items:stretch; }
        .rapat-buat .chip-draft {
            display:flex; flex-wrap:wrap; align-content:flex-start; gap:8px; min-height:56px; max-height:200px;
            overflow-y:auto; padding:10px; background:#fff; border:1px solid var(--rb-line); border-radius:9px;
        }
        .rapat-buat .daftar-grup { display:flex; flex-direction:column; gap:10px; }
        .rapat-buat .grup-kartu { border:1px solid var(--rb-line); border-radius:9px; background:#fff; }
        .rapat-buat .grup-kepala {
            display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 12px;
            background:var(--rb-soft); border-bottom:1px solid var(--rb-line-soft); border-radius:9px 9px 0 0; font-size:13px;
        }
        .rapat-buat .grup-kepala small { color:var(--rb-mute); font-weight:400; }
        .rapat-buat .grup-isi { display:flex; flex-wrap:wrap; gap:8px; padding:10px; max-height:160px; overflow-y:auto; }
        .rapat-buat .chip-orang .tag-grup {
            font-style:normal; padding:0 6px; border-radius:4px; background:#d6efef; color:#0b6b6d;
            font-size:10.5px; font-weight:700; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }

        /* ---------- Perapian: peserta terpilih & kelompok sementara ---------- */
        /* baris kepala hanya tampil jika ada tombol "Hapus Semua" */
        .rapat-buat .peserta-kepala:not(:has(#btnHapusSemua:not([hidden]))) { display:none; }
        .rapat-buat .peserta-kepala { justify-content:flex-end; }

        /* kotak chip kosong tidak ditampilkan; muncul otomatis saat ada peserta */
        .rapat-buat .peserta-chip:empty { display:none; }
        .rapat-buat .peserta-terpilih:has(.peserta-chip:empty):not(:has(#kotakCariTerpilih:not([hidden]))) {
            min-height:0; flex:0;
        }

        /* tab Kelompok Sementara: jarak lebih lega, anggota draft kosong lebih ringkas */
        .rapat-buat #tabKelompok .kotak-pilih { gap:14px; }
        .rapat-buat .chip-draft:empty { min-height:44px; background:var(--rb-soft); border-style:dashed; }
        .rapat-buat .aksi-kelompok .btn { flex:1; }

        /* ---------- Tombol ---------- */
        .rapat-buat .btn {
            display:inline-flex; align-items:center; justify-content:center; gap:8px; height:var(--rb-h); margin:0; padding:0 16px;
            border:1px solid var(--rb-line); border-radius:var(--rb-r); background:#fff; color:var(--rb-ink);
            font:inherit; font-size:13.5px; font-weight:600; line-height:1; white-space:nowrap; text-decoration:none; cursor:pointer;
        }
        .rapat-buat .btn:hover { background:#f1f5fb; }
        .rapat-buat .btn .i { width:16px; height:16px; flex:none; }
        .rapat-buat .btn-biru { background:var(--rb-blue); border-color:var(--rb-blue); color:#fff; }
        .rapat-buat .btn-hijau { background:#1f8f3d; border-color:#1f8f3d; color:#fff; }
        .rapat-buat .btn-biru:hover, .rapat-buat .btn-hijau:hover { filter:brightness(.92); background:inherit; }
        .rapat-buat .btn-biru:hover { background:var(--rb-blue); }
        .rapat-buat .btn-hijau:hover { background:#1f8f3d; }
        .rapat-buat .btn[disabled] { opacity:.6; cursor:not-allowed; }
        .rapat-buat .btn-kirim { height:42px; padding:0 22px; }
        .rapat-buat .btn:focus-visible { outline:2px solid rgba(26,95,208,.45); outline-offset:2px; }

        /* ---------- Bilah aksi (footer panel Peserta Rapat) ---------- */
        .rapat-buat .aksi-bar {
            display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:0; padding:12px 18px;
            background:var(--rb-soft); border-top:1px solid var(--rb-line); border-radius:0 0 9px 9px;
        }
        .rapat-buat .aksi-bar .spasi { flex:1; }

        /* ---------- Status pengiriman WA ---------- */
        .rapat-buat .panel-plain { margin-top:16px; padding:0; }
        .rapat-buat .kepala-plain {
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px 16px; margin:0;
            padding:10px 18px; min-height:56px; background:var(--rb-soft); border-bottom:1px solid var(--rb-line); border-radius:9px 9px 0 0;
        }
        .rapat-buat .kepala-plain h3 { display:flex; align-items:center; gap:9px; margin:0; font-size:14.5px; font-weight:700; color:var(--rb-navy); }
        .rapat-buat .kepala-plain h3 .i { width:19px; height:19px; color:var(--rb-blue); }
        .rapat-buat .kepala-plain h3 small { font-size:12.5px; font-weight:400; color:var(--rb-mute); }
        .rapat-buat .aksi-status { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .rapat-buat .aksi-status select { width:auto; min-width:150px; }
        .rapat-buat .status-isi { padding:16px 18px 18px; }

        .rapat-buat .ringkas-wa { display:flex; flex-wrap:wrap; gap:10px; margin:0 0 14px; }
        .rapat-buat .status-wa {
            display:inline-flex; align-items:baseline; gap:6px; padding:7px 14px; background:#fff; color:var(--rb-mute);
            border:1px solid var(--rb-line); border-left:3px solid #94a3b8; border-radius:6px; font-size:12.5px; font-weight:500;
        }
        .rapat-buat .status-wa b { font-size:15px; color:var(--rb-ink); font-variant-numeric:tabular-nums; }
        .rapat-buat .status-wa.terkirim { background:#fff; color:var(--rb-mute); border-left-color:#1f9d4a; }
        .rapat-buat .status-wa.pending { background:#fff; color:var(--rb-mute); border-left-color:#d98e04; }
        .rapat-buat .status-wa.gagal { background:#fff; color:var(--rb-mute); border-left-color:#d92d27; }

        .rapat-buat .tabel-scroll { overflow-x:auto; border:1px solid var(--rb-line); border-radius:9px; }
        .rapat-buat .tabel-polos { width:100%; border-collapse:collapse; }
        .rapat-buat .tabel-polos th { padding:10px 14px; background:var(--rb-soft); border-bottom:1px solid var(--rb-line); font-size:12.5px; font-weight:700; text-align:left; color:var(--rb-navy); white-space:nowrap; }
        .rapat-buat .tabel-polos td { padding:10px 14px; border-bottom:1px solid var(--rb-line-soft); font-size:13px; vertical-align:middle; }
        .rapat-buat .tabel-polos tbody tr:last-child td { border-bottom:0; }
        .rapat-buat .tabel-polos tbody tr:hover { background:#fafcff; }
        .rapat-buat .tabel-polos .c { text-align:center; }
        .rapat-buat .tabel-polos .kosong { padding:28px 14px; text-align:center; color:var(--rb-mute); }

        /* ---------- Lain-lain ---------- */
        .rapat-buat .alert.gagal ul { margin:0; padding-left:18px; }

        @@media (max-width:1000px) {
            .rapat-buat .grid-dua { grid-template-columns:1fr; }
        }
        @@media (max-width:600px) {
            .rapat-buat .f-baris { grid-template-columns:1fr; }
            .rapat-buat .peserta-luar-baris { grid-template-columns:1fr; }
            .rapat-buat .grup-luar { grid-template-columns:1fr; }
            .rapat-buat .aksi-bar { flex-wrap:wrap; }
            .rapat-buat .aksi-bar .spasi { display:none; }
            .rapat-buat .aksi-bar .btn { flex:1 1 140px; }
            .rapat-buat .aksi-status, .rapat-buat .aksi-status select { width:100%; }
        }
        @@media (prefers-reduced-motion:reduce) {
            .rapat-buat .wk-isi, .rapat-buat .acc-panah { transition:none; }
        }
    </style>
@endpush

@section('page_head')
    <div class="rapat-head">
        <svg class="i ikon-besar"><use href="#i-kalender-plus"/></svg>
        <div>
            <h1>Buat Rapat / Undangan</h1>
            <p class="sub">Buat undangan rapat, pilih peserta (pegawai/kelompok), atur jadwal, dan kirim undangan melalui WhatsApp.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="rapat-wrap rapat-buat">

    @php
        // Jam operasional rapat (sama dengan konstanta di RapatController)
        $jamBuka    = '07:00';
        $jamTutup   = '15:00';
        // Penentuan sesi otomatis:
        //   selesai <= batas  -> Sesi Pagi
        //   mulai   >= batas  -> Sesi Siang
        //   selain itu        -> Fullday (melewati batas)
        $batasSiang = '12:00';
        $langkah    = 30; // interval pilihan waktu (menit)

        $keMenit = fn ($t) => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
        $keJam   = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        $bukaM  = $keMenit($jamBuka);
        $tutupM = $keMenit($jamTutup);

        $slotMulai = $slotSelesai = [];
        for ($m = $bukaM; $m < $tutupM; $m += $langkah)          { $slotMulai[]   = $keJam($m); }
        for ($m = $bukaM + $langkah; $m <= $tutupM; $m += $langkah) { $slotSelesai[] = $keJam($m); }

        // Bawaan: Fullday 07:00 - 15:00
        $wMulai   = substr((string) old('waktu', $jamBuka), 0, 5);
        $wSelesai = substr((string) old('waktu_selesai', $jamTutup), 0, 5);

        // Jaga-jaga bila nilai lama tidak persis di kelipatan interval
        if (preg_match('/^\d{2}:\d{2}$/', $wMulai) && !in_array($wMulai, $slotMulai, true)
            && $keMenit($wMulai) >= $bukaM && $keMenit($wMulai) < $tutupM) {
            $slotMulai[] = $wMulai; sort($slotMulai);
        }
        if (preg_match('/^\d{2}:\d{2}$/', $wSelesai) && !in_array($wSelesai, $slotSelesai, true)
            && $keMenit($wSelesai) > $bukaM && $keMenit($wSelesai) <= $tutupM) {
            $slotSelesai[] = $wSelesai; sort($slotSelesai);
        }
    @endphp

    @if ($errors->any())
        <div class="alert gagal" role="alert">
            <ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('rapat.store') }}" id="formRapat" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="peserta_json" id="pesertaJson" value="{{ old('peserta_json') }}">

        <div class="grid-dua">

            {{-- ================= DETAIL RAPAT ================= --}}
            <section class="panel">
                <h3 class="judul-panel"><svg class="i"><use href="#i-edit"/></svg> Detail Rapat</h3>

                <div class="isi-panel">
                    <div class="f">
                        <label for="nama_rapat">Nama Rapat <span class="wajib">*</span></label>
                        <input type="text" id="nama_rapat" name="nama_rapat" maxlength="100" required
                               value="{{ old('nama_rapat') }}" placeholder="Contoh: Rapat Koordinasi Program SDM 2027">
                    </div>

                    <div class="f">
                        <label for="catatan_rapat">Agenda / Topik</label>
                        <textarea id="catatan_rapat" name="catatan_rapat" rows="2" maxlength="255"
                                  placeholder="Pembahasan rapat">{{ old('catatan_rapat') }}</textarea>
                    </div>

                    {{-- ===== Jadwal: tanggal, mulai, selesai ===== --}}
                    <div class="wk">
                        <span class="wk-label" id="labelWaktu">Jadwal Rapat <span class="wajib">*</span></span>
                        <span class="sembunyi-visual" id="teksTanggal">—</span>
                        {{-- elemen lama; dipertahankan agar skrip lain tidak error --}}
                        <span id="teksWaktu" class="sembunyi-visual" aria-hidden="true">—</span>

                        <div class="wk-baris" role="group" aria-labelledby="labelWaktu">
                            <div class="wk-kolom">
                                <small>Tanggal</small>
                                <div class="wk-pilih">
                                    <svg class="i"><use href="#i-kalender"/></svg>
                                    <input type="date" id="tanggal" name="tanggal" required
                                           min="{{ now()->toDateString() }}" aria-label="Tanggal rapat"
                                           value="{{ old('tanggal', now()->toDateString()) }}">
                                </div>
                            </div>
                            <div class="wk-kolom">
                                <small>Mulai</small>
                                <div class="wk-pilih">
                                    <svg class="i"><use href="#i-jam"/></svg>
                                    <select id="waktu" name="waktu" required aria-label="Waktu mulai">
                                        @foreach ($slotMulai as $s)
                                            <option value="{{ $s }}" @selected($s === $wMulai)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @if ($punya['selesai'])
                                <div class="wk-kolom">
                                    <small>Selesai</small>
                                    <div class="wk-pilih">
                                        <svg class="i"><use href="#i-jam"/></svg>
                                        <select id="waktu_selesai" name="waktu_selesai" required aria-label="Waktu selesai">
                                            @foreach ($slotSelesai as $s)
                                                <option value="{{ $s }}" @selected($s === $wSelesai)>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Pemberitahuan bila ada jam yang sudah terlewat (hanya untuk tanggal hari ini) --}}
                        <p class="wk-hint wk-peringatan" id="infoLewat" aria-live="polite" hidden></p>

                        @if ($punya['selesai'])
                            <div class="wk-preset" role="group" aria-label="Pilihan cepat">
                                <button type="button" class="wk-chip" data-mulai="07:00" data-selesai="12:00">Pagi 07:00–12:00</button>
                                <button type="button" class="wk-chip" data-mulai="13:00" data-selesai="15:00">Siang 13:00–15:00</button>
                                <button type="button" class="wk-chip" data-mulai="07:00" data-selesai="15:00">Fullday 07:00–15:00</button>
                            </div>
                        @endif

                        {{-- Garis waktu visual --}}
                        <div class="wk-garis" aria-hidden="true">
                            <div class="wk-jalur">
                                <div class="wk-isi" id="garisIsi"></div>
                                <span class="wk-batas"></span>
                            </div>
                            <div class="wk-skala">
                                <span style="left:0">07:00</span>
                                <span style="left:25%">09:00</span>
                                <span style="left:62.5%">12:00</span>
                                <span style="left:100%">15:00</span>
                            </div>
                        </div>

                        {{-- Sesi ditentukan otomatis dari jam mulai & selesai --}}
                        <div class="wk-ringkas">
                            <label class="judul">Sesi Rapat</label>
                            <span id="badgeSesi" class="badge-sesi" aria-live="polite">—</span>
                            <span class="bantu-inline" id="infoSesi"></span>
                        </div>
                        <p class="wk-hint">
                            Jam rapat {{ $jamBuka }}–{{ $jamTutup }}. Pagi: selesai paling lambat {{ $batasSiang }}. Siang: mulai {{ $batasSiang }} atau lebih. Fullday: melewati {{ $batasSiang }}.
                            Tempat yang sama memerlukan jeda minimal 30 menit setelah rapat sebelumnya selesai.
                        </p>
                    </div>

                    {{-- ===== Tempat & metode ===== --}}
                    <div class="f-baris">
                        <div class="f">
                            <label for="id_tempat">Tempat Rapat <span class="wajib">*</span></label>
                            <div class="baris-tempat">
                                <div class="ikon-input">
                                    <svg class="i"><use href="#i-ruang"/></svg>
                                    <select id="id_tempat" name="id_tempat" required>
                                        <option value="" id="opsiKosongTempat">— Pilih tempat —</option>
                                        @foreach ($tempat as $t)
                                            @php $label = $t->nama_tempat . ($t->gedung ? ' (' . $t->gedung . ')' : ''); @endphp
                                            <option value="{{ $t->id_tempat }}" data-label="{{ $label }}" @selected((string) old('id_tempat') === (string) $t->id_tempat)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                        <option value="luar" data-label="Tempat di luar kantor (sementara)" @selected((string) old('id_tempat') === 'luar')>
                                            + Tempat di luar kantor (sementara)
                                        </option>
                                    </select>
                                </div>
                                <span id="badgeTempat" class="badge-tempat"></span>
                            </div>
                        </div>

                        <div class="f">
                            <span class="f-label" id="labelMetode">Metode Rapat</span>
                            <div class="metode-grup" role="radiogroup" aria-labelledby="labelMetode">
                                <label class="radio">
                                    <input type="radio" name="metode" value="tatap_muka" @checked(old('metode', 'tatap_muka') === 'tatap_muka')>
                                    <svg class="i"><use href="#i-users"/></svg> Tatap Muka
                                </label>
                                <label class="radio">
                                    <input type="radio" name="metode" value="daring" @checked(old('metode') === 'daring')>
                                    <svg class="i"><use href="#i-ruang"/></svg> Daring (Online)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="status-tempat" id="statusTempat" aria-live="polite"></div>

                    {{-- Tempat di luar kantor: hanya untuk rapat ini, TIDAK disimpan ke daftar Tempat Rapat --}}
                    <div id="barisTempatLuar" class="tempat-luar" hidden>
                        <div class="tempat-luar-judul">Tempat di luar kantor — hanya untuk rapat ini, tidak disimpan ke daftar tempat.</div>

                        <div class="f-baris">
                            <div class="f">
                                <label for="tempat_luar_nama">Nama tempat <span class="wajib">*</span></label>
                                <input type="text" id="tempat_luar_nama" name="tempat_luar_nama" maxlength="60"
                                       value="{{ old('tempat_luar_nama') }}" placeholder="Contoh: Hotel Santika, Aula Kecamatan">
                            </div>
                            <div class="f">
                                <label for="tempat_luar_ket">Link Google Maps</label>
                                <input type="text" id="tempat_luar_ket" name="tempat_luar_ket" maxlength="100"
                                       value="{{ old('tempat_luar_ket') }}" placeholder="Contoh: https://maps.google.com/...">
                            </div>
                        </div>

                        <p class="bantu">Keterangan ini ikut tertulis di pesan WhatsApp ke peserta. Ketersediaan tempat tidak diperiksa.</p>
                    </div>

                    <div id="barisLink" class="f" hidden>
                        <label for="link_rapat">Link Rapat Online</label>
                        <input type="url" id="link_rapat" name="link_rapat" value="{{ old('link_rapat') }}"
                               placeholder="https://meet.google.com/... atau https://zoom.us/...">
                    </div>

                    @if ($punya['lampiran'])
                        <div class="f">
                            <span class="f-label">Lampiran (Opsional)</span>
                            <div class="lampiran-baris">
                                <label class="btn-file">
                                    <input type="file" id="lampiran" name="lampiran" accept=".pdf,.doc,.docx" class="sembunyi">
                                    <svg class="i"><use href="#i-klip"/></svg> <span id="namaFile">Pilih File</span>
                                </label>
                                <span class="bantu-inline">Undangan, TOR, atau dokumen pendukung (PDF/DOC)</span>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- ================= PESERTA RAPAT ================= --}}
            <section class="panel">
                <h3 class="judul-panel"><svg class="i"><use href="#i-users"/></svg> Peserta Rapat</h3>

                <div class="isi-panel">
                    <div class="tab-peserta-bar">
                        <button type="button" class="tab-peserta aktif" data-tab="pilih">
                            <svg class="i"><use href="#i-users"/></svg> Pilih Peserta
                        </button>
                        <button type="button" class="tab-peserta" data-tab="kelompok">
                            <svg class="i"><use href="#i-layers"/></svg> Kelompok Sementara
                        </button>
                    </div>

                    {{-- Tab: Pilih Peserta --}}
                    <div id="tabPilih" class="isi-tab">
                        <div class="kotak-pilih">
                            <div class="kotak-cari">
                                <svg class="i"><use href="#i-cari"/></svg>
                                <input type="search" id="cariPegawai" placeholder="Cari pegawai (nama, NIK, unit, atau jabatan)..." autocomplete="off">
                                <div id="hasilCari" class="hasil-cari" hidden></div>
                            </div>

                            <div class="acc" data-jenis="unit">
                                <div class="acc-kepala">
                                    <label class="acc-judul"><input type="checkbox" class="acc-semua"> <svg class="i ikon-biru"><use href="#i-gedung"/></svg> Direktorat / Unit</label>
                                    <button type="button" class="acc-panah" aria-expanded="false" aria-label="Buka daftar unit"><svg class="i"><use href="#i-chevron"/></svg></button>
                                </div>
                                <div class="acc-isi" hidden>
                                    @forelse ($units as $u)
                                        <label class="acc-item"><input type="checkbox" class="acc-cek" data-jenis="unit" data-nilai="{{ $u }}"> {{ $u }}</label>
                                    @empty
                                        <span class="kosong-kecil">Belum ada data unit.</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="acc" data-jenis="jabatan">
                                <div class="acc-kepala">
                                    <label class="acc-judul"><input type="checkbox" class="acc-semua"> <svg class="i ikon-biru"><use href="#i-users"/></svg> Jabatan</label>
                                    <button type="button" class="acc-panah" aria-expanded="false" aria-label="Buka daftar jabatan"><svg class="i"><use href="#i-chevron"/></svg></button>
                                </div>
                                <div class="acc-isi" hidden>
                                    @forelse ($jabatan as $j)
                                        <label class="acc-item"><input type="checkbox" class="acc-cek" data-jenis="jabatan" data-nilai="{{ $j }}"> {{ $j }}</label>
                                    @empty
                                        <span class="kosong-kecil">Belum ada data jabatan.</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="acc" data-jenis="kelompok">
                                <div class="acc-kepala">
                                    <label class="acc-judul"><input type="checkbox" class="acc-semua"> <svg class="i ikon-biru"><use href="#i-users"/></svg> Kelompok Rapat <small>(Tersimpan)</small></label>
                                    <button type="button" class="acc-panah" aria-expanded="false" aria-label="Buka daftar kelompok"><svg class="i"><use href="#i-chevron"/></svg></button>
                                </div>
                                <div class="acc-isi" id="isiKelompok" hidden></div>
                            </div>

                            {{-- Peserta dari luar: hanya untuk rapat ini, TIDAK masuk data pegawai --}}
                            <div class="peserta-luar">
                                <div class="peserta-luar-judul">
                                    <svg class="i ikon-biru"><use href="#i-users"/></svg>
                                    Tambah Peserta dari Luar <small>(sementara, tidak masuk daftar pegawai)</small>
                                </div>
                                <div class="peserta-luar-baris">
                                    <input type="text" id="luarNama" maxlength="100" placeholder="Nama lengkap" autocomplete="off">
                                    <input type="tel" id="luarWa" maxlength="20" inputmode="tel" placeholder="Nomor WA, mis. 0812xxxxxxx" autocomplete="off">
                                    <button type="button" class="btn btn-biru" id="btnTambahLuar">Tambah</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab: Kelompok Sementara (hanya untuk rapat ini, tidak disimpan ke database) --}}
                    <div id="tabKelompok" class="isi-tab" hidden>
                        <div class="kotak-pilih">
                            <p class="bantu">Kelompok ini hanya berlaku untuk rapat yang sedang dibuat. Tidak disimpan ke database dan tidak muncul di daftar Kelompok Rapat. Anggotanya otomatis masuk ke Peserta Terpilih.</p>

                            <div class="f">
                                <label for="namaKelompok">Nama Kelompok</label>
                                <input type="text" id="namaKelompok" maxlength="100" placeholder="Contoh: Tim Verifikasi RKA" autocomplete="off">
                            </div>

                            <div class="f">
                                <span class="f-label">Cari Anggota</span>
                                <div class="kotak-cari">
                                    <svg class="i"><use href="#i-cari"/></svg>
                                    <input type="search" id="cariAnggota" placeholder="Cari pegawai (nama, NIK, unit, atau jabatan)..." autocomplete="off">
                                    <div id="hasilAnggota" class="hasil-cari" hidden></div>
                                </div>
                            </div>

                            <div class="f">
                                <span class="f-label">Atau tambah dari luar <span class="bantu-inline">(tidak masuk daftar pegawai)</span></span>
                                <div class="grup-luar">
                                    <input type="text" id="grupLuarNama" maxlength="100" placeholder="Nama lengkap" autocomplete="off">
                                    <input type="tel" id="grupLuarWa" maxlength="20" inputmode="tel" placeholder="Nomor WA, mis. 0812xxxxxxx" autocomplete="off">
                                    <button type="button" class="btn btn-biru" id="btnGrupLuar">Tambah</button>
                                </div>
                            </div>

                            <div class="f">
                                <span class="f-label">Anggota kelompok: <b id="jumlahDraft">0 orang</b></span>
                                <div class="chip-draft" id="chipDraft"></div>
                            </div>

                            <div class="aksi-kelompok">
                                <button type="button" class="btn btn-biru" id="btnBuatGrup">Buat Kelompok</button>
                                <button type="button" class="btn" id="btnKosongDraft">Kosongkan</button>
                            </div>

                            {{-- Daftar kelompok sementara disembunyikan (anggota tetap tampil di Peserta Terpilih lewat tag kelompok).
                                 Elemen dipertahankan agar buat-rapat.js tidak error. --}}
                            <div class="f" hidden>
                                <div class="daftar-grup" id="daftarGrup"></div>
                            </div>
                        </div>
                    </div>

                    <div class="peserta-terpilih">
                        <div class="peserta-kepala">
                            {{-- Judul disembunyikan secara visual; tetap ada untuk pembaca layar dan JS --}}
                            <div class="peserta-judul sembunyi-visual" id="judulPeserta">Peserta Terpilih (0 orang)</div>
                            <button type="button" class="btn-hapus-semua" id="btnHapusSemua" hidden>
                                <svg class="i"><use href="#i-x"/></svg> Hapus Semua
                            </button>
                        </div>
                        <div class="cari-terpilih" id="kotakCariTerpilih" hidden>
                            <svg class="i"><use href="#i-cari"/></svg>
                            <input type="search" id="cariTerpilih" placeholder="Cari di peserta terpilih (nama atau nomor WA)..." autocomplete="off">
                            <span class="cari-terpilih-info" id="infoCariTerpilih" aria-live="polite"></span>
                        </div>
                        <div class="peserta-chip" id="chipPeserta"></div>
                    </div>
                </div>

                {{-- Aksi: menyatu dengan panel Peserta Rapat --}}
                <div class="aksi-bar">
                    <button type="reset" class="btn"><svg class="i"><use href="#i-ulang"/></svg> Reset</button>
                    <span class="spasi"></span>
                    <button type="submit" name="aksi" value="draft" class="btn"><svg class="i"><use href="#i-simpan"/></svg> Simpan Draft</button>
                    <button type="submit" name="aksi" value="kirim" class="btn btn-hijau btn-kirim"><svg class="i"><use href="#i-wa"/></svg> Kirim Undangan (WA)</button>
                </div>
            </section>
        </div>

    </form>

    {{-- ================= STATUS PENGIRIMAN WA (hanya setelah undangan dikirim) ================= --}}
    @if ($rapatBaru)
    <section class="panel panel-plain" id="panelStatusWa">
        <div class="kepala-plain">
            <h3>
                <svg class="i"><use href="#i-wa"/></svg>
                Status Pengiriman Undangan <small>(WhatsApp)</small>
            </h3>
            <div class="aksi-status">
                {{-- Rapat yang baru dikirim; id dipertahankan agar JS tetap bekerja --}}
                <input type="hidden" id="pilihRapatWa" value="{{ $rapatBaru->getKey() }}">
                <span class="bantu-inline">
                    {{ $rapatBaru->nama_rapat }} — {{ \Carbon\Carbon::parse($rapatBaru->tanggal)->format('d/m/Y') }}
                </span>
                <select id="filterStatusWa" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="pending">Pending</option>
                    <option value="terkirim">Terkirim</option>
                    <option value="gagal">Gagal</option>
                </select>
                <button type="button" class="btn btn-biru" id="btnUlangSemua" hidden>
                    <svg class="i"><use href="#i-ulang"/></svg> Kirim Ulang yang Gagal
                </button>
            </div>
        </div>

        <div class="status-isi">
            <div class="ringkas-wa">
                <span class="status-wa">Total <b id="wa-total">0</b></span>
                <span class="status-wa terkirim">Terkirim <b id="wa-terkirim">0</b></span>
                <span class="status-wa pending">Pending <b id="wa-pending">0</b></span>
                <span class="status-wa gagal">Gagal <b id="wa-gagal">0</b></span>
            </div>

            <div class="tabel-scroll">
                <table class="tabel-polos tabel-kel">
                    <thead>
                        <tr>
                            <th style="width:56px">No</th><th>Nama</th><th>Nomor WA</th>
                            <th class="c">Status</th><th>Waktu Kirim</th><th>Keterangan</th><th class="c">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyStatusWa">
                        <tr><td colspan="7" class="kosong">Memuat status pengiriman…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif

    <div id="toast" class="toast" role="status" hidden></div>
</div>
@endsection

@push('scripts')
    <script>
        window.SIUPIN = @json($konfig);
    </script>
    <script src="{{ asset('js/buat-rapat.js') }}?v={{ @filemtime(public_path('js/buat-rapat.js')) }}"></script>

    {{-- Pemilih waktu, penentuan sesi otomatis, garis waktu, ringkasan, status tempat, jam terlewat, dan metode daring --}}
    <script>
        (function () {
            var BUKA  = @json($jamBuka);
            var TUTUP = @json($jamTutup);
            var BATAS = @json($batasSiang);
            var LANGKAH = @json($langkah);
            var URL_STATUS = @json(route('rapat.status-tempat'));

            // Waktu server saat halaman dibuka (tidak bergantung pada jam perangkat pengguna)
            var HARI_INI   = @json(now()->toDateString());
            var MENIT_AWAL = @json(now()->hour * 60 + now()->minute);
            var T0 = Date.now();

            var form    = document.getElementById('formRapat');
            var tanggal = document.getElementById('tanggal');
            var mulai   = document.getElementById('waktu');
            var selesai = document.getElementById('waktu_selesai'); // null bila kolom tidak ada
            var tempat  = document.getElementById('id_tempat');
            var badge   = document.getElementById('badgeSesi');
            var info    = document.getElementById('infoSesi');
            var isi     = document.getElementById('garisIsi');
            var teksTgl = document.getElementById('teksTanggal');
            var chips   = document.querySelectorAll('.wk-chip');
            var statusTempat = document.getElementById('statusTempat');
            var barisLuar = document.getElementById('barisTempatLuar');
            var luarNama  = document.getElementById('tempat_luar_nama');
            var infoLewat = document.getElementById('infoLewat');
            var barisLink = document.getElementById('barisLink');
            var opsiKosong = document.getElementById('opsiKosongTempat');
            var radioMetode = document.querySelectorAll('input[name="metode"]');
            var TEKS_KOSONG = opsiKosong ? opsiKosong.text : '';

            var LABEL = { pagi: 'Sesi Pagi', siang: 'Sesi Siang', fullday: 'Fullday' };
            var timerCek = null, seqCek = 0;

            // ===== Ketersediaan per jam (tempat yang sudah dipakai ditandai langsung di pilihan jam) =====
            var penuhMulai = {}, penuhSelesai = {};   // { 'HH:MM': true } = jam tidak bisa dipilih
            var ketDipakai = '';                      // contoh: "Rapat A 07:00–09:00"
            var bisaMulai  = '';                      // jam mulai terdekat yang masih kosong
            var catatan    = '';                      // pemberitahuan jam disesuaikan otomatis
            var keyTerakhir = '', mulaiTerakhir = '';
            var timerSlot = null, seqSlot = 0;
            var tempatSebelum = '';                   // tempat yang dipilih sebelum beralih ke Daring

            function menit(t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1]); }
            function jam(m) { return ('0' + Math.floor(m / 60)).slice(-2) + ':' + ('0' + (m % 60)).slice(-2); }
            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            // ===== Jam yang sudah terlewat (hanya berlaku bila tanggal rapat = hari ini) =====
            function menitSekarang() { return MENIT_AWAL + Math.floor((Date.now() - T0) / 60000); }
            function hariIni() { return tanggal.value === HARI_INI; }
            // jam MULAI yang sudah lewat dari sekarang
            function lewat(v) { return hariIni() && menit(v) < menitSekarang(); }
            // jam SELESAI yang sudah lewat atau tepat sekarang
            function selesaiLewat(v) { return hariIni() && menit(v) <= menitSekarang(); }

            function pesanMulai() {
                if (!mulai.value) return '';
                if (lewat(mulai.value)) return 'Jam mulai sudah terlewat. Pilih jam lain atau ganti tanggal.';
                if (penuhMulai[mulai.value]) return 'Tempat dipakai pada jam mulai ini.';
                return '';
            }

            function perbaruiInfoLewat() {
                if (!infoLewat) return;
                if (!hariIni()) { infoLewat.hidden = true; infoLewat.textContent = ''; return; }

                var ada = false, adaLewat = false;
                Array.prototype.forEach.call(mulai.options, function (o) {
                    if (lewat(o.value)) adaLewat = true; else ada = true;
                });

                if (!ada) {
                    infoLewat.textContent = 'Jam rapat hari ini sudah berakhir. Pilih tanggal lain.';
                    infoLewat.hidden = false;
                } else if (adaLewat) {
                    infoLewat.textContent = 'Jam yang sudah terlewat (sebelum ' + jam(Math.min(menitSekarang(), 1439)) + ') tidak dapat dipilih.';
                    infoLewat.hidden = false;
                } else {
                    infoLewat.hidden = true;
                    infoLewat.textContent = '';
                }
            }

            // ===== Metode rapat: Daring -> Tempat Rapat dikunci =====
            function daring() {
                var r = document.querySelector('input[name="metode"]:checked');
                return !!r && r.value === 'daring';
            }

            function aturMetode() {
                var d = daring();

                if (d) {
                    if (tempat.value) tempatSebelum = tempat.value;
                    tempat.value = '';
                    tempat.disabled = true;
                    tempat.required = false;
                    tempat.setCustomValidity('');
                    if (opsiKosong) opsiKosong.text = '— Tidak diperlukan (daring) —';
                } else {
                    tempat.disabled = false;
                    tempat.required = true;
                    if (opsiKosong) opsiKosong.text = TEKS_KOSONG;
                    if (tempatSebelum && !tempat.value) tempat.value = tempatSebelum;
                    tempatSebelum = '';
                }

                if (barisLink) barisLink.hidden = !d;
                aturTempatLuar();
            }

            function durasi(m, s) {
                var d = menit(s) - menit(m), j = Math.floor(d / 60), mn = d % 60;
                return (j ? j + ' jam' : '') + (j && mn ? ' ' : '') + (mn ? mn + ' menit' : '');
            }

            // Mengembalikan: 'pagi' | 'siang' | 'fullday' | ''
            function tentukan(m, s) {
                if (!m) return '';
                if (!s) return m >= BATAS ? 'siang' : 'pagi';
                if (s <= m) return '';
                if (s <= BATAS) return 'pagi';
                if (m >= BATAS) return 'siang';
                return 'fullday';
            }

            // Label & status aktif/nonaktif tiap pilihan jam
            // (jam terlewat dan jam yang bentrok dengan rapat lain tidak bisa dipilih)
            function terapkanOpsi() {
                var m = mulai.value;
                Array.prototype.forEach.call(mulai.options, function (o) {
                    var p = !!penuhMulai[o.value];
                    var l = lewat(o.value);
                    o.disabled = p || l;
                    o.text = o.value + (l ? '  — Terlewat' : (p ? '  — Dipakai' : ''));
                });
                if (selesai) {
                    Array.prototype.forEach.call(selesai.options, function (o) {
                        var lewatMulai = !!m && o.value <= m;
                        var l = selesaiLewat(o.value);
                        var p = !!penuhSelesai[o.value];
                        o.disabled = lewatMulai || l || p;
                        o.text = o.value + (p && !lewatMulai && !l ? '  — Dipakai' : '');
                    });
                }
            }

            // Jam mulai yang sudah terlewat otomatis dipindah ke jam berikutnya yang masih bisa dipilih
            function aturOpsiMulai() {
                var opsi = mulai.options, cur = opsi[mulai.selectedIndex], i;
                if (!cur || !lewat(cur.value)) return false;

                for (i = 0; i < opsi.length; i++) {
                    if (!opsi[i].disabled && menit(opsi[i].value) > menit(cur.value)) {
                        mulai.value = opsi[i].value;
                        return true;
                    }
                }
                return false; // semua jam hari ini sudah lewat
            }

            // Jam selesai yang tidak valid (<= mulai, terlewat, atau bentrok) otomatis dipindah ke pilihan terdekat
            function aturOpsiSelesai() {
                if (!selesai) return false;
                var m = mulai.value, opsi = selesai.options, i, pilih = null;
                var cur = opsi[selesai.selectedIndex];
                if (!m || (cur && !cur.disabled)) return false;

                if (cur && penuhSelesai[cur.value] && menit(cur.value) > menit(m)) {
                    // bentrok: pakai jam selesai paling akhir yang masih kosong
                    for (i = opsi.length - 1; i >= 0; i--) {
                        if (!opsi[i].disabled) { pilih = opsi[i]; break; }
                    }
                } else {
                    // geser otomatis: mulai + 1 jam, mentok di jam tutup
                    var target = Math.min(menit(m) + 60, menit(TUTUP));
                    for (i = 0; i < opsi.length; i++) {
                        if (!opsi[i].disabled && menit(opsi[i].value) >= target) { pilih = opsi[i]; break; }
                    }
                    if (!pilih) {
                        for (i = opsi.length - 1; i >= 0; i--) {
                            if (!opsi[i].disabled) { pilih = opsi[i]; break; }
                        }
                    }
                }
                if (pilih && selesai.value !== pilih.value) { selesai.value = pilih.value; return true; }
                return false;
            }

            function gambarGaris(m, s, k) {
                var total = menit(TUTUP) - menit(BUKA);
                var a = Math.max(0, menit(m) - menit(BUKA));
                var b = s ? Math.min(total, menit(s) - menit(BUKA)) : a + 8;
                isi.style.left  = (a / total * 100) + '%';
                isi.style.width = (Math.max(b - a, 0) / total * 100) + '%';
                isi.className = 'wk-isi' + (k ? ' ' + k : '');
            }

            function formatTanggal(v) {
                if (!v) return '';
                var p = v.split('-');
                var d = new Date(+p[0], +p[1] - 1, +p[2]);
                if (isNaN(d)) return '';
                return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            }

            // ===== Tempat di luar kantor (sementara) =====
            function aturTempatLuar() {
                var luar = tempat.value === 'luar';
                barisLuar.hidden = !luar;
                luarNama.required = luar;
            }

            // ===== Status ketersediaan tempat =====
            function infoSlot() {
                var h = '';
                if (ketDipakai) {
                    h += '<span class="st-info peringatan">Tempat dipakai: ' + esc(ketDipakai) + '.';
                    if (bisaMulai) h += ' Bisa dipakai mulai ' + esc(bisaMulai) + ' (jeda 30 menit setelah rapat sebelumnya).';
                    else h += ' Tidak ada jam kosong lagi pada tanggal ini.';
                    h += '</span>';
                }
                if (catatan) h += '<span class="st-info peringatan">' + esc(catatan) + '</span>';
                return h;
            }

            function tampilStatus() {
                if (daring()) {
                    statusTempat.innerHTML = '<span class="st-badge cek">Daring</span>' +
                        '<span class="st-info">Rapat online, tempat tidak diperlukan. Isi link rapat di bawah.</span>';
                    tempat.setCustomValidity('');
                    return;
                }

                var opt = tempat.options[tempat.selectedIndex];
                if (!tempat.value) {
                    statusTempat.innerHTML = '';
                    tempat.setCustomValidity('');
                    return;
                }
                if (tempat.value === 'luar') {
                    statusTempat.innerHTML = '<span class="st-badge cek">Di luar kantor</span>' +
                        '<span class="st-info">Ketersediaan tidak diperiksa.</span>';
                    tempat.setCustomValidity('');
                    return;
                }
                var t = opt.dataset.tersedia, h;
                if (t === '0') {
                    h = '<span class="st-badge penuh">Tidak tersedia</span>' +
                        '<span class="st-info">Dipakai: ' + esc(opt.dataset.dipakai || '') + '</span>';
                    tempat.setCustomValidity('Tempat tidak tersedia pada jadwal ini.');
                } else if (t === '1') {
                    h = '<span class="st-badge ' + (ketDipakai ? 'peringatan' : 'ok') + '">' +
                        (ketDipakai ? 'Tersedia pada jam ini' : 'Tersedia') + '</span>';
                    tempat.setCustomValidity('');
                } else {
                    h = '<span class="st-badge cek">Memeriksa…</span>';
                }
                statusTempat.innerHTML = h + infoSlot();
            }

            function cekTempat() {
                clearTimeout(timerCek);
                if (daring()) return; // rapat daring: ketersediaan tempat tidak diperiksa
                timerCek = setTimeout(function () {
                    var m = mulai.value, s = selesai ? selesai.value : '';
                    if (!tanggal.value || !m || (s && s <= m)) return;

                    var mine = ++seqCek;
                    var qs = new URLSearchParams({ tanggal: tanggal.value, waktu: m, waktu_selesai: s });

                    fetch(URL_STATUS + '?' + qs.toString(), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                        .then(function (res) {
                            if (mine !== seqCek) return; // abaikan respons usang
                            var data = res.tempat || {};
                            Array.prototype.forEach.call(tempat.options, function (o) {
                                if (!o.value || o.value === 'luar') return;
                                var d = data[o.value];
                                if (!d) { o.text = o.dataset.label; return; }
                                o.dataset.tersedia = d.tersedia ? '1' : '0';
                                o.dataset.dipakai  = (d.dipakai || []).join(', ');
                                o.text = o.dataset.label + (d.tersedia ? '  — Tersedia' : '  — Tidak tersedia');
                                // tempat bentrok tidak bisa dipilih, kecuali yang sedang terpilih (tampil dengan peringatan)
                                o.disabled = !d.tersedia && o.value !== tempat.value;
                            });
                            tampilStatus();
                        })
                        .catch(function () { /* gagal cek: server tetap memvalidasi saat simpan */ });
                }, 250);
            }

            // Tanya server: apakah tempat terpilih kosong pada rentang m–s?
            function tanyaTempat(m, s) {
                var qs = new URLSearchParams({ tanggal: tanggal.value, waktu: m, waktu_selesai: s });
                return fetch(URL_STATUS + '?' + qs.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                    .then(function (res) { return (res.tempat || {})[tempat.value] || null; })
                    .catch(function () { return null; });
            }

            // Periksa tiap pilihan jam mulai (rentang 30 menit) dan tiap pilihan jam selesai (dari jam mulai terpilih)
            function muatSlot() {
                clearTimeout(timerSlot);
                timerSlot = setTimeout(function () {
                    var id = tempat.value;
                    if (daring() || !id || id === 'luar' || !tanggal.value || !mulai.value) return;

                    var mine = ++seqSlot;
                    var tugas = [], mulaiDipakai = mulai.value;

                    Array.prototype.forEach.call(mulai.options, function (o) {
                        var m = o.value;
                        if (lewat(m)) return; // jam terlewat tidak perlu diperiksa
                        var s = jam(Math.min(menit(m) + LANGKAH, menit(TUTUP)));
                        tugas.push(tanyaTempat(m, s).then(function (d) { return { jenis: 'm', nilai: m, d: d }; }));
                    });
                    if (selesai) {
                        Array.prototype.forEach.call(selesai.options, function (o) {
                            if (o.value <= mulaiDipakai) return;
                            tugas.push(tanyaTempat(mulaiDipakai, o.value).then(function (d) { return { jenis: 's', nilai: o.value, d: d }; }));
                        });
                    }

                    Promise.all(tugas).then(function (hasil) {
                        if (mine !== seqSlot || id !== tempat.value) return; // respons usang

                        var pm = {}, ps = {}, dipakai = [];
                        hasil.forEach(function (h) {
                            if (!h.d || h.d.tersedia !== false) return;
                            if (h.jenis === 'm') pm[h.nilai] = true; else ps[h.nilai] = true;
                            (h.d.dipakai || []).forEach(function (x) { if (dipakai.indexOf(x) < 0) dipakai.push(x); });
                        });

                        penuhMulai = pm;
                        penuhSelesai = ps;
                        ketDipakai = dipakai.join(', ');

                        // jam mulai kosong terdekat setelah jam yang terpakai
                        bisaMulai = '';
                        var opsi = mulai.options, pertamaPenuh = -1, i;
                        for (i = 0; i < opsi.length; i++) { if (pm[opsi[i].value]) { pertamaPenuh = i; break; } }
                        if (pertamaPenuh >= 0) {
                            for (i = pertamaPenuh + 1; i < opsi.length; i++) {
                                if (!pm[opsi[i].value] && !lewat(opsi[i].value)) { bisaMulai = opsi[i].value; break; }
                            }
                        }

                        // jam mulai yang dipilih bentrok -> pindah ke jam kosong terdekat
                        if (pm[mulai.value]) {
                            var baru = '';
                            for (i = 0; i < opsi.length; i++) {
                                if (!pm[opsi[i].value] && !lewat(opsi[i].value) && menit(opsi[i].value) > menit(mulai.value)) { baru = opsi[i].value; break; }
                            }
                            if (!baru) {
                                for (i = 0; i < opsi.length; i++) { if (!pm[opsi[i].value] && !lewat(opsi[i].value)) { baru = opsi[i].value; break; } }
                            }
                            if (baru) {
                                catatan = 'Jam mulai dipindah ke ' + baru + ' karena tempat sedang dipakai.';
                                mulai.value = baru;
                                penuhSelesai = {};
                                segarkan();      // memuat ulang ketersediaan jam selesai
                                return;
                            }
                        }

                        // jam selesai yang dipilih bentrok -> pakai jam selesai terakhir yang kosong
                        var awal = selesai ? selesai.value : '';
                        terapkanOpsi();
                        if (aturOpsiSelesai() && selesai.value !== awal) {
                            catatan = 'Jam selesai disesuaikan ke ' + selesai.value + ' karena tempat dipakai setelahnya.';
                        }

                        mulai.setCustomValidity(pesanMulai());
                        if (selesai) {
                            selesai.setCustomValidity(
                                (selesai.value <= mulai.value) ? 'Waktu selesai harus setelah waktu mulai.' :
                                (ps[selesai.value] ? 'Tempat dipakai pada jam selesai ini.' : '')
                            );
                        }

                        tampilStatus();
                        tampilRingkasanJadwal();
                    });
                }, 250);
            }

            function tampilRingkasanJadwal() {
                var m = mulai.value, s = selesai ? selesai.value : '';
                var k = tentukan(m, s);

                badge.className = 'badge-sesi' + (k ? ' ' + k : '') + (k === 'fullday' ? ' seharian' : '');
                badge.textContent = k ? LABEL[k] : '—';
                info.textContent = (k && s) ? (m + ' sampai ' + s + ', durasi ' + durasi(m, s)) : '';

                gambarGaris(m, s, k);
                chips.forEach(function (c) {
                    c.classList.toggle('aktif', c.dataset.mulai === m && c.dataset.selesai === s);
                    // pilihan cepat yang jam mulainya sudah terlewat tidak bisa diklik
                    c.disabled = lewat(c.dataset.mulai);
                });

                var tgl = formatTanggal(tanggal.value);
                teksTgl.textContent = tgl || '—';
                document.getElementById('teksWaktu').textContent = s ? m + ' – ' + s : m;

                perbaruiInfoLewat();
            }

            function segarkan() {
                // tempat atau tanggal berubah -> hasil pemeriksaan jam sebelumnya tidak berlaku lagi
                var key = tempat.value + '|' + tanggal.value;
                if (key !== keyTerakhir) {
                    keyTerakhir = key;
                    mulaiTerakhir = mulai.value;
                    penuhMulai = {}; penuhSelesai = {};
                    ketDipakai = ''; bisaMulai = ''; catatan = '';
                    mulai.setCustomValidity('');
                    if (selesai) selesai.setCustomValidity('');
                } else if (mulai.value !== mulaiTerakhir) {
                    // jam mulai berubah -> pemeriksaan jam selesai harus diulang
                    mulaiTerakhir = mulai.value;
                    penuhSelesai = {};
                    catatan = '';
                }

                terapkanOpsi();

                // jam mulai terlewat (tanggal hari ini) -> pindah ke jam berikutnya yang masih bisa dipilih
                if (aturOpsiMulai()) {
                    mulaiTerakhir = mulai.value;
                    penuhSelesai = {};
                    terapkanOpsi();
                }
                aturOpsiSelesai();

                var m = mulai.value;
                var s = selesai ? selesai.value : '';

                // validasi tambahan (cadangan; server tetap memeriksa)
                mulai.setCustomValidity(pesanMulai());
                if (selesai) selesai.setCustomValidity(s && m && s <= m ? 'Waktu selesai harus setelah waktu mulai.' : '');

                tampilRingkasanJadwal();

                // periksa ketersediaan tempat untuk jadwal ini
                cekTempat();
                muatSlot();
                tampilStatus();
            }

            // Jam berjalan: tiap menit, jam yang baru saja terlewat ikut dikunci
            var menitTerakhir = menitSekarang();
            setInterval(function () {
                var n = menitSekarang();
                if (n === menitTerakhir) return;
                menitTerakhir = n;
                if (!hariIni()) return;

                var sebelum = mulai.value;
                terapkanOpsi();
                if (aturOpsiMulai()) {
                    mulaiTerakhir = mulai.value;
                    penuhSelesai = {};
                    terapkanOpsi();
                }
                aturOpsiSelesai();
                mulai.setCustomValidity(pesanMulai());
                tampilRingkasanJadwal();

                if (mulai.value !== sebelum) segarkan(); // jam mulai bergeser: periksa ulang ketersediaan
            }, 20000);

            // Pilihan cepat
            chips.forEach(function (c) {
                c.addEventListener('click', function () {
                    if (c.disabled) return;
                    mulai.value = c.dataset.mulai;
                    penuhSelesai = {};
                    terapkanOpsi();
                    if (selesai) selesai.value = c.dataset.selesai;
                    segarkan();
                });
            });

            [tanggal, mulai, selesai, tempat].forEach(function (el) {
                if (!el) return;
                el.addEventListener('change', segarkan);
            });
            tempat.addEventListener('change', function () { aturTempatLuar(); tampilStatus(); });

            // Pilih metode: Daring mengunci Tempat Rapat, Tatap Muka membukanya kembali
            Array.prototype.forEach.call(radioMetode, function (r) {
                r.addEventListener('change', function () {
                    aturMetode();
                    keyTerakhir = ''; // paksa pemeriksaan ulang ketersediaan tempat
                    segarkan();
                });
            });

            // Reset mengembalikan nilai bawaan form (07:00 - 15:00 = Fullday, Tatap Muka)
            form.addEventListener('reset', function () {
                setTimeout(function () {
                    // hapus status lama; label asli dikembalikan oleh cekTempat()
                    Array.prototype.forEach.call(tempat.options, function (o) {
                        if (!o.value || o.value === 'luar') return;
                        o.text = o.dataset.label; o.disabled = false;
                        delete o.dataset.tersedia; delete o.dataset.dipakai;
                    });
                    penuhMulai = {}; penuhSelesai = {};
                    ketDipakai = ''; bisaMulai = ''; catatan = '';
                    keyTerakhir = ''; mulaiTerakhir = '';
                    tempatSebelum = '';
                    mulai.setCustomValidity('');
                    if (selesai) selesai.setCustomValidity('');
                    statusTempat.innerHTML = '';
                    tempat.setCustomValidity('');
                    aturMetode();
                    aturTempatLuar();
                    segarkan();
                }, 0);
            });

            aturMetode();
            aturTempatLuar();
            if (tempat.value === 'luar') tampilStatus();
            segarkan();
        })();
    </script>
@endpush