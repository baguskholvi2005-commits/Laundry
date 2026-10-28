<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Dashboard</span></x-slot>

    @php
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $kg = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
        $processLabels = ['diproses' => 'Diproses', 'dicuci' => 'Dicuci', 'dikeringkan' => 'Dikeringkan', 'disetrika' => 'Disetrika'];
        $barColors = ['bg-brand-600', 'bg-brand-800', 'bg-gold-400', 'bg-gold-500'];
    @endphp

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-500">Ringkasan bisnis, {{ now()->translatedFormat('l, d F Y') }}</p>
    </div>

    {{-- Baris angka utama --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">

        <div class="sm:col-span-2 bg-white rounded-2xl border border-gray-100 border-t-4 border-t-gold-400 p-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Pendapatan hari ini</p>
                @if($revenueBadge)
                    <span class="text-xs font-medium rounded-full px-2.5 py-1 {{ $revenueUp ? 'bg-brand-50 text-brand-700' : 'bg-danger-50 text-danger-600' }}">{{ $revenueBadge }}</span>
                @endif
            </div>
            <p class="text-4xl font-bold text-gray-900 mt-3 tabular-nums">{{ $rp($revenueToday) }}</p>
            <p class="text-sm text-gray-500 mt-2">{{ $yesterdayNote ?? 'Belum ada pembanding dari kemarin pada jam yang sama.' }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Est. laba bersih</p>
            <p class="text-2xl font-bold mt-3 tabular-nums {{ $profitToday < 0 ? 'text-danger-600' : 'text-gray-900' }}">{{ $rp($profitToday) }}</p>
            <p class="text-sm text-brand-700 mt-2">{{ $margin !== null ? 'Margin laba ' . number_format($margin, 1, ',', '.') . '%' : 'Belum ada pemasukan' }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5 flex flex-col">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Pengeluaran</p>
            <p class="text-2xl font-bold text-gray-900 mt-3 tabular-nums">{{ $rp($expenseToday) }}</p>
            <p class="text-sm text-danger-600 mt-2 truncate">{{ $lastExpenseLabel ?? 'Belum ada pengeluaran' }}</p>
            <div class="mt-auto pt-3 flex items-center justify-between text-xs">
                <span class="text-gray-500">{{ $expenseCount }} pos pembelian</span>
                <a href="{{ route('expenses.index', ['period' => 'today']) }}" class="text-brand-700 font-medium">Lihat rincian</a>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5 flex flex-col">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Jumlah transaksi</p>
            <p class="text-2xl font-bold text-gray-900 mt-3">{{ $transactionsToday }} <span class="text-base font-semibold text-gray-500">nota</span></p>
            <p class="text-sm text-gray-500 mt-2">Rata-rata {{ $kg($avgKg) }} kg per nota kiloan</p>
            <div class="mt-auto pt-3 text-xs text-gray-500">
                Kiloan {{ $kiloanCount }} · Satuan {{ $satuanCount }} · {{ $kg($totalKgToday) }} kg
            </div>
        </div>
    </div>

    {{-- Antrean --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mt-6">
        <h2 class="font-semibold text-gray-900 mb-4">Status antrean</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <a href="{{ route('transactions.processing') }}" class="block rounded-xl bg-brand-50 p-4 hover:shadow-sm transition">
                <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Sedang diproses</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $inProcess }} <span class="text-base font-semibold text-gray-500">nota</span></p>
                <p class="text-sm text-gray-500">{{ $kg($inProcessKg) }} kg</p>
                @if($inProcess > 0)
                    <p class="text-xs text-gray-500 mt-2">
                        @foreach($processLabels as $key => $label)
                            @if(($processCounts[$key] ?? 0) > 0){{ $label }} {{ $processCounts[$key] }}@if(! $loop->last) · @endif @endif
                        @endforeach
                    </p>
                @endif
            </a>

            <a href="{{ route('transactions.ready') }}" class="block rounded-xl bg-brand-50 p-4 hover:shadow-sm transition">
                <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Siap diambil</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $readyToPickup }} <span class="text-base font-semibold text-gray-500">nota</span></p>
                <p class="text-sm text-gray-500">{{ $kg($readyKg) }} kg</p>
            </a>

            <a href="{{ route('reports.receivables') }}" class="block rounded-xl bg-gold-50 p-4 hover:shadow-sm transition">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold tracking-widest text-warning-700 uppercase">Belum lunas</p>
                    @if($unpaid > 0)<span class="text-[11px] bg-gold-400 text-gray-900 font-semibold rounded-full px-2 py-0.5">Perhatian</span>@endif
                </div>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $unpaid }} <span class="text-base font-semibold text-gray-500">nota</span></p>
                <p class="text-sm text-gray-600">Sisa tagihan {{ $rp($unpaidAmount) }}</p>
            </a>
        </div>
    </div>

    {{-- Grafik dan samping --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

        <div class="lg:col-span-2 space-y-6">

            <div class="bg-white rounded-2xl border border-gray-100 p-5"
                 x-data="{ custom: {{ $period === 'custom' ? 'true' : 'false' }} }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">Tren pendapatan bersih</h2>
                        <p class="text-xs text-gray-500">Pendapatan masuk dikurangi pengeluaran, per hari</p>
                    </div>
                    <div class="flex bg-gray-50 border rounded-xl p-1 text-sm">
                        @foreach(['7d' => '7 Hari', '30d' => '30 Hari', 'month' => 'Bulan Ini'] as $key => $label)
                            <a href="{{ route('dashboard', ['period' => $key]) }}"
                               class="px-3 py-1.5 rounded-lg {{ $period === $key ? 'bg-brand-600 text-white' : 'text-gray-600' }}">{{ $label }}</a>
                        @endforeach
                        <button type="button" @click="custom = !custom"
                                class="px-3 py-1.5 rounded-lg {{ $period === 'custom' ? 'bg-brand-600 text-white' : 'text-gray-600' }}">Custom</button>
                    </div>
                </div>

                <form x-show="custom" x-cloak method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2 mt-3">
                    <input type="hidden" name="period" value="custom">
                    <input type="date" name="start" value="{{ $startDate }}" required class="border-gray-200 rounded-xl text-sm">
                    <span class="text-gray-400 text-sm">s/d</span>
                    <input type="date" name="end" value="{{ $endDate }}" required class="border-gray-200 rounded-xl text-sm">
                    <button class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Terapkan</button>
                </form>

                <div class="flex flex-wrap gap-x-10 gap-y-2 mt-4">
                    <div>
                        <p class="text-xs text-gray-500">Total {{ $periodLabel }}</p>
                        <p class="text-2xl font-bold tabular-nums {{ $periodNet < 0 ? 'text-danger-600' : 'text-brand-700' }}">{{ $rp($periodNet) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Rata-rata per hari</p>
                        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $rp($periodAvg) }}</p>
                    </div>
                </div>
                @if($clamped)
                    <p class="text-xs text-warning-600 mt-2">Rentang dipersingkat ke 92 hari terakhir supaya grafik tetap terbaca.</p>
                @endif

                <div class="mt-4"><canvas id="netChart" height="110"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">Volume transaksi harian</h2>
                        <p class="text-xs text-gray-500">Jumlah nota minggu ini, Senin sampai Minggu</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background:#2F8F86"></span>Reguler</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-brand-600"></span>Puncak</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-gold-400"></span>Hari ini</span>
                    </div>
                </div>
                <div class="mt-4"><canvas id="weekChart" height="100"></canvas></div>
                <div class="flex flex-wrap justify-between gap-2 text-sm mt-3 pt-3 border-t border-gray-100">
                    <span class="text-gray-600">Total pekan ini: <span class="font-semibold text-gray-900">{{ $weekTotal }} nota ({{ $kg($weekKg) }} kg kiloan)</span></span>
                    @if($peakDayName)<span class="text-brand-700 font-medium">Hari tersibuk: {{ $peakDayName }}</span>@endif
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-900">Layanan terlaris</h2>
                    <span class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Bulan ini</span>
                </div>

                @forelse($topServices as $i => $s)
                    <div class="mb-4 last:mb-0">
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="font-medium text-gray-900">{{ $s['name'] }}</span>
                            <span class="font-semibold text-gray-700">{{ $s['share'] }}%</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full mt-1.5">
                            <div class="h-2 rounded-full {{ $barColors[$i] ?? 'bg-brand-600' }}" style="width: {{ min(100, $s['share']) }}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>{{ $rp($s['price']) }}/{{ $s['unit'] }}</span>

                            <span>
                                {{ $s['trx'] }} transaksi
                                @if ($s['unit'] === 'kg')
                                    ({{ $kg($s['qty']) }} kg)
                                @endif
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Belum ada transaksi bulan ini.</p>
                @endforelse

                <a href="{{ route('services.index') }}" class="block text-center rounded-xl bg-brand-50 text-brand-700 text-sm font-medium py-2.5 mt-4 hover:bg-brand-100">
                    Kelola layanan dan tarif
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Aktivitas terkini</h2>

                @forelse($activities as $log)
                    @php $role = $log->user?->roles->first()?->name; @endphp
                    <div class="flex gap-3 py-2.5 border-b border-gray-100 last:border-0">
                        @if($log->user)
                            <img src="{{ $log->user->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                        @else
                            <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-400 text-xs flex items-center justify-center flex-shrink-0">S</div>
                        @endif
                        <div class="min-w-0 text-sm">
                            <p class="text-gray-700">
                                <span class="font-semibold text-gray-900">{{ $role ? ucfirst($role) . ' ' : '' }}{{ $log->user->name ?? 'Sistem' }}</span>
                                {{ $log->description }}
                                @if($log->transaction_id)
                                    <a href="{{ route('transactions.show', $log->transaction_id) }}" class="text-brand-600">Lihat</a>
                                @endif
                            </p>
                            <p class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Belum ada aktivitas.</p>
                @endforelse

                <a href="{{ route('activity-logs.index') }}" class="block text-center rounded-xl bg-brand-50 text-brand-700 text-sm font-medium py-2.5 mt-3 hover:bg-brand-100">
                    Buka seluruh log aktivitas
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const rupiah = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');
        const singkat = (v) => {
            const a = Math.abs(v), s = v < 0 ? '-' : '';
            return a >= 1000000 ? s + (a / 1000000) + ' jt' : (a >= 1000 ? s + (a / 1000) + ' rb' : v);
        };

        const labels = @json($labels);
        const revenue = @json($revenueSeries);
        const expense = @json($expenseSeries);
        const net = @json($netSeries);
        const last = net.length - 1;

        new Chart(document.getElementById('netChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: net,
                    borderColor: '#0F766E',
                    backgroundColor: 'rgba(15,118,110,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: labels.length > 31 ? 0 : 3,
                    pointBackgroundColor: net.map((_, i) => i === last ? '#F2C94C' : '#0F766E'),
                    pointBorderColor: '#0F766E',
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) => 'Bersih: ' + rupiah(c.parsed.y),
                            afterBody: (items) => {
                                const i = items[0].dataIndex;
                                return ['Pendapatan: ' + rupiah(revenue[i]), 'Pengeluaran: ' + rupiah(expense[i])];
                            }
                        }
                    }
                },
                scales: { y: { ticks: { callback: singkat } } }
            }
        });

        new Chart(document.getElementById('weekChart'), {
            type: 'bar',
            data: {
                labels: @json($weekLabels),
                datasets: [{ data: @json($weekSeries), backgroundColor: @json($weekColors), borderRadius: 6 }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => c.parsed.y + ' nota' } }
                },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    });
    </script>
    @endpush
</x-layouts.app-layout>