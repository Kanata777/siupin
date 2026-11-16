@forelse ($daftar as $i => $g)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $g['nama'] }}</td>
        <td class="c">{{ $g['jumlah'] }} Orang</td>
        <td>{{ $g['keterangan'] }}</td>
        <td class="aksi-baris">
            <button type="button" class="btn-aksi edit"  data-aksi="edit"  data-id="{{ $g['id'] }}"><svg class="i"><use href="#i-pensil"/></svg> Edit</button>
            <button type="button" class="btn-aksi lihat" data-aksi="lihat" data-id="{{ $g['id'] }}"><svg class="i"><use href="#i-users"/></svg> Lihat</button>
            <button type="button" class="btn-aksi hapus" data-aksi="hapus" data-id="{{ $g['id'] }}" data-nama="{{ $g['nama'] }}"><svg class="i"><use href="#i-sampah"/></svg> Batal Kelompok</button>
        </td>
    </tr>
@empty
    <tr><td colspan="5" class="kosong">Belum ada kelompok rapat tersimpan.</td></tr>
@endforelse 