{{-- Simpan sebagai: resources/views/dashboard/index.blade.php (ganti SELURUH isi file lama) --}}
@extends('layouts.app')

@section('title', 'Dashboard Rapat')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ @filemtime(public_path('css/dashboard.css')) }}">
@endpush

@section('page_head')
    <div class="dash-head">
        <div>
            <h1>Dashboard Rapat</h1>
            <p class="sub">Ringkasan pelaksanaan rapat, kehadiran peserta, notulensi, dan dokumentasi.</p>
        </div>

        <div class="rentang">
            <a class="rentang-nav" href="{{ route('dashboard', ['bulan' => $bulanSebelum]) }}" aria-label="Bulan sebelumnya">‹</a>

            <form method="GET" action="{{ route('dashboard') }}" class="rentang-isi">
                <span class="rentang-teks">{{ $dari->translatedFormat('d F Y') }} – {{ $sampai->translatedFormat('d F Y') }}</span>
                <input type="month" name="bulan" value="{{ $dari->format('Y-m') }}" class="rentang-bulan"
                       onchange="this.form.submit()" onclick="try{this.showPicker()}catch(e){}" aria-label="Pilih bulan">
            </form>

            <a class="rentang-nav" href="{{ route('dashboard', ['bulan' => $bulanBerikut]) }}" aria-label="Bulan berikutnya">›</a>
        </div>
    </div>

    <p class="preset">
        Periode cepat:
        @foreach ($preset as $nama => $url)
            <a href="{{ $url }}">{{ $nama }}</a>
        @endforeach
    </p>
@endsection

@section('content')

    {{-- User biasa hanya melihat data rapat yang dia buat --}}
    @unless (\App\Models\Rapat::bolehSemua())
        <div class="alert info">Menampilkan data rapat yang Anda buat.</div>
    @endunless

    @if (!$adaData)
        <div class="alert gagal">Tabel <b>rapat</b> di database <b>siupin_nh</b> masih kosong, jadi semua angka bernilai 0.</div>
    @elseif ($periodeOtomatis)
        <div class="alert info">Belum ada rapat bulan ini. Menampilkan bulan terakhir yang memiliki data ({{ $dari->translatedFormat('F Y') }}). Ubah tanggal di kanan atas untuk periode lain.</div>
    @elseif ($total === 0)
        <div class="alert info">Tidak ada rapat pada periode {{ $dari->translatedFormat('j M Y') }} – {{ $sampai->translatedFormat('j M Y') }}.</div>
    @endif

    {{-- ===== Ringkasan ===== --}}
    <div class="ringkas">
        <div class="rk">
            <span class="rk-label">Total rapat</span>
            <b>{{ $total }}</b>
            @if (!is_null($perubahan))
                <span class="rk-sub"><span class="delta {{ $perubahan >= 0 ? 'naik' : 'turun' }}">{{ $perubahan >= 0 ? '+' : '' }}{{ $perubahan }}%</span> dari periode sebelumnya</span>
            @endif
        </div>
        <div class="rk">
            <span class="rk-label"><i class="dot hijau"></i>Selesai</span>
            <b>{{ $jumlah['selesai'] }}</b>
            <span class="rk-sub">{{ $persen['selesai'] }}% dari total</span>
        </div>
        <div class="rk">
            <span class="rk-label"><i class="dot biru"></i>Berlangsung</span>
            <b>{{ $jumlah['berlangsung'] }}</b>
            <span class="rk-sub">{{ $persen['berlangsung'] }}% dari total</span>
        </div>
        <div class="rk">
            <span class="rk-label"><i class="dot merah"></i>Dibatalkan</span>
            <b>{{ $jumlah['dibatalkan'] }}</b>
            <span class="rk-sub">{{ $persen['dibatalkan'] }}% dari total</span>
        </div>
    </div>

    {{-- ===== Tren, status, kehadiran ===== --}}
    <div class="grid-tiga">
        <section class="panel">
            <h3>Tren pelaksanaan rapat</h3>
            <div class="chart-tren"><canvas id="chartTren"></canvas></div>
        </section>

        <section class="panel">
            <h3>Status rapat</h3>
            <div class="status-wrap">
                <div class="donat">
                    <canvas id="chartStatus"></canvas>
                    <div class="donat-tengah"><b>{{ $total }}</b><span>rapat</span></div>
                </div>
                <ul class="legenda">
                    <li><i style="background:#2f7d4f"></i>Selesai <em>{{ $jumlah['selesai'] }} ({{ $persen['selesai'] }}%)</em></li>
                    <li><i style="background:#1f4e8c"></i>Berlangsung <em>{{ $jumlah['berlangsung'] }} ({{ $persen['berlangsung'] }}%)</em></li>
                    <li><i style="background:#b3402f"></i>Dibatalkan <em>{{ $jumlah['dibatalkan'] }} ({{ $persen['dibatalkan'] }}%)</em></li>
                    @if ($jumlah['belum'] > 0)
                        <li><i style="background:#9aa5b5"></i>Belum terlaksana <em>{{ $jumlah['belum'] }} ({{ $persen['belum'] }}%)</em></li>
                    @endif
                    @if ($jumlah['tertunda'] > 0)
                        <li><i style="background:#d9a441"></i>Tertunda <em>{{ $jumlah['tertunda'] }} ({{ $persen['tertunda'] }}%)</em></li>
                    @endif
                    @if ($jumlah['lainnya'] > 0)
                        <li><i style="background:#6b7686"></i>Lainnya <em>{{ $jumlah['lainnya'] }} ({{ $persen['lainnya'] }}%)</em></li>
                    @endif
                </ul>
            </div>
            @if (!empty($statusLain))
                <p class="catatan-status">Status belum dikenali:
                    @foreach ($statusLain as $nama => $n){{ $nama }} ({{ $n }}){{ !$loop->last ? ', ' : '' }}@endforeach
                </p>
            @endif
        </section>

        <section class="panel">
            <h3>Kehadiran peserta</h3>
            <div class="hadir-besar">
                <b>{{ $persenHadir }}%</b>
                <span>rata-rata kehadiran</span>
            </div>
            <div class="bar"><i style="width:{{ min(100, (float) $persenHadir) }}%"></i></div>
            <dl class="hadir-angka">
                <div><dt>Undangan</dt><dd>{{ number_format($undangan, 0, ',', '.') }}</dd></div>
                <div><dt>Hadir</dt><dd>{{ number_format($hadir, 0, ',', '.') }}</dd></div>
            </dl>

            <details class="daftar-hadir">
                <summary>Lihat nama peserta yang hadir (<span id="jmlHadir">0</span> orang)</summary>

                <div class="filter-hadir">
                    @if ($daftarHadir['bisaFilter'])
                        <select id="filterRapat" aria-label="Filter nama rapat">
                            <option value="">Semua rapat</option>
                            @foreach ($daftarHadir['rapat'] as $r)
                                <option value="{{ $r['id'] }}">{{ $r['nama'] }}</option>
                            @endforeach
                        </select>
                    @endif
                    <input type="search" id="cariHadir" placeholder="Cari nama peserta atau unit…" aria-label="Cari peserta" autocomplete="off">
                </div>

                <ol id="listHadir"></ol>
                <p class="kosong-hadir" id="kosongHadir" hidden>Tidak ada peserta yang cocok.</p>
            </details>
        </section>
    </div>

    {{-- ===== Rekap per unit + notulensi ===== --}}
    <div class="grid-bawah">
        <section class="panel">
            <h3>Rekap rapat per unit</h3>
            <div class="tabel-scroll">
                <table class="tabel-unit">
                    <thead>
                        <tr>
                            <th>No.</th><th>Unit / Direktorat</th><th class="c">Rapat</th>
                            <th class="c">Selesai</th><th class="c">Berlangsung</th><th class="c">Dibatalkan</th><th class="c">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($perUnit as $i => $u)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $u['unit'] }}</td>
                                <td class="c">{{ $u['jumlah'] }}</td>
                                <td class="c {{ $u['selesai'] ? 'n-hijau' : 'nol' }}">{{ $u['selesai'] }}</td>
                                <td class="c {{ $u['berlangsung'] ? 'n-biru' : 'nol' }}">{{ $u['berlangsung'] }}</td>
                                <td class="c {{ $u['dibatalkan'] ? 'n-merah' : 'nol' }}">{{ $u['dibatalkan'] }}</td>
                                <td class="c">{{ is_null($u['kehadiran']) ? '-' : $u['kehadiran'] . '%' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="kosong">Belum ada data rapat pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="kolom-kanan">
            <section class="panel">
                <h3>Notulensi dan dokumentasi</h3>
                <dl class="rekap">
                    <div><dt>Notulensi tersedia</dt><dd>{{ $notulensi['jumlah'] }} rapat <small>({{ $notulensi['persen'] }}%)</small></dd></div>
                    <div><dt>Dokumentasi foto</dt><dd>{{ $dokumentasi['jumlah'] }} rapat <small>({{ $dokumentasi['persen'] }}%)</small></dd></div>
                </dl>
            </section>

            <a href="{{ route('laporan.index') }}" class="tombol-unduh">Unduh laporan rekap lengkap (PDF/Excel)</a>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $dataStatus = array_values([
            $jumlah['selesai'], $jumlah['berlangsung'], $jumlah['dibatalkan'],
            $jumlah['belum'], $jumlah['tertunda'], $jumlah['lainnya'],
        ]);
    @endphp
    <script>
        // ===== Daftar peserta hadir: filter nama rapat + pencarian =====
        (function () {
            const data   = @json($daftarHadir['baris']);
            const selEl  = document.getElementById('filterRapat');
            const cariEl = document.getElementById('cariHadir');
            const listEl = document.getElementById('listHadir');
            const kosong = document.getElementById('kosongHadir');
            const jmlEl  = document.getElementById('jmlHadir');

            function render() {
                const rapatId = selEl ? selEl.value : '';
                const q = cariEl.value.trim().toLowerCase();

                // gabungkan per orang (+ unit) sesuai rapat yang dipilih
                const peta = new Map();
                data.forEach(r => {
                    if (rapatId && r.id_rapat !== rapatId) return;
                    const key = r.peserta + '|' + r.unit;
                    const o = peta.get(key) || { peserta: r.peserta, unit: r.unit, kali: 0 };
                    o.kali += r.kali;
                    peta.set(key, o);
                });

                const hasil = [...peta.values()]
                    .filter(o => !q || (o.peserta + ' ' + o.unit).toLowerCase().includes(q))
                    .sort((a, b) => a.peserta.localeCompare(b.peserta, 'id'));

                jmlEl.textContent = hasil.length;
                listEl.replaceChildren();

                hasil.forEach(o => {
                    const li = document.createElement('li');

                    const nm = document.createElement('span');
                    nm.className = 'nm';
                    nm.textContent = o.peserta;
                    const sm = document.createElement('small');
                    sm.textContent = o.unit;
                    nm.appendChild(sm);
                    li.appendChild(nm);

                    if (o.kali > 1) {
                        const k = document.createElement('span');
                        k.className = 'kali';
                        k.textContent = o.kali + '×';
                        li.appendChild(k);
                    }
                    listEl.appendChild(li);
                });

                kosong.hidden = hasil.length > 0;
                kosong.textContent = data.length
                    ? 'Tidak ada peserta yang cocok.'
                    : 'Belum ada data kehadiran pada periode ini.';
            }

            if (selEl) selEl.addEventListener('change', render);
            cariEl.addEventListener('input', render);
            render();
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const tren   = @json($tren);
        const status = @json($dataStatus);
        const total  = @json($total);

        Chart.defaults.font.family = '"IBM Plex Sans", "Segoe UI", sans-serif';
        Chart.defaults.color = '#667085';

        // Angka di atas titik pada grafik garis (teks biasa, tanpa kotak)
        const labelTitik = {
            id: 'labelTitik',
            afterDatasetsDraw(chart) {
                const ctx = chart.ctx;
                ctx.save();
                ctx.font = '600 12px "IBM Plex Sans", "Segoe UI", sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillStyle = '#1b2a3f';
                chart.getDatasetMeta(0).data.forEach((p, i) => {
                    ctx.fillText(chart.data.datasets[0].data[i], p.x, p.y - 8);
                });
                ctx.restore();
            }
        };

        new Chart(document.getElementById('chartTren'), {
            type: 'line',
            data: {
                labels: tren.label,
                datasets: [{
                    data: tren.data,
                    borderColor: '#1f4e8c',
                    backgroundColor: 'transparent',
                    fill: false, tension: 0, borderWidth: 2,
                    pointRadius: 3, pointBackgroundColor: '#1f4e8c', pointBorderColor: '#1f4e8c'
                }]
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { top: 22 } },
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eceff3' }, border: { display: false } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            },
            plugins: [labelTitik]
        });

        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: total ? status : [1],
                    backgroundColor: total ? ['#2f7d4f', '#1f4e8c', '#b3402f', '#9aa5b5', '#d9a441', '#6b7686'] : ['#e8ebef'],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: { cutout: '68%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
        });
    </script>
@endpush