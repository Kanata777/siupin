@php use App\Models\TindakLanjutNotulensi as TL; @endphp
<tr>
    <td class="no">{{ is_numeric($i) ? $i + 1 : '' }}</td>
    <td><input type="text" name="tl[{{ $i }}][uraian]" value="{{ $b['uraian'] ?? '' }}" maxlength="255" aria-label="Tindak lanjut" @disabled($terkunci)></td>
    <td><input type="text" name="tl[{{ $i }}][pic]" value="{{ $b['pic'] ?? '' }}" maxlength="100" aria-label="PIC" @disabled($terkunci)></td>
    <td class="tgl"><input type="date" name="tl[{{ $i }}][target_tanggal]" value="{{ $b['target_tanggal'] ?? '' }}" aria-label="Target waktu" @disabled($terkunci)></td>
    <td class="sts">
        <select name="tl[{{ $i }}][status]" aria-label="Status" @disabled($terkunci)>
            @foreach (TL::STATUS as $nilai => $teks)
                <option value="{{ $nilai }}" @selected(($b['status'] ?? 'belum') === $nilai)>{{ $teks }}</option>
            @endforeach
        </select>
    </td>
    <td class="aksi">@unless ($terkunci)<button type="button" class="nt-hapus" aria-label="Hapus baris">Hapus</button>@endunless</td>
</tr>
