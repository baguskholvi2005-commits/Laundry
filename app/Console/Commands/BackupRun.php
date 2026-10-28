<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--keep= : Jumlah cadangan terbaru yang disimpan}';

    protected $description = 'Membuat cadangan database (file .sql) dan menghapus cadangan yang sudah lama';

    public function handle(): int
    {
        $conn = config('database.connections.' . config('database.default'));

        if (! in_array($conn['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('Cadangan otomatis hanya mendukung MySQL atau MariaDB.');
            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $file = $dir . DIRECTORY_SEPARATOR . 'backup-' . now()->format('Ymd-His') . '.sql';

        // Kata sandi database dikirim lewat file sementara, bukan lewat argumen perintah
        $cnf = tempnam(sys_get_temp_dir(), 'ldb');

        try {
            file_put_contents($cnf,
                "[client]\n"
                . 'user="' . $this->escape((string) $conn['username']) . "\"\n"
                . 'password="' . $this->escape((string) ($conn['password'] ?? '')) . "\"\n"
                . 'host="' . $this->escape((string) $conn['host']) . "\"\n"
                . 'port="' . $this->escape((string) $conn['port']) . "\"\n"
            );

            $process = new Process([
                config('backup.mysqldump_path'),
                '--defaults-extra-file=' . $cnf,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                '--result-file=' . $file,
                $conn['database'],
            ]);
            $process->setTimeout(300);
            $process->run();

            $ok = $process->isSuccessful() && $this->looksComplete($file);
            $detail = trim($process->getErrorOutput());
        } catch (\Throwable $e) {
            $ok = false;
            $detail = $e->getMessage();
        } finally {
            @unlink($cnf);
        }

        if (! $ok) {
            @unlink($file);
            $this->error('Cadangan gagal dibuat. Periksa DB_DUMP_PATH di .env (lokasi mysqldump).');
            if ($detail !== '') {
                $this->line(mb_substr($detail, 0, 300));
            }
            return self::FAILURE;
        }

        // Hapus cadangan lama, sisakan yang terbaru
        $keep = max(1, (int) ($this->option('keep') ?: config('backup.keep')));
        $files = collect(File::glob($dir . DIRECTORY_SEPARATOR . 'backup-*.sql'))->sort()->values();
        $files->slice(0, max(0, $files->count() - $keep))->each(fn ($old) => @unlink($old));

        $size = filesize($file);
        $sizeText = $size >= 1048576
            ? number_format($size / 1048576, 2) . ' MB'
            : number_format($size / 1024, 1) . ' KB';

        ActivityLog::record('backup', 'membuat cadangan database (' . $sizeText . ')');

        $this->info('Cadangan dibuat: ' . basename($file) . ' (' . $sizeText . ')');

        return self::SUCCESS;
    }

    // Hasil dump yang utuh selalu diakhiri baris "Dump completed"
    private function looksComplete(string $file): bool
    {
        if (! is_file($file) || filesize($file) < 200) {
            return false;
        }

        $handle = fopen($file, 'rb');
        fseek($handle, -min(400, filesize($file)), SEEK_END);
        $tail = fread($handle, 400);
        fclose($handle);

        return str_contains($tail, 'Dump completed');
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}