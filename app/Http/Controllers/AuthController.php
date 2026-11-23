<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login / logout SIUPIN (berbasis tabel `users`).
 * Akun dibuat lewat: php artisan siupin:pengguna "Nama" email@contoh.com --role=user
 */
class AuthController extends Controller
{
    /** Maksimal percobaan gagal per email + IP sebelum dikunci sementara */
    private const MAKS_PERCOBAAN = 5;
    private const KUNCI_DETIK    = 60;

    public function tampil()
    {
        return view('auth.login');
    }

    public function masuk(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $kunci = Str::lower($data['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . RateLimiter::availableIn($kunci) . ' detik.',
            ]);
        }

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, self::KUNCI_DETIK);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
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
