<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResetPassword extends Command
{
    protected $signature = 'app:reset-password {email : Email akun yang kata sandinya diganti}';

    protected $description = 'Mengganti kata sandi akun staf (untuk pemilik yang lupa kata sandi)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Akun dengan email itu tidak ditemukan.');
            return self::FAILURE;
        }

        $this->line('Akun: ' . $user->name . ' (' . ($user->roles->first()->name ?? 'tanpa role') . ')');
        $this->comment('Catatan: di Windows, kata sandi bisa terlihat saat diketik.');

        $password = (string) $this->secret('Kata sandi baru (minimal 8 karakter)');

        if (mb_strlen($password) < 8) {
            $this->error('Kata sandi terlalu pendek. Tidak ada yang diubah.');
            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Ulangi kata sandi baru')) {
            $this->error('Kedua kata sandi tidak sama. Tidak ada yang diubah.');
            return self::FAILURE;
        }

        $user->password = $password;
        $user->setRememberToken(Str::random(60));
        $user->save();

        ActivityLog::record('user_changed', 'mengganti kata sandi ' . $user->name . ' lewat perintah server');

        $this->info('Kata sandi ' . $user->email . ' berhasil diganti.');

        return self::SUCCESS;
    }
}