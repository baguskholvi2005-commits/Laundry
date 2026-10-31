<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Buku Kas</span></x-slot>

    @php $rp = fn ($n) => 'Rp ' . number_format(abs($n), 0, ',', '.'); @endphp

    <a href="{{ route('reports.index', request()->query()) }}" class="text-sm text-gray-400 hover:text-gray-600">← Laporan</a>

    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 mt-2 mb-5">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Buku Kas</h1>
            <p class="text-gray-500">{{ $periodLabel }} · {{ $rangeText }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.pdf', request()->query()) }}" class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Download PDF</a>
            <a href="{{ route('reports.export', request()->query()) }}" class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-semibold hover:bg-brand-700">Export CSV / Excel</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        @include('reports._ledger', ['entries' => $entries])

        <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="text-gray-500">{{ $ledgerCount }} mutasi pada periode ini</span>
            <span class="flex gap-4 font-semibold tabular-nums">
                <span class="text-success-600">Total masuk: +{{ $rp($totalRevenue) }}</span>
                <span class="text-danger-600">Total keluar: -{{ $rp($totalExpense) }}</span>
            </span>
        </div>

        <div class="p-4"><x-pagination :paginator="$page" /></div>
    </div>
</x-layouts.app-layout>