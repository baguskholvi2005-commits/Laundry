<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Layanan</span></x-slot>

    @php
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $tabs = ['semua' => 'Semua Layanan', 'kiloan' => 'Kiloan', 'satuan' => 'Satuan', 'nonaktif' => 'Nonaktif'];
        $kilo = $services->where('type', 'kiloan');
        $pcs = $services->where('type', 'satuan');
        $days = fn ($s) => $s->duration_days ?: $defaultDays;
    @endphp

    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">Operasional / Katalog layanan dan tarif</p>
            <h1 class="text-3xl font-bold text-gray-900">Katalog Layanan &amp; Tarif Cuci</h1>
            <p class="text-gray-500">Atur layanan kiloan dan satuan, harga, dan estimasi pengerjaan.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('services.tariff') }}" target="_blank" class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Unduh Daftar Tarif</a>
            @role('pemilik')
                <a href="{{ route('services.create') }}" class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-semibold hover:bg-brand-700">+ Tambah Layanan Baru</a>
            @endrole
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Total layanan aktif</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $counts['semua'] }} <span class="text-base font-semibold text-gray-500">layanan</span></p>
            <p class="text-sm text-brand-700 mt-1">{{ $counts['kiloan'] }} kiloan · {{ $counts['satuan'] }} satuan</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Layanan paling laris</p>
            <p class="text-xl font-bold text-gray-900 mt-2">{{ $topService->name ?? 'Belum ada transaksi' }}</p>
            <p class="text-sm text-brand-700 mt-1">{{ $topService ? $topShare . '% dari nota bulan ini' : 'Muncul setelah ada transaksi bulan ini' }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Rata-rata estimasi selesai</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $avgDuration !== null ? rtrim(rtrim(number_format($avgDuration, 1, ',', '.'), '0'), ',') : '-' }} <span class="text-base font-semibold text-gray-500">hari</span></p>
            <p class="text-sm text-gray-500 mt-1">Layanan tanpa estimasi memakai {{ $defaultDays }} hari</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-5">
        @foreach($tabs as $key => $label)
            <a href="{{ route('services.index', $key === 'semua' ? [] : ['tab' => $key]) }}"
               class="rounded-xl border px-4 py-2.5 text-sm font-medium flex items-center gap-2 {{ $tab === $key ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300' }}">
                {{ $label }}
                <span class="text-[11px] font-semibold rounded-md px-1.5 py-0.5 {{ $tab === $key ? 'bg-white/25' : 'bg-brand-50 text-brand-700' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    @if($services->isEmpty())
        <x-empty-state title="Tidak ada layanan di tab ini" description="Tambahkan layanan lewat tombol di atas." />
    @endif

    {{-- Kartu kiloan --}}
    @if($kilo->isNotEmpty())
        <h2 class="font-semibold text-gray-900 mb-3">Layanan kiloan</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
            @foreach($kilo as $s)
                <div class="bg-white rounded-2xl border border-gray-100 p-5 flex flex-col {{ $s->is_active ? '' : 'opacity-70' }}">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-bold text-gray-900 leading-snug">{{ $s->name }}</h3>
                        @if($topService && $topService->id === $s->id)
                            <span class="text-[11px] font-semibold bg-success-50 text-success-700 rounded-md px-2 py-0.5">Terlaris</span>
                        @endif
                        @unless($s->is_active)<span class="text-[11px] bg-gray-100 text-gray-500 rounded-md px-2 py-0.5">Nonaktif</span>@endunless
                    </div>
                    <div class="rounded-xl bg-brand-50 p-3 mt-3 flex items-end justify-between">
                        <span class="text-xs text-gray-500">Tarif pokok</span>
                        <span class="text-2xl font-bold text-brand-800 tabular-nums">{{ $rp($s->price) }}<span class="text-xs font-normal text-gray-500">/kg</span></span>
                    </div>
                    <p class="text-sm text-gray-500 mt-3">Estimasi: <span class="font-semibold text-gray-800">{{ $days($s) }} hari</span>@unless($s->duration_days) <span class="text-xs text-gray-400">(umum)</span>@endunless</p>

                    <div class="flex items-center gap-2 mt-auto pt-4">
                        @role('pemilik')
                            <a href="{{ route('services.edit', $s) }}" class="flex-1 text-center bg-brand-50 text-brand-700 rounded-xl py-2 text-sm font-medium hover:bg-brand-100">Edit tarif</a>
                            <form method="POST" action="{{ route('services.toggle', $s) }}" @if($s->is_active) onsubmit="return confirm('Nonaktifkan layanan ini? Layanan tidak akan muncul di kasir dan akun pelanggan.')" @endif>
                                @csrf @method('PATCH')
                                <button class="rounded-xl px-3 py-2 text-sm font-medium {{ $s->is_active ? 'text-danger-600 hover:bg-danger-50' : 'text-success-600 hover:bg-success-50' }}">{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                            <form method="POST" action="{{ route('services.destroy', $s) }}" onsubmit="return confirm('Hapus layanan ini?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-gray-400 hover:text-danger-600 px-1">Hapus</button>
                            </form>
                        @endrole
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Tabel satuan --}}
    @if($pcs->isNotEmpty())
        <h2 class="font-semibold text-gray-900 mb-3">Layanan satuan</h2>
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[560px]">
                    <thead class="bg-brand-50 text-gray-600 text-left">
                        <tr>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Nama layanan</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase text-right">Tarif</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Estimasi</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Status</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pcs as $s)
                            <tr class="border-t border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $s->name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-brand-700 whitespace-nowrap">{{ $rp($s->price) }}<span class="text-xs font-normal text-gray-500">/pcs</span></td>
                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $days($s) }} hari</td>
                                <td class="px-4 py-3">
                                    <span class="text-[11px] font-semibold rounded-md px-2 py-1 {{ $s->is_active ? 'bg-success-50 text-success-700' : 'bg-gray-100 text-gray-500' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @role('pemilik')
                                        <div class="flex items-center gap-3 text-xs">
                                            <a href="{{ route('services.edit', $s) }}" class="text-brand-600 font-medium">Edit</a>
                                            <form method="POST" action="{{ route('services.toggle', $s) }}" @if($s->is_active) onsubmit="return confirm('Nonaktifkan layanan ini?')" @endif>
                                                @csrf @method('PATCH')
                                                <button class="{{ $s->is_active ? 'text-danger-600' : 'text-success-600' }}">{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('services.destroy', $s) }}" onsubmit="return confirm('Hapus layanan ini?')">
                                                @csrf @method('DELETE')
                                                <button class="text-gray-400 hover:text-danger-600">Hapus</button>
                                            </form>
                                        </div>
                                    @endrole
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="text-xs text-gray-400 mt-4">Tambahan seperti pewangi atau plastik tebal cukup dibuat sebagai layanan satuan, misalnya "Pewangi Premium" Rp2.500, lalu dipilih di kasir bersama layanan utama.</p>
</x-layouts.app-layout>