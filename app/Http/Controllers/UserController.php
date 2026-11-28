<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Kelola akun login (khusus Super Admin, dijaga middleware super_admin di route).
 * User baru otomatis berperan "user" (default kolom role), tidak bisa diatur dari form.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $pengguna = User::query()
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                $x->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%");
            }))
            ->orderByRaw("role = 'super_admin' DESC")
            ->orderBy('name')
            ->get();

        return view('pengguna.index', compact('pengguna'));
    }

    public function store(Request $request)
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required'      => 'Nama wajib diisi.',
            'username.required'  => 'Username wajib diisi.',
            'username.min'       => 'Username minimal 3 karakter.',
            'username.regex'     => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.',
            'username.unique'    => 'Username sudah dipakai.',
            'password.required'  => 'Kata sandi wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        // Kata sandi di-hash otomatis oleh cast 'hashed' di model User
        User::create([
            'name'     => trim($data['name']),
            'username' => $data['username'],
            'password' => $data['password'],
        ]);

        return redirect()->route('pengguna.index')
            ->with('sukses', 'User "' . $data['username'] . '" berhasil ditambahkan.');
    }
}