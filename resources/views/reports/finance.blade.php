<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Laporan</span></x-slot>

    @php
        $rp = fn ($n) => 'Rp ' . number_format(abs($n), 0, ',', '.');
        $periodTabs = ['today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini', 'year' => 'Tahun Ini'];
        $surplus = $netProfit >= 0;
    @endphp

    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-5 flex flex-col lg:flex-row lg:items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Laporan arus kas dan keuangan</p>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">Laporan Arus Kas &amp; Keuangan Usaha</h1>
            <p class="text-gray-500 mt-1 max-w-2xl">Pemasukan kasir, pengeluaran operasional, dan piutang pelanggan per periode.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.pdf', request()->query()) }}"
               class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Download PDF</a>
            <a href="{{ route('reports.export', request()->query()) }}"
               class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-semibold hover:bg-brand-700">Export CSV / Excel</a>
        </div>
    </div>

    {{-- Periode --}}
    <div x-data="{ custom: {{ $period === 'custom' ? 'true' : 'false' }} }" class="bg-white rounded-2xl border border-gray-100 p-3 mb-5">
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex flex-wrap gap-1 text-sm">
                @foreach($periodTabs as $key => $label)
                    <button type="submit" name="period" value="{{ $key }}"
                            class="px-4 py-2 rounded-xl {{ $period === $key ? 'bg-brand-600 text-white font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                        {{ $period === $key ? $periodLabel : $label }}
                    </button>
                @endforeach
                <button type="button" @click="custom = !custom"
                        class="px-4 py-2 rounded-xl {{ $period === 'custom' ? 'bg-brand-600 text-white font-medium' : 'text-gray-600 hover:bg-gray-50' }}">Custom</button>
            </form>
            <span class="lg:ml-auto text-sm text-gray-600 bg-brand-50 rounded-xl px-3 py-2">{{ $rangeText }}</span>
        </div>

        <form x-show="custom" x-cloak method="GET" class="flex flex-wrap items-center gap-2 mt-3">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="start" value="{{ $startDate }}" required class="border-gray-200 rounded-xl text-sm">
            <span class="text-gray-400 text-sm">s/d</span>
            <input type="date" name="end" value="{{ $endDate }}" required class="border-gray-200 rounded-xl text-sm">
            <button class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Terapkan</button>
        </form>
    </div>

    {{-- Empat kartu --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">

        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Total pemasukan</p>
            <p class="text-3xl font-bold text-gray-900 mt-3 tabular-nums">{{ $rp($totalRevenue) }}</p>
            @if($revenueChange !== null)
                <p class="text-sm mt-1.5 {{ $revenueChange >= 0 ? 'text-brand-700' : 'text-danger-600' }}">
                    {{ $revenueChange >= 0 ? '+' : '-' }}{{ number_format(abs($revenueChange), 1, ',', '.') }}% vs {{ $compareLabel }}
                </p>
            @else
                <p class="text-sm text-gray-400 mt-1.5">Belum ada pembanding dari {{ $compareLabel }}</p>
            @endif
            <p class="text-xs text-gray-400 mt-3 pt-3 border-t border-gray-100">Uang yang sudah diterima, tanpa transaksi dibatalkan</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Total beban operasional</p>
            <p class="text-3xl font-bold text-gray-900 mt-3 tabular-nums">{{ $rp($totalExpense) }}</p>
            <p class="text-sm text-gray-500 mt-1.5 truncate">{{ count($topCategoryLabels) ? implode(', ', $topCategoryLabels) : 'Belum ada pengeluaran' }}</p>
            <p class="text-xs text-gray-400 mt-3 pt-3 border-t border-gray-100 flex justify-between">
                <span>Rasio beban terhadap pemasukan</span>
                <span class="font-semibold text-gray-700">{{ $expenseRatio !== null ? number_format($expenseRatio, 1, ',', '.') . '%' : '-' }}</span>
            </p>
        </div>

        <div class="bg-brand-700 text-white rounded-2xl p-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-semibold tracking-widest text-white/70 uppercase">Estimasi laba bersih</p>
                <span class="text-[11px] font-bold rounded-md px-2 py-0.5 {{ $surplus ? 'bg-gold-400 text-gray-900' : 'bg-danger-500 text-white' }}">{{ $surplus ? 'SURPLUS' : 'DEFISIT' }}</span>
            </div>
            <p class="text-3xl font-bold mt-3 tabular-nums">{{ $netProfit < 0 ? '-' : '' }}{{ $rp($netProfit) }}</p>
            <p class="text-sm text-white/80 mt-1.5">{{ $margin !== null ? 'Margin operasional ' . number_format($margin, 1, ',', '.') . '%' : 'Belum ada pemasukan' }}</p>
            <p class="text-xs text-white/60 mt-3 pt-3 border-t border-white/15">Pemasukan dikurangi pengeluaran</p>
        </div>

        <a href="{{ route('reports.receivables') }}" class="block bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-sm transition">
            <p class="text-[11px] font-semibold tracking-widest text-danger-600 uppercase">Piutang / belum lunas</p>
            <p class="text-3xl font-bold text-gray-900 mt-3 tabular-nums">{{ $rp($receivableTotal) }}</p>
            <p class="text-sm text-danger-600 mt-1.5">{{ $receivableCount }} nota menunggu pelunasan</p>
            <p class="text-xs text-gray-400 mt-3 pt-3 border-t border-gray-100 flex justify-between">
                <span>Tagihan terlama</span>
                <span class="font-semibold text-gray-700">{{ $oldestDays !== null ? ($oldestDays === 0 ? 'Hari ini' : $oldestDays . ' hari') : '-' }}</span>
            </p>
        </a>
    </div>

    {{-- Grafik dan distribusi --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-gray-900">Tren keuangan 6 bulan terakhir</h2>
                    <p class="text-xs text-gray-500">Perbandingan pemasukan dan pengeluaran per bulan</p>
                </div>
                <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background:#0F766E"></span>Pemasukan</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background:#A4DBD1"></span>Pengeluaran</span>
                </div>
            </div>
            <div class="mt-4"><canvas id="trendChart" height="110"></canvas></div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-900">Distribusi metode bayar</h2>
            <p class="text-xs text-gray-500 mb-4">Berdasarkan nominal uang yang masuk</p>

            @forelse($distribution as $d)
                <div class="mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-gray-800">{{ $d['label'] }}</span>
                        <span class="font-semibold text-gray-700">{{ $d['percent'] }}%</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full mt-1.5">
                        <div class="h-2 rounded-full" style="width: {{ $d['percent'] }}%; background: {{ $d['color'] }}"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ $rp($d['amount']) }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-400">Belum ada pembayaran pada periode ini.</p>
            @endforelse

            <div class="flex justify-between text-sm pt-3 border-t border-gray-100">
                <span class="text-gray-500">Total akumulasi pembayaran</span>
                <span class="font-bold text-brand-700 tabular-nums">{{ $rp($distTotal) }}</span>
            </div>
        </div>
    </div>

    {{-- Buku kas --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="p-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold text-gray-900">Mutasi buku kas &amp; transaksi terakhir</h2>
                <p class="text-xs text-gray-500">Pembayaran yang masuk dan pengeluaran yang dicatat, dari yang terbaru</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-500">Menampilkan {{ count($ledger) }} dari {{ $ledgerCount }} mutasi</span>
                <a href="{{ route('reports.ledger', request()->query()) }}" class="bg-brand-50 text-brand-700 rounded-lg px-3 py-1.5 font-medium hover:bg-brand-100">Lihat Semua</a>
            </div>
        </div>

        @include('reports._ledger', ['entries' => $ledger])

        <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="text-gray-500">Total pergerakan kas pada periode ini: <span class="font-semibold text-gray-800">{{ $ledgerCount }} mutasi</span></span>
            <span class="flex gap-4 font-semibold tabular-nums">
                <span class="text-success-600">Total masuk: +{{ $rp($totalRevenue) }}</span>
                <span class="text-danger-600">Total keluar: -{{ $rp($totalExpense) }}</span>
            </span>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const rupiah = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');
        const singkat = (v) => v >= 1000000 ? (v / 1000000) + ' jt' : (v >= 1000 ? (v / 1000) + ' rb' : v);

        new Chart(document.getElementById('trendChart'), {
            type: 'bar',
            data: {
                labels: @json($trendLabels),
                datasets: [
                    { label: 'Pemasukan', data: @json($trendRevenue), backgroundColor: '#0F766E', borderRadius: 6 },
                    { label: 'Pengeluaran', data: @json($trendExpense), backgroundColor: '#A4DBD1', borderRadius: 6 },
                ]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + rupiah(c.parsed.y) } }
                },
                scales: { y: { beginAtZero: true, ticks: { callback: singkat } } }
            }
        });
    });
    </script>
    @endpush
</x-layouts.app-layout>