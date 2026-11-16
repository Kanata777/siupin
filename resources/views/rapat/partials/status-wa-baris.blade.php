@php $label = ['pending' => 'Pending', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal']; @endphp
@forelse ($daftar as $i => $u)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $u->nama }}</td>
        <td>{{ $u->nomor_wa }}</td>
        <td class="c"><span class="status-wa {{ $u->status }}">{{ $label[$u->status] ?? $u->status }}</span></td>
        <td>{{ $u->terkirim_pada ? $u->terkirim_pada->format('d/m/Y H:i') : '—' }}</td>
        <td>{{ $u->status === 'gagal' ? $u->error : '' }}</td>
        <td class="aksi-baris">
            @if ($u->status === 'gagal')
                <button type="button" class="btn-aksi" data-aksi="ulang" data-id="{{ $u->id }}">
                    <svg class="i"><use href="#i-ulang"/></svg> Kirim Ulang
                </button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="7" class="kosong">Tidak ada data untuk filter ini.</td></tr>
@endforelse