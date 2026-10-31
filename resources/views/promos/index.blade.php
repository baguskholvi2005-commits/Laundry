<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Promo</span></x-slot>

    @php
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $tabs = [
            'semua' => 'Semua Promo',
            'aktif' => 'Aktif Berjalan',
            'habis_kuota' => 'Habis Kuota',
            'belum_dimulai' => 'Belum Dimulai',
            'kadaluarsa' => 'Kadaluarsa',
            'nonaktif' => 'Nonaktif',
        ];
        $statusLabels = [
            'aktif' => 'Aktif berjalan', 'habis_kuota' => 'Habis kuota', 'kadaluarsa' => 'Kadaluarsa',
            'belum_dimulai' => 'Belum dimulai', 'nonaktif' => 'Nonaktif',
        ];
        $statusStyles = [
            'aktif' => 'bg-success-50 text-success-700',
            'habis_kuota' => 'bg-gold-100 text-warning-700',
            'kadaluarsa' => 'bg-danger-50 text-danger-600',
            'belum_dimulai' => 'bg-gray-100 text-gray-600',
            'nonaktif' => 'bg-gray-100 text-gray-500',
        ];
        $dotStyles = [
            'aktif' => 'bg-success-500', 'habis_kuota' => 'bg-warning-500', 'kadaluarsa' => 'bg-danger-500',
            'belum_dimulai' => 'bg-gray-400', 'nonaktif' => 'bg-gray-400',
        ];
        $countStyles = [
            'habis_kuota' => 'bg-gold-300 text-gray-900', 'kadaluarsa' => 'bg-danger-100 text-danger-600',
        ];
        $chip = 'inline-flex items-center gap-1.5 text-[11px] font-semibold tracking-wide uppercase rounded-md px-2.5 py-1 whitespace-nowrap';
        $pickupFee = (float) setting('pickup_fee', 0);
        $deliveryFee = (float) setting('delivery_fee', 0);
        $stackingOn = (bool) setting('promo_stacking_enabled');

        // Kartu besar untuk promo yang masih relevan, baris ringkas untuk yang sudah selesai atau dimatikan
        $all = $promos->getCollection();
        $cards = $all->reject(fn ($p) => in_array($p->status_label, ['kadaluarsa', 'nonaktif'], true));
        $rows = $all->filter(fn ($p) => in_array($p->status_label, ['kadaluarsa', 'nonaktif'], true));
    @endphp

    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-5 flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-brand-600"></span> Manajemen promo dan diskon
            </p>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">Program Promo</h1>
            <p class="text-gray-500 mt-1 max-w-2xl">
                Atur potongan harga dan gratis ongkir. Kasir memilih promo yang berlaku saat membuat transaksi, dan sistem mengecek syaratnya otomatis.
            </p>
        </div>
        <a href="{{ route('promos.create') }}"
           class="bg-brand-600 text-white px-5 py-3 rounded-xl text-sm font-semibold hover:bg-brand-700 whitespace-nowrap">+ Buat Promo Baru</a>
    </div>

    {{-- Tab status --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach($tabs as $key => $label)
            @php $n = $counts[$key] ?? 0; @endphp
            <a href="{{ route('promos.index', $key === 'semua' ? [] : ['status' => $key]) }}"
               class="rounded-xl border px-4 py-2.5 text-sm font-medium flex items-center gap-2
               {{ $status === $key ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300' }}">
                {{ $label }}
                @if($n > 0 || $key === 'semua')
                    <span class="text-[11px] font-semibold rounded-md px-1.5 py-0.5
                        {{ $status === $key ? 'bg-white/25 text-white' : ($countStyles[$key] ?? 'bg-brand-50 text-brand-700') }}">{{ $n }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if($promos->isEmpty())
        <x-empty-state
            :title="$status === 'semua' ? 'Belum ada promo' : 'Tidak ada promo di tab ini'"
            description="Buat promo pertama untuk menarik lebih banyak pelanggan." />
    @else

        {{-- ============ KARTU ============ --}}
        @if($cards->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-4">
            @foreach($cards as $p)
            @php
                $s = $p->status_label;
                $pct = $p->quota ? min(100, (int) round(($p->used_count / $p->quota) * 100)) : 0;
                $valueLabel = match (true) {
                    $p->type === 'gratis_ongkir' => 'Kompensasi ongkos',
                    $p->type === 'member' => 'Potongan member',
                    $p->value_type === 'persen' => 'Diskon',
                    default => 'Potongan harga',
                };
            @endphp
            <div class="bg-white rounded-2xl border border-gray-100 p-5 flex flex-col" x-data="{ addQuota: false }">

                <div class="flex items-center justify-between gap-2">
                    <span class="{{ $chip }} {{ $statusStyles[$s] }}"><span class="w-1.5 h-1.5 rounded-full {{ $dotStyles[$s] }}"></span>{{ $statusLabels[$s] }}</span>
                    <span class="text-xs {{ $p->type === 'member' ? 'font-semibold text-gold-600 bg-gold-50 rounded-md px-2 py-1' : 'text-gray-400' }}">{{ $p->type_label }}</span>
                </div>

                <a href="{{ route('promos.show', $p) }}" class="mt-3 text-lg font-bold text-gray-900 leading-snug hover:text-brand-700">{{ $p->name }}</a>

                <div class="rounded-xl bg-brand-50 p-3 mt-3">
                    <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">{{ $valueLabel }}</p>
                    <p class="text-2xl font-bold text-brand-800 mt-0.5">{{ $p->type === 'gratis_ongkir' ? 'GRATIS ONGKIR' : $p->value_text }}</p>
                    @if($p->type === 'gratis_ongkir')
                        <p class="text-xs text-gray-500 mt-0.5">Biaya jemput {{ $rp($pickupFee) }} · biaya antar {{ $rp($deliveryFee) }}</p>
                    @endif
                </div>

                <ul class="space-y-1.5 text-sm text-gray-600 mt-3">
                    @if($p->description)
                        <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-gray-300 flex-shrink-0"></span><span>{{ $p->description }}</span></li>
                    @endif
                    @if($p->min_weight)
                        <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-brand-500 flex-shrink-0"></span><span>Minimal cucian <span class="font-semibold">{{ (float) $p->min_weight }} kg</span></span></li>
                    @endif
                    @if($p->min_transaction)
                        <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-brand-500 flex-shrink-0"></span><span>Minimal transaksi <span class="font-semibold">{{ $rp($p->min_transaction) }}</span></span></li>
                    @endif
                    @if(! $p->min_weight && ! $p->min_transaction)
                        <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-brand-500 flex-shrink-0"></span><span>Tanpa syarat minimal</span></li>
                    @endif
                    @if($p->type === 'member')
                        <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-gold-500 flex-shrink-0"></span><span>Hanya untuk pelanggan member</span></li>
                    @endif
                    @if($p->start_time && $p->end_time)
                        <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-brand-500 flex-shrink-0"></span><span>Berlaku pukul {{ substr($p->start_time, 0, 5) }} – {{ substr($p->end_time, 0, 5) }}</span></li>
                    @endif
                    <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-brand-500 flex-shrink-0"></span>
                        <span>Berlaku <span class="font-semibold">{{ $p->start_date->translatedFormat('d M Y') }}</span> sampai <span class="font-semibold">{{ $p->end_date->translatedFormat('d M Y') }}</span></span></li>
                    @if($p->stackable)
                        <li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full bg-brand-500 flex-shrink-0"></span>
                            <span>Bisa digabung dengan promo lain @unless($stackingOn)<span class="text-xs text-warning-600">(penggabungan sedang dimatikan di Pengaturan)</span>@endunless</span></li>
                    @endif
                </ul>

                {{-- Kuota --}}
                <div class="rounded-xl bg-gray-50 p-3 mt-3">
                    @if($p->quota)
                        <div class="flex justify-between gap-3 text-xs">
                            <span class="text-gray-600">Penggunaan kuota</span>
                            <span class="font-semibold text-gray-800">{{ $p->used_count }} dari {{ $p->quota }} ({{ $pct }}%)</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full mt-2">
                            <div class="h-2 rounded-full {{ $s === 'habis_kuota' ? 'bg-danger-600' : 'bg-brand-600' }}" style="width: {{ $pct }}%"></div>
                        </div>
                    @else
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="text-gray-600">Tanpa batas kuota</span>
                            <span class="font-semibold text-gray-800">{{ $p->used_count }}x terpakai</span>
                        </div>
                    @endif
                </div>

                {{-- Aksi --}}
                <div class="mt-auto pt-4">
                    <div class="border-t border-gray-100 pt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ route('promos.edit', $p) }}" class="border bg-white rounded-xl px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ $s === 'belum_dimulai' ? 'Atur Jadwal' : 'Edit Promo' }}
                        </a>

                        @if($s === 'habis_kuota')
                            <button type="button" @click="addQuota = !addQuota"
                                    class="bg-gold-100 text-warning-700 rounded-xl px-3.5 py-2 text-sm font-semibold hover:bg-gold-300">+ Tambah Kuota</button>
                        @endif

                        <form method="POST" action="{{ route('promos.toggle', $p) }}"
                              @if($p->is_active) onsubmit="return confirm('Nonaktifkan promo ini? Promo tidak akan muncul di kasir.')" @endif>
                            @csrf @method('PATCH')
                            <button class="rounded-xl px-3 py-2 text-sm font-medium {{ $p->is_active ? 'text-danger-600 hover:bg-danger-50' : 'text-success-600 hover:bg-success-50' }}">
                                {{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>

                        @if($p->used_count === 0)
                            <form method="POST" action="{{ route('promos.destroy', $p) }}" class="ml-auto"
                                  onsubmit="return confirm('Hapus promo ini?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-gray-400 hover:text-danger-600 px-2 py-2">Hapus</button>
                            </form>
                        @endif
                    </div>

                    @if($s === 'habis_kuota')
                    <form method="POST" action="{{ route('promos.quota', $p) }}" x-show="addQuota" x-cloak class="flex items-center gap-2 mt-3">
                        @csrf @method('PATCH')
                        <input type="number" name="add" min="1" max="100000" required placeholder="Tambah berapa?"
                               class="flex-1 border-gray-200 rounded-xl text-sm">
                        <button class="bg-brand-600 text-white rounded-xl px-4 py-2 text-sm font-medium">Simpan</button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- ============ BARIS RINGKAS (kadaluarsa dan nonaktif) ============ --}}
        @if($rows->isNotEmpty())
        <div class="space-y-3 mb-4">
            @foreach($rows as $p)
            @php $s = $p->status_label; @endphp
            <div class="bg-white rounded-2xl border border-gray-100 p-4 flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="lg:w-36 flex-shrink-0">
                    <span class="{{ $chip }} {{ $statusStyles[$s] }}"><span class="w-1.5 h-1.5 rounded-full {{ $dotStyles[$s] }}"></span>{{ $statusLabels[$s] }}</span>
                </div>

                <div class="flex-1 min-w-0">
                    <a href="{{ route('promos.show', $p) }}" class="font-bold text-gray-900 hover:text-brand-700">{{ $p->name }}</a>
                    <p class="text-sm text-gray-500 truncate">{{ $p->description ?: $p->type_label }}</p>
                </div>

                <div class="text-sm lg:text-right">
                    <p><span class="text-gray-400">Nilai</span> <span class="font-semibold text-gray-800">{{ $p->type === 'gratis_ongkir' ? 'Gratis ongkir' : $p->value_text }}</span></p>
                    <p class="text-xs text-gray-400">
                        {{ $p->start_date->translatedFormat('d M Y') }} – {{ $p->end_date->translatedFormat('d M Y') }} · {{ $p->used_count }}x terpakai
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('promos.show', $p) }}" class="border bg-white rounded-xl px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Laporan Hasil</a>

                    <form method="POST" action="{{ route('promos.duplicate', $p) }}">
                        @csrf
                        <button class="bg-brand-50 text-brand-700 rounded-xl px-3.5 py-2 text-sm font-medium hover:bg-brand-100">Duplikasi</button>
                    </form>

                    @if(! $p->is_active)
                        <form method="POST" action="{{ route('promos.toggle', $p) }}">
                            @csrf @method('PATCH')
                            <button class="text-success-600 rounded-xl px-3 py-2 text-sm font-medium hover:bg-success-50">Aktifkan</button>
                        </form>
                    @endif

                    @if($p->used_count === 0)
                        <form method="POST" action="{{ route('promos.destroy', $p) }}" onsubmit="return confirm('Hapus promo ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-gray-400 hover:text-danger-600 px-2 py-2">Hapus</button>
                        </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <div class="mt-2"><x-pagination :paginator="$promos" /></div>
    @endif
</x-layouts.app-layout>