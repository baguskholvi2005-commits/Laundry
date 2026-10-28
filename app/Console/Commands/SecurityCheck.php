<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

class SecurityCheck extends Command
{
    protected $signature = 'app:security-check';

    protected $description = 'Memeriksa kesiapan aplikasi sebelum dipakai sungguhan';

    private int $issues = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Pemeriksaan kesiapan aplikasi</>');
        $this->newLine();

        $this->row('Mode debug mati (APP_DEBUG=false)', ! config('app.debug'),
            'Mode debug menampilkan isi kode dan pengaturan saat terjadi error. Ubah di .env.');

        $this->row('Lingkungan produksi (APP_ENV=production)', config('app.env') === 'production',
            'Ubah di .env saat aplikasi sudah dipasang di tempat kerja.');

        // Alamat aplikasi menentukan isi QR di nota dan link WhatsApp
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $isLocal = $host === ''
            || $host === 'localhost'
            || preg_match('/\.(test|local|localhost)$/', $host)
            || (filter_var($host, FILTER_VALIDATE_IP)
                && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));

        $this->row('APP_URL berupa alamat publik (' . ($host ?: 'kosong') . ')', ! $isLocal,
            'QR di nota dan link WhatsApp hanya bisa dibuka dari jaringan yang sama. Abaikan kalau aplikasi hanya dipakai di toko.');

        if (str_starts_with((string) config('app.url'), 'https://')) {
            $this->row('Cookie sesi hanya lewat HTTPS (SESSION_SECURE_COOKIE=true)', (bool) config('session.secure'),
                'Tambahkan SESSION_SECURE_COOKIE=true di .env.');
        }

        // Akun bawaan yang kata sandinya masih "password"
        $weak = User::all()
            ->filter(fn ($u) => Hash::check('password', $u->password))
            ->pluck('email');

        $this->row('Tidak ada akun yang masih memakai kata sandi bawaan', $weak->isEmpty(),
            'Ganti kata sandi: ' . $weak->join(', ') . '. Perintah: php artisan app:reset-password email-akun');

        $latest = collect(File::glob(storage_path('app/backups/backup-*.sql')))->sort()->last();
        $fresh = $latest && filemtime($latest) > now()->subDays(2)->getTimestamp();

        $this->row('Ada cadangan database kurang dari 2 hari lalu', (bool) $fresh,
            $latest
                ? 'Cadangan terakhir: ' . basename($latest) . '. Jadwalkan php artisan backup:run setiap hari.'
                : 'Belum ada cadangan. Jalankan: php artisan backup:run');

        $dumpOk = false;
        try {
            $p = new Process([config('backup.mysqldump_path'), '--version']);
            $p->run();
            $dumpOk = $p->isSuccessful();
        } catch (\Throwable) {
            // dianggap tidak tersedia
        }

        $this->row('Program mysqldump ditemukan (untuk cadangan)', $dumpOk,
            'Isi DB_DUMP_PATH di .env dengan lokasi lengkap mysqldump.exe (pakai garis miring biasa /).');

        $this->row('Folder penyimpanan terhubung (storage:link)',
            is_link(public_path('storage')) || is_dir(public_path('storage')),
            'Jalankan: php artisan storage:link');

        $this->newLine();

        if ($this->issues === 0) {
            $this->info('Semua pemeriksaan lolos.');
            return self::SUCCESS;
        }

        $this->warn($this->issues . ' hal perlu dicek (tanda CEK di atas). Tidak semuanya wajib, baca keterangannya.');

        return self::FAILURE;
    }

    private function row(string $label, bool $ok, string $hint = ''): void
    {
        if ($ok) {
            $this->line('  <fg=green>OK </>  ' . $label);
            return;
        }

        $this->issues++;
        $this->line('  <fg=yellow>CEK</>  ' . $label);

        if ($hint !== '') {
            $this->line('        ' . $hint);
        }
    }
}