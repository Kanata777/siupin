{{-- resources/views/status-wa/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Status Pengiriman WA')

@section('content')
<style>
    .wa-halaman { max-width: 1280px; }
    .wa-judul { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .wa-judul h1 { margin: 0 0 2px; font-size: 22px; }
    .wa-judul p { margin: 0; color: #6b7280; font-size: 13px; }
    .wa-otomatis { font-size: 13px; color: #374151; display: flex; align-items: center; gap: 6px; }

    .wa-kartu-wrap { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 12px; }
    .wa-kartu { display: block; text-decoration: none; color: inherit; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; background: #fff; }
    .wa-kartu small { display: block; color: #6b7280; font-size: 12px; }
    .wa-kartu b { font-size: 24px; }
    .wa-kartu.aktif { border-color: #111827; box-shadow: 0 0 0 1px #111827 inset; }
    .wa-kartu--gagal b { color: #b91c1c; }
    .wa-kartu--pending b { color: #b45309; }
    .wa-kartu--terkirim b { color: #15803d; }

    .wa-progres { height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin-bottom: 4px; }
    .wa-progres > span { display: block; height: 100%; background: #16a34a; }
    .wa-progres-ket { font-size: 12px; color: #6b7280; margin-bottom: 14px; }

    .wa-form { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .wa-form select, .wa-form input[type=search] { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; background: #fff; }
    .wa-form input[type=search] { flex: 1; min-width: 220px; }
    .wa-form select { max-width: 280px; }

    .wa-btn { background: #dc2626; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }
    .wa-btn:disabled { opacity: .5; cursor: not-allowed; }
    .wa-btn--garis { background: #fff; color: #111827; border: 1px solid #d1d5db; }
    .wa-btn--gelap { background: #111827; }
    .wa-btn--kecil { padding: 4px 10px; font-size: 12px; }

    .wa-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 8px; }
    .wa-toolbar .wa-total { margin-left: auto; font-size: 12px; color: #6b7280; }
    #wa-pesan { font-size: 13px; color: #1d4ed8; min-height: 20px; margin-bottom: 6px; }

    .wa-tabel-wrap { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; }
    .wa-tabel { width: 100%; border-collapse: collapse; font-size: 13px; }
    .wa-tabel th { text-align: left; background: #f9fafb; color: #6b7280; font-weight: 600; font-size: 12px; padding: 10px 12px; white-space: nowrap; }
    .wa-tabel td { padding: 10px 12px; border-top: 1px solid #f3f4f6; vertical-align: top; }
    .wa-tabel .wa-err { color: #b91c1c; font-size: 12px; max-width: 320px; }
    .wa-tabel .wa-sub { color: #6b7280; font-size: 12px; }
    .wa-tag { font-size: 11px; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
    .wa-tag--gagal { background: #fee2e2; color: #b91c1c; }
    .wa-tag--pending { background: #fef3c7; color: #92400e; }
    .wa-tag--terkirim { background: #dcfce7; color: #166534; }
    .wa-kosong { text-align: center; padding: 36px 0; color: #6b7280; }
    .wa-paging { margin-top: 12px; }
</style>

<div class="wa-halaman">
    <div class="wa-judul">
        <div>
            <h1>Status Pengiriman WA</h1>
            <p>Pantau undangan WhatsApp dari seluruh rapat, filter yang gagal atau pending, dan kirim ulang.</p>
        </div>
        <label class="wa-otomatis">
            <input type="checkbox" id="wa-auto" checked> Segarkan otomatis (15 detik)
        </label>
    </div>

    {{-- Filter & pencarian (di luar #wa-live supaya isinya tidak hilang saat auto-refresh) --}}
    <form method="GET" action="{{ route('wa.index') }}" class="wa-form">
        <select name="status" onchange="this.form.submit()">
            <option value="bermasalah" @selected($filter === 'bermasalah')>Gagal + Pending</option>
            <option value="gagal" @selected($filter === 'gagal')>Gagal</option>
            <option value="pending" @selected($filter === 'pending')>Pending</option>
            <option value="terkirim" @selected($filter === 'terkirim')>Terkirim</option>
            <option value="semua" @selected($filter === 'semua')>Semua status</option>
        </select>

        <select name="rapat" onchange="this.form.submit()">
            <option value="">Semua rapat</option>
            @foreach ($daftarRapat as $r)
                <option value="{{ $r->getKey() }}" @selected($idRapat === (int) $r->getKey())>
                    {{ \Illuminate\Support\Str::limit($r->nama_rapat, 50) }}
                    ({{ \Illuminate\Support\Carbon::parse($r->tanggal)->format('d/m/Y') }})
                </option>
            @endforeach
        </select>

        <input type="search" name="q" value="{{ $kata }}" placeholder="Cari nama, nomor WA, nama rapat, atau pesan error…">

        <button type="submit" class="wa-btn wa-btn--gelap">Cari</button>
        <a href="{{ route('wa.index') }}" class="wa-btn wa-btn--garis">Reset</a>
    </form>

    <div id="wa-pesan"></div>

    <div id="wa-live">
        @php
            $tautan = fn($st) => route('wa.index', array_merge(request()->except('page', 'status'), ['status' => $st]));
        @endphp

        <div class="wa-kartu-wrap">
            <a class="wa-kartu {{ $filter === 'semua' ? 'aktif' : '' }}" href="{{ $tautan('semua') }}">
                <small>Total undangan</small><b>{{ number_format($ringkasan['total']) }}</b>
            </a>
            <a class="wa-kartu wa-kartu--terkirim {{ $filter === 'terkirim' ? 'aktif' : '' }}" href="{{ $tautan('terkirim') }}">
                <small>Terkirim</small><b>{{ number_format($ringkasan['terkirim']) }}</b>
            </a>
            <a class="wa-kartu wa-kartu--pending {{ $filter === 'pending' ? 'aktif' : '' }}" href="{{ $tautan('pending') }}">
                <small>Pending</small><b>{{ number_format($ringkasan['pending']) }}</b>
            </a>
            <a class="wa-kartu wa-kartu--gagal {{ $filter === 'gagal' ? 'aktif' : '' }}" href="{{ $tautan('gagal') }}">
                <small>Gagal</small><b>{{ number_format($ringkasan['gagal']) }}</b>
            </a>
        </div>

        <div class="wa-progres"><span style="width: {{ $ringkasan['persen'] }}%"></span></div>
        <div class="wa-progres-ket">{{ $ringkasan['persen'] }}% terkirim</div>

        <div class="wa-toolbar">
            <button type="button" class="wa-btn" id="wa-ulang-pilih" disabled>
                Kirim ulang terpilih (<span>0</span>)
            </button>
            <button type="button" class="wa-btn wa-btn--garis" id="wa-ulang-semua" @disabled($ringkasan['gagal'] === 0)>
                Kirim ulang semua yang gagal
            </button>
            <span class="wa-total">{{ number_format($daftar->total()) }} data ditampilkan</span>
        </div>

        <div class="wa-tabel-wrap">
            <table class="wa-tabel">
                <thead>
                    <tr>
                        <th style="width:32px"><input type="checkbox" id="wa-cek-semua" title="Pilih semua yang gagal di halaman ini"></th>
                        <th>Peserta</th>
                        <th>Rapat</th>
                        <th>Status</th>
                        <th>Keterangan error</th>
                        <th>Diperbarui</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $u)
                        @php
                            $kelas = match ($u->status) {
                                \App\Models\RapatUndangan::GAGAL => 'gagal',
                                \App\Models\RapatUndangan::PENDING => 'pending',
                                default => 'terkirim',
                            };
                            $label = ['gagal' => 'Gagal', 'pending' => 'Pending', 'terkirim' => 'Terkirim'][$kelas];
                        @endphp
                        <tr>
                            <td>
                                @if ($kelas === 'gagal')
                                    <input type="checkbox" class="wa-cek" value="{{ $u->id }}">
                                @endif
                            </td>
                            <td>
                                <strong>{{ $u->nama }}</strong>
                                <div class="wa-sub">{{ $u->nomor_wa }}</div>
                            </td>
                            <td>
                                <a href="{{ route('rapat.show', $u->id_rapat) }}">{{ $u->nama_rapat }}</a>
                                <div class="wa-sub">{{ \Illuminate\Support\Carbon::parse($u->tgl_rapat)->format('d/m/Y') }}</div>
                            </td>
                            <td><span class="wa-tag wa-tag--{{ $kelas }}">{{ $label }}</span></td>
                            <td class="wa-err" title="{{ $u->error }}">
                                {{ $kelas === 'gagal' ? \Illuminate\Support\Str::limit((string) $u->error, 120) : '' }}
                            </td>
                            <td class="wa-sub">{{ optional($u->updated_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($kelas === 'gagal')
                                    <button type="button" class="wa-btn wa-btn--kecil" data-ulang="{{ $u->id }}">Kirim ulang</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="wa-kosong">Tidak ada data untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="wa-paging">{{ $daftar->links() }}</div>
    </div>
</div>

<script>
(function () {
    const URL_ULANG = @json(route('wa.kirim-ulang'));
    const CSRF = @json(csrf_token());
    let sibuk = false;

    const $ = (id) => document.getElementById(id);
    const terpilih = () => [...document.querySelectorAll('.wa-cek:checked')].map(c => c.value);
    const pesan = (t) => { $('wa-pesan').textContent = t || ''; };

    function perbarui() {
        const n = terpilih().length;
        const tombol = $('wa-ulang-pilih');
        if (tombol) { tombol.disabled = n === 0; tombol.querySelector('span').textContent = n; }
    }

    async function segarkan() {
        if (sibuk) return;
        sibuk = true;
        try {
            const res = await fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const baru = doc.getElementById('wa-live');
            if (baru) { $('wa-live').innerHTML = baru.innerHTML; perbarui(); }
        } catch (e) { /* abaikan, coba lagi di putaran berikutnya */ }
        sibuk = false;
    }

    async function kirim(body) {
        if (sibuk) return;
        sibuk = true;
        try {
            const res = await fetch(URL_ULANG, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            const d = await res.json().catch(() => ({}));
            pesan(d.pesan || d.message || (res.ok ? 'Selesai.' : 'Gagal mengirim ulang.'));
        } catch (e) {
            pesan('Gagal mengirim ulang.');
        }
        sibuk = false;
        await segarkan();
    }

    document.addEventListener('click', (e) => {
        const satu = e.target.closest('[data-ulang]');
        if (satu) return kirim({ ids: [satu.dataset.ulang] });

        if (e.target.closest('#wa-ulang-pilih')) {
            const ids = terpilih();
            if (ids.length) kirim({ ids });
            return;
        }

        if (e.target.closest('#wa-ulang-semua')) {
            const p = new URLSearchParams(location.search);
            if (confirm('Kirim ulang semua pesan yang gagal' + (p.get('q') || p.get('rapat') ? ' (sesuai pencarian/rapat yang dipilih)' : '') + '?')) {
                kirim({ semua: 1, q: p.get('q') || '', rapat: p.get('rapat') || '' });
            }
        }
    });

    document.addEventListener('change', (e) => {
        if (e.target.id === 'wa-cek-semua') {
            document.querySelectorAll('.wa-cek').forEach(c => { c.checked = e.target.checked; });
        }
        if (e.target.id === 'wa-cek-semua' || e.target.classList.contains('wa-cek')) perbarui();
    });

    // Auto-refresh: berhenti sementara bila tab tidak aktif atau ada baris yang sedang dipilih
    setInterval(() => {
        if (document.hidden || !$('wa-auto').checked || terpilih().length > 0) return;
        segarkan();
    }, 15000);
})();
</script>
@endsection