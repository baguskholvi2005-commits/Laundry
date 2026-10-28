<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    private function dir(): string
    {
        return storage_path('app/backups');
    }

    public function index()
    {
        $files = collect(File::glob($this->dir() . DIRECTORY_SEPARATOR . 'backup-*.sql'))
            ->map(fn ($path) => [
                'name' => basename($path),
                'size' => filesize($path),
                'time' => Carbon::createFromTimestamp(filemtime($path)),
            ])
            ->sortByDesc('name')
            ->values();

        return view('backups.index', ['files' => $files, 'latest' => $files->first()]);
    }

    public function run()
    {
        set_time_limit(300);

        $code = Artisan::call('backup:run');
        $output = trim(Artisan::output());

        return $code === 0
            ? back()->with('success', $output ?: 'Cadangan dibuat.')
            : back()->with('error', $output ?: 'Cadangan gagal dibuat.');
    }

    public function download(string $file)
    {
        // Hanya nama file cadangan yang sah, supaya tidak bisa menembus ke file lain
        abort_unless(preg_match('/^backup-\d{8}-\d{6}\.sql$/', $file) === 1, 404);

        $path = $this->dir() . DIRECTORY_SEPARATOR . $file;
        abort_unless(is_file($path), 404);

        ActivityLog::record('backup', 'mengunduh cadangan ' . $file);

        return response()->download($path);
    }
}