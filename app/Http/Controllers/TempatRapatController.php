<?php

namespace App\Http\Controllers;

use App\Models\TempatRapat;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TempatRapatController extends Controller
{
    /** Halaman Tempat Rapat: daftar + pencarian */
    public function index(Request $request)
    {
        $kata = trim((string) $request->query('q'));

        $tempat = TempatRapat::query()
            ->when($kata !== '', function ($q) use ($kata) {
                $like = '%' . $kata . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('nama_tempat', 'like', $like)
                      ->orWhere('gedung', 'like', $like);
                });
            })
            ->orderBy('nama_tempat')
            ->get();

        return view('tempat.index', compact('tempat'));
    }

    /** Simpan tempat baru */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_tempat' => ['required', 'string', 'max:100', 'unique:tempat_rapat,nama_tempat'],
            'gedung'      => ['nullable', 'string', 'max:100'],
        ], [
            'nama_tempat.required' => 'Nama tempat wajib diisi.',
            'nama_tempat.unique'   => 'Nama tempat ini sudah ada.',
            'nama_tempat.max'      => 'Nama tempat maksimal 100 karakter.',
            'gedung.max'           => 'Nama gedung maksimal 100 karakter.',
        ]);

        TempatRapat::create($data);

        return redirect()->route('tempat.index')->with('sukses', 'Tempat rapat berhasil ditambahkan.');
    }

    /** Ubah tempat yang sudah ada */
    public function update(Request $request, TempatRapat $tempat)
    {
        $data = $request->validate([
            'nama_tempat' => ['required', 'string', 'max:100', Rule::unique('tempat_rapat', 'nama_tempat')->ignore($tempat->id_tempat, 'id_tempat')],
            'gedung'      => ['nullable', 'string', 'max:100'],
        ], [
            'nama_tempat.required' => 'Nama tempat wajib diisi.',
            'nama_tempat.unique'   => 'Nama tempat ini sudah ada.',
            'nama_tempat.max'      => 'Nama tempat maksimal 100 karakter.',
            'gedung.max'           => 'Nama gedung maksimal 100 karakter.',
        ]);

        $tempat->update($data);

        return redirect()->route('tempat.index')->with('sukses', 'Tempat rapat berhasil diperbarui.');
    }

    /** Hapus tempat */
    public function destroy(TempatRapat $tempat)
    {
        try {
            $tempat->delete();
        } catch (QueryException $e) {
            // Biasanya karena tempat ini masih dipakai oleh data rapat (foreign key)
            return redirect()->route('tempat.index')
                ->with('gagal', 'Tempat tidak bisa dihapus karena masih dipakai oleh data rapat.');
        }

        return redirect()->route('tempat.index')->with('sukses', 'Tempat rapat berhasil dihapus.');
    }
}