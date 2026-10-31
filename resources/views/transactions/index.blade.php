<x-layouts.app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold">Transaksi</h1></x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Semua Transaksi</h1>
            <p class="text-gray-500">Cari, pantau status, dan kelola pembayaran laundry.</p>
        </div>
        <a href="{{ route('transactions.create') }}"
           class="bg-brand-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-brand-700">
            + Transaksi Baru
        </a>
    </div>

    {{-- Tab status --}}
    <div class="flex flex-wrap bg-white border rounded-xl p-1 text-sm w-fit mb-4">
        <a href="{{ route('transactions.index') }}"
           class="px-4 py-1.5 rounded-lg {{ request()->routeIs('transactions.index') ? 'bg-brand-600 text-white' : '' }}">Semua</a>
        <a href="{{ route('transactions.processing') }}"
           class="px-4 py-1.5 rounded-lg {{ request()->routeIs('transactions.processing') ? 'bg-brand-600 text-white' : '' }}">Sedang Diproses</a>
        <a href="{{ route('transactions.ready') }}"
           class="px-4 py-1.5 rounded-lg {{ request()->routeIs('transactions.ready') ? 'bg-brand-600 text-white' : '' }}">Siap Diambil</a>
        <a href="{{ route('transactions.history') }}"
           class="px-4 py-1.5 rounded-lg {{ request()->routeIs('transactions.history') ? 'bg-brand-600 text-white' : '' }}">Riwayat</a>
    </div>

{{-- Pencarian dan filter --}}
    @php
        $hasFilter = request()->filled('q')
            || request()->filled('date_from')
            || request()->filled('date_to')
            || request()->filled('payment');
    @endphp

    <form method="GET" action="{{ url()->current() }}"
        class="bg-white rounded-2xl border border-gray-100 p-4 mb-4">

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">

            {{-- Pencarian --}}
            <div class="md:col-span-4">
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Kode, nama, atau No. HP..."
                    class="w-full border border-gray-200 rounded-xl text-sm"
                >
            </div>

            {{-- Tanggal awal --}}
            <div class="md:col-span-2">
                <input
                    type="date"
                    name="date_from"
                    value="{{ request('date_from') }}"
                    title="Dari tanggal"
                    class="w-full border border-gray-200 rounded-xl text-sm"
                >
            </div>

            {{-- Tanggal akhir --}}
            <div class="md:col-span-2">
                <input
                    type="date"
                    name="date_to"
                    value="{{ request('date_to') }}"
                    title="Sampai tanggal"
                    class="w-full border border-gray-200 rounded-xl text-sm"
                >
            </div>

            {{-- Metode pembayaran --}}
            <div class="md:col-span-2">
                <select
                    name="payment"
                    class="w-full border border-gray-200 rounded-xl text-sm"
                >
                    <option value="">Semua metode bayar</option>

                    @foreach(payment_method_labels() as $key => $label)
                        <option
                            value="{{ $key }}"
                            {{ request('payment') === $key ? 'selected' : '' }}
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Tombol cari --}}
            <div class="md:col-span-2 flex items-center gap-2">
                <button
                    type="submit"
                    class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition whitespace-nowrap"
                >
                    Cari
                </button>

                @if($hasFilter)
                    <a
                        href="{{ url()->current() }}"
                        class="text-sm text-gray-500 hover:text-gray-700 whitespace-nowrap"
                    >
                       <button type="button" class="px-3 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 transition">Reset</button>
                    </a>
                @endif
            </div>

        </div>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        @if($transactions->isEmpty())
            <x-empty-state :title="$hasFilter ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi'"
                           :description="$hasFilter ? 'Coba ubah atau hapus filter.' : 'Transaksi yang kamu buat akan muncul di sini.'" />
        @else
            {{-- Kartu untuk HP --}}
            <div class="md:hidden divide-y">
                @foreach($transactions as $t)
                    <a href="{{ route('transactions.show', $t) }}" class="block p-4 hover:bg-gray-50">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-brand-600">{{ $t->code }}</span>
                            <x-badge :status="$t->status" />
                        </div>
                        <p class="text-sm mt-1 flex items-center gap-1.5">
                            @if($t->customer?->is_member)<span class="text-amber-600">⭐</span>@endif
                            {{ $t->customer->name ?? 'Pelanggan Umum' }}
                        </p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-gray-400">{{ $t->created_at->translatedFormat('d M Y, H:i') }}</span>
                            <span class="font-semibold">Rp {{ number_format($t->total, 0, ',', '.') }}</span>
                        </div>
                        <div class="mt-1.5"><x-badge :status="$t->payment_status" /></div>
                    </a>
                @endforeach
            </div>

            {{-- Tabel untuk layar lebar --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Kode</th>
                            <th class="px-4 py-3 font-medium">Pelanggan</th>
                            <th class="px-4 py-3 font-medium">Tanggal</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                            <th class="px-4 py-3 font-medium">Pembayaran</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $t)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('transactions.show', $t) }}" class="font-medium text-brand-600">{{ $t->code }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if($t->customer)
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold
                                            {{ $t->customer->is_member ? 'bg-amber-100 text-amber-700' : 'bg-brand-50 text-brand-600' }}">
                                            {{ strtoupper(substr($t->customer->name, 0, 1)) }}
                                        </span>
                                        <span>{{ $t->customer->name }}</span>
                                    @else
                                        <span class="text-gray-400">Pelanggan Umum</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $t->created_at->translatedFormat('d M Y, H:i') }}</td>
                            <td class="px-4 py-3 font-medium">Rp {{ number_format($t->total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3"><x-badge :status="$t->payment_status" /></td>
                            <td class="px-4 py-3"><x-badge :status="$t->status" /></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('transactions.show', $t) }}" class="text-brand-600 text-xs">Detail</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                <x-pagination :paginator="$transactions" />
            </div>
        @endif
    </div>
</x-layouts.app-layout>