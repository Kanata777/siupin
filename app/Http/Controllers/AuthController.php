<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login / logout SIUPIN (berbasis tabel `users`).
 * Masuk memakai username + kata sandi. Akun lama juga masih boleh memakai email.
 */
class AuthController extends Controller
{
    /** Maksimal percobaan gagal per username + IP sebelum dikunci sementara */
    private const MAKS_PERCOBAAN = 5;
    private const KUNCI_DETIK    = 60;

    public function tampil()
    {
        return view('auth.login');
    }

    public function masuk(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $login = Str::lower(trim($data['username']));
        $kunci = $login . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            throw ValidationException::withMessages([
                'username' => 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . RateLimiter::availableIn($kunci) . ' detik.',
            ]);
        }

        // Akun lama boleh tetap masuk memakai email; selain itu memakai username
        $kolom = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$kolom => $login, 'password' => $data['password']], $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, self::KUNCI_DETIK);

            throw ValidationException::withMessages([
                'username' => 'Username atau kata sandi salah.',
            ]);
        }

        RateLimiter::clear($kunci);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function keluar(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}