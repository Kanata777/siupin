<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Membuat / memperbarui akun SIUPIN.
 *
 *   php artisan siupin:pengguna "SDM YNH" admin@contoh.com --role=super_admin
 *   php artisan siupin:pengguna "Budi" budi@contoh.com
 *
 * Kata sandi ditanyakan saat perintah dijalankan (tidak tampil di layar / riwayat perintah).
 * Bila email sudah ada, nama, kata sandi, dan role akun itu diperbarui.
 */
class BuatPengguna extends Command
{
    protected $signature = 'siupin:pengguna
                            {nama : Nama lengkap (dipakai sebagai nama penyelenggara rapat)}
                            {email : Email untuk login}
                            {--role=user : super_admin atau user}';

    protected $description = 'Buat atau perbarui akun pengguna SIUPIN';

    public function handle(): int
    {
        $role = (string) $this->option('role');
        if (!in_array($role, [User::ROLE_USER, User::ROLE_SUPER_ADMIN], true)) {
            $this->error('Role harus "super_admin" atau "user".');
            return self::FAILURE;
        }

        $nama  = trim((string) $this->argument('nama'));
        $email = strtolower(trim((string) $this->argument('email')));

        if ($nama === '') {
            $this->error('Nama tidak boleh kosong.');
            return self::FAILURE;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');
            return self::FAILURE;
        }

        $sandi = (string) $this->secret('Kata sandi (minimal 8 karakter)');
        if (strlen($sandi) < 8) {
            $this->error('Kata sandi minimal 8 karakter.');
            return self::FAILURE;
        }
        if ($sandi !== (string) $this->secret('Ulangi kata sandi')) {
            $this->error('Kata sandi tidak sama.');
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $baru = !$user->exists;

        $user->name     = $nama;
        $user->password = $sandi;   // di-hash otomatis oleh cast "hashed" pada model User
        $user->role     = $role;    // role tidak mass-assignable, jadi diisi langsung
        $user->save();

        $this->info(($baru ? 'Akun dibuat' : 'Akun diperbarui') . ": {$user->name} <{$user->email}> ({$user->role})");

        return self::SUCCESS;
    }
}
