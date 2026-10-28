<x-layouts.app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold">Cadangan</h1></x-slot>

    @php
        $stale = ! $latest || $latest['time']->lt(now()->subDays(2));
        $fmt = fn ($b) => $b >= 1048576
            ? number_format($b / 1048576, 2, ',', '.') . ' MB'
            : number_format($b / 1024, 1, ',', '.') . ' KB';
    @endphp

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Cadangan Data</h1>
            <p class="text-gray-500">Salinan seluruh data (transaksi, pelanggan, pengaturan) untuk berjaga kalau komputer rusak.</p>
        </div>

        <form method="POST" action="{{ route('backups.run') }}" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <button :disabled="busy" class="bg-brand-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50">
                <span x-show="!busy">Buat Cadangan Sekarang</span>
                <span x-show="busy" x-cloak>Membuat cadangan...</span>
            </button>
        </form>
    </div>

    @if($stale)
        <div class="bg-warning-50 text-warning-600 rounded-xl px-4 py-3 text-sm mb-4">
            @if($latest)
                Cadangan terakhir dibuat {{ $latest['time']->diffForHumans() }}. Sebaiknya ada cadangan baru setiap hari.
            @else
                Belum ada cadangan sama sekali. Klik "Buat Cadangan Sekarang".
            @endif
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6">
        @if($files->isEmpty())
            <x-empty-state title="Belum ada cadangan" description="Cadangan yang dibuat muncul di sini." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">File</th>
                            <th class="px-4 py-3 font-medium">Dibuat</th>
                            <th class="px-4 py-3 font-medium text-right">Ukuran</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($files as $f)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $f['name'] }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $f['time']->translatedFormat('d M Y, H:i') }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($f['size']) }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('backups.download', $f['name']) }}" class="text-brand-600 text-xs">Unduh</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-5 text-sm text-gray-600 space-y-3 max-w-3xl">
        <h2 class="font-semibold text-gray-900">Supaya cadangan benar-benar aman</h2>
        <ul class="list-disc ml-5 space-y-1.5">
            <li>Cadangan yang hanya tersimpan di komputer yang sama ikut hilang kalau komputernya rusak atau dicuri. <span class="font-medium">Salin file terbaru ke flashdisk atau tempat lain</span> secara berkala.</li>
            <li>File cadangan berisi semua data pelanggan dan kata sandi terenkripsi. Simpan di tempat yang aman dan jangan dikirim lewat grup.</li>
            <li>Sistem menyimpan {{ config('backup.keep') }} cadangan terakhir, yang lebih lama dihapus otomatis.</li>
            <li>Foto profil dan logo ada di folder <span class="font-mono text-xs">storage/app/public</span> dan tidak ikut di file ini. Salin folder itu juga kalau perlu.</li>
            <li>Cara memulihkan: buat database kosong, lalu impor file .sql lewat HeidiSQL atau perintah <span class="font-mono text-xs">mysql -u root nama_database &lt; file.sql</span>.</li>
        </ul>
    </div>
</x-layouts.app-layout>