<x-layouts.portal-layout title="Beranda" :wide="true">
    @php

        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $discount = rtrim(rtrim(number_format((float) setting('member_discount_percent', 0), 1, ',', '.'), '0'), ',');
        $pickupActive = setting('pickup_enabled') ? $customer->pickupRequests()->active()->latest()->first() : null;
        $steps = ['menunggu', 'diproses', 'dicuci', 'dikeringkan', 'disetrika', 'siap_diambil', 'selesai'];
    @endphp

    <div>
        <p class="text-sm text-gray-500">Halo,</p>
        <h1 class="text-2xl font-bold text-gray-900">{{ Str::before($customer->name, ' ') }}</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

        {{-- ============ KIRI ============ --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Kartu keanggotaan --}}
            @if($customer->is_member)
                <div class="rounded-2xl bg-gold-100 border border-gold-300 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-widest text-warning-700">MEMBER</p>
                            <p class="text-xl font-bold text-gray-900 mt-1">{{ $customer->name }}</p>
                            <p class="text-xs text-gray-600">Pelanggan sejak {{ $customer->created_at->translatedFormat('M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-warning-700">Hemat bulan ini</p>
                            <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $rp($monthSaved) }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <div class="rounded-xl bg-white/70 px-3 py-2 text-sm text-gray-800">
                            <span class="font-semibold">Diskon {{ $discount }}%</span> otomatis di tiap transaksi
                            <span class="block text-xs text-gray-500">tidak berlaku bersama promo</span>
                        </div>
                        <div class="rounded-xl bg-white/70 px-3 py-2 text-sm text-gray-800">
                            <span class="font-semibold">{{ $totalTransactions }} transaksi</span>
                            <span class="block text-xs text-gray-500">total hemat {{ $rp($totalSaved) }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-2xl bg-white border border-gray-100 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold tracking-widest text-gray-400">MENUJU MEMBER</p>
                        <span class="text-xs bg-gray-100 text-gray-600 rounded-md px-2 py-1">Pelanggan reguler</span>
                    </div>

                    @if($eligible)
                        <p class="font-semibold text-gray-900 mt-2">Kamu sudah memenuhi syarat member</p>
                        <p class="text-sm text-gray-500 mt-1">Tanyakan ke kasir untuk dijadikan member dan dapatkan diskon {{ $discount }}% otomatis di setiap transaksi.</p>
                    @else
                        <p class="font-semibold text-gray-900 mt-2">
                            {{ $remainingTrx }} transaksi lagi
                            <span class="font-normal text-gray-500">atau belanja {{ $rp($remainingSpent) }} lagi</span>
                        </p>
                        <div class="h-2 bg-gray-100 rounded-full mt-3"><div class="h-2 bg-gold-500 rounded-full" style="width: {{ $progress }}%"></div></div>
                        <div class="flex justify-between text-xs text-gray-500 mt-2">
                            <span>{{ $totalTransactions }} / {{ $minTrx }} transaksi</span>
                            <span>{{ $progress }}%</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Setelah syarat terpenuhi, pemilik toko yang menjadikan kamu member dengan diskon {{ $discount }}%.</p>
                    @endif
                </div>
            @endif

            {{-- Minta jemput --}}
            @if(setting('pickup_enabled'))
                <a href="{{ route('portal.pickup') }}" class="block rounded-2xl bg-brand-700 text-white p-4 hover:bg-brand-800 transition">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $pickupActive ? 'Permintaan jemput aktif' : 'Minta jemput cucian ke rumah' }}</p>
                            <p class="text-sm text-white/75 mt-0.5">
                                @if($pickupActive)
                                    {{ $pickupActive->preferred_date->translatedFormat('l, d M') }} · {{ $pickupActive->slot_label }} · {{ ucfirst($pickupActive->status) }}
                                @else
                                    Pilih layanan dan jadwal, kasir akan mengonfirmasi.
                                @endif
                            </p>
                        </div>
                        <span class="text-sm font-semibold whitespace-nowrap">{{ $pickupActive ? 'Lihat' : 'Buat' }} →</span>
                    </div>
                </a>
            @endif

            {{-- Cucian berjalan --}}
            <div>
                <h2 class="font-semibold text-gray-900 mb-2">Cucian sedang berjalan</h2>

                @forelse($active as $t)
                    @php
                        $idx = max(0, (int) array_search($t->status, $steps, true));
                        $pct = (int) round((($idx + 1) / count($steps)) * 100);
                    @endphp
                    <a href="{{ route('portal.transactions.show', $t->tracking_token) }}"
                       class="block bg-white rounded-2xl border border-gray-100 p-4 mb-3 hover:shadow-sm transition">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $t->code }}</p>
                                <p class="text-sm text-gray-500">{{ $t->items->pluck('service_name')->join(', ') }}</p>
                            </div>
                            <x-badge :status="$t->status" />
                        </div>

                        <div class="h-1.5 bg-gray-100 rounded-full mt-3"><div class="h-1.5 bg-brand-600 rounded-full" style="width: {{ $pct }}%"></div></div>
                        <div class="flex justify-between text-[11px] text-gray-400 mt-1.5">
                            <span>Diterima</span><span>Proses</span><span>Siap diambil</span><span>Selesai</span>
                        </div>

                        <div class="flex items-center justify-between mt-3 text-sm">
                            <span class="text-gray-400">
                                @if($t->estimated_done_at) Estimasi {{ $t->estimated_done_at->translatedFormat('d M Y') }} @endif
                            </span>
                            @if($t->remaining > 0)
                                <span class="text-warning-700 font-medium">Sisa {{ $rp($t->remaining) }}</span>
                            @endif
                        </div>
                        <p class="text-center text-sm font-medium text-brand-700 bg-brand-50 rounded-xl py-2.5 mt-3">Lacak dan periksa rincian</p>
                    </a>
                @empty
                    <div class="bg-white rounded-2xl border border-gray-100 p-5 text-sm text-gray-400">Tidak ada cucian yang sedang berjalan.</div>
                @endforelse
            </div>

            {{-- Riwayat terakhir --}}
            @if($recent->isNotEmpty())
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="font-semibold text-gray-900">Riwayat cucian terakhir</h2>
                        <a href="{{ route('portal.transactions') }}" class="text-sm text-brand-600">Lihat semua</a>
                    </div>
                    @foreach($recent as $t)
                        <a href="{{ route('portal.transactions.show', $t->tracking_token) }}"
                           class="flex items-center justify-between gap-3 bg-white rounded-2xl border border-gray-100 p-4 mb-3 hover:shadow-sm transition">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $t->items->pluck('service_name')->join(', ') }}</p>
                                <p class="text-xs text-gray-400">{{ $t->created_at->translatedFormat('d M Y') }} · {{ $t->code }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="font-semibold tabular-nums">{{ $rp($t->total) }}</p>
                                <x-badge :status="$t->payment_status" />
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============ KANAN ============ --}}
        <div class="space-y-4">

            @if($promos->isNotEmpty())
                <div>
                    <h2 class="font-semibold text-gray-900 mb-2">Promo untuk kamu</h2>
                    @foreach($promos as $promo)
                        <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-gray-900">{{ $promo['name'] }}</p>
                                @if($promo['member_only'])
                                    <span class="text-xs bg-gold-100 text-warning-700 px-2 py-0.5 rounded-md whitespace-nowrap">Khusus member</span>
                                @endif
                            </div>
                            <p class="text-lg font-bold text-brand-700 mt-1">{{ $promo['value'] }}</p>
                            @if($promo['description'])<p class="text-sm text-gray-500">{{ $promo['description'] }}</p>@endif
                            @if($promo['syarat'])<p class="text-xs text-gray-400 mt-1">{{ $promo['syarat'] }}</p>@endif
                            <p class="text-xs text-gray-400 mt-1">Sampai {{ $promo['until'] }}. Sebutkan ke kasir saat transaksi.</p>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Info toko --}}
            <div class="bg-white rounded-2xl border border-gray-100 p-4 text-sm">
                <p class="font-semibold text-gray-900">{{ setting('business_name', 'Laundry Desa') }}</p>
                @if(setting('business_address'))<p class="text-gray-500 mt-1">{{ setting('business_address') }}</p>@endif
                @if($hours = operating_hours_text())<p class="text-gray-500 mt-1">Buka: {{ $hours }}</p>@endif
                @if(setting('business_whatsapp'))
                    <a href="https://wa.me/{{ setting('business_whatsapp') }}" target="_blank"
                       class="block text-center border border-success-200 text-success-600 rounded-xl py-2.5 mt-3 font-medium">Hubungi lewat WhatsApp</a>
                @endif
            </div>
        </div>
    </div>
</x-layouts.portal-layout>