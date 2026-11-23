<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanNotulensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Draft boleh kosong; finalisasi mewajibkan tiga kolom utama.
        $wajib = $this->input('aksi') === 'final' ? 'required' : 'nullable';

        return [
            'aksi'                => ['required', 'in:draft,final'],
            'agenda'              => [$wajib, 'string', 'max:5000'],
            'pembahasan'          => [$wajib, 'string', 'max:20000'],
            'keputusan'           => [$wajib, 'string', 'max:20000'],
            'tl'                  => ['nullable', 'array', 'max:50'],
            'tl.*.uraian'         => ['nullable', 'string', 'max:255'],
            'tl.*.pic'            => ['nullable', 'string', 'max:100'],
            'tl.*.target_tanggal' => ['nullable', 'date'],
            'tl.*.status'         => ['nullable', 'in:belum,proses,selesai'],
            'foto'                => ['nullable', 'array', 'max:20'],
            'foto.*'              => ['image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'lampiran'            => ['nullable', 'array', 'max:10'],
            'lampiran.*'          => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'hapus_berkas'        => ['nullable', 'array'],
            'hapus_berkas.*'      => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'agenda'     => 'Agenda rapat',
            'pembahasan' => 'Jalannya pembahasan',
            'keputusan'  => 'Hasil / keputusan rapat',
            'foto.*'     => 'Foto dokumentasi',
            'lampiran.*' => 'Lampiran file',
        ];
    }

    public function messages(): array
    {
        return [
            'required'            => ':attribute wajib diisi.',
            'foto.*.image'        => 'Foto dokumentasi harus berupa gambar JPG atau PNG.',
            'foto.*.mimes'        => 'Foto dokumentasi harus berformat JPG atau PNG.',
            'foto.*.max'          => 'Ukuran foto maksimal 5 MB per file.',
            'foto.*.uploaded'     => 'Foto gagal diunggah. Periksa ukuran file (maksimal 5 MB).',
            'lampiran.*.mimes'    => 'Lampiran harus berformat PDF, Word, Excel, atau PowerPoint.',
            'lampiran.*.max'      => 'Ukuran lampiran maksimal 10 MB per file.',
            'lampiran.*.uploaded' => 'Lampiran gagal diunggah. Periksa ukuran file (maksimal 10 MB).',
        ];
    }
}
