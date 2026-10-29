<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Pelanggan</span></x-slot>

    @php
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $hasFilter = request()->filled('q') || $status !== 'semua' || $sort !== 'terbaru';
        $discount = rtrim(rtrim(number_format((float) setting('member_discount_percent', 0), 1, ',', '.'), '0'), ',');
        $minTrx = (int) setting('member_min_transactions', 5);
        $minSpent = (float) setting('member_min_spent', 200000);
    @endphp

    {{-- Saat dicetak, menu dan tombol disembunyikan --}}
    <style>
        @media print {
            aside, header, nav, .no-print { display: none !important; }
            main { padding: 0 !important; }
            body { background: #fff !important; }
        }
    </style>

    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Daftar Pelanggan</h1>
            <p class="text-gray-500">Riwayat cucian, alamat, dan status keanggotaan pelanggan.</p>
        </div>
        <div class="flex flex-wrap gap-2 no-print">
            @role('pemilik')
                <a href="{{ route('customers.export', request()->query()) }}"
                   class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Ekspor ke Excel</a>
            @endrole
            <button type="button" onclick="window.print()"
                    class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cetak</button>
            <a href="{{ route('customers.create') }}"
               class="bg-brand-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-brand-700">+ Tambah Pelanggan Baru</a>
        </div>
    </div>

    {{-- Kartu angka --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Total pelanggan terdaftar</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['total'], 0, ',', '.') }} <span class="text-base font-semibold text-gray-500">pelanggan</span></p>
            <p class="text-sm text-brand-700 mt-1">{{ $stats['new_month'] }} baru bulan ini · {{ $stats['online'] }} punya akun online</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Member aktif (diskon {{ $discount }}%)</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['members'], 0, ',', '.') }} <span class="text-base font-semibold text-gray-500">member</span></p>
            <p class="text-sm text-brand-700 mt-1">{{ $stats['member_share'] }}% dari seluruh pelanggan</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Calon rekomendasi member</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($stats['eligible'], 0, ',', '.') }} <span class="text-base font-semibold text-gray-500">pelanggan</span></p>
            <p class="text-sm text-brand-700 mt-1">Sudah {{ $minTrx }}x transaksi atau belanja {{ $rp($minSpent) }}</p>
        </div>
    </div>

    {{-- Pencarian dan filter --}}
    <form method="GET" action="{{ route('customers.index') }}"
          class="bg-white rounded-2xl border border-gray-100 p-3 mb-4 grid grid-cols-1 md:grid-cols-[1fr_200px_230px] gap-3 no-print">
        <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </span>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, nomor HP, atau alamat/dusun..."
                   class="w-full pl-11 border-gray-200 rounded-xl text-sm bg-gray-50">
        </div>

        <select name="status" onchange="this.form.submit()" class="border-gray-200 rounded-xl text-sm bg-gray-50">
            @foreach(['semua' => 'Semua status', 'member' => 'Member', 'rekomendasi' => 'Layak jadi member', 'reguler' => 'Reguler', 'akses' => 'Punya akun online'] as $key => $label)
                <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="sort" onchange="this.form.submit()" class="border-gray-200 rounded-xl text-sm bg-gray-50">
            @foreach(['terbaru' => 'Terbaru bergabung', 'belanja' => 'Total belanja terbanyak', 'transaksi' => 'Transaksi terbanyak', 'nama' => 'Nama A sampai Z'] as $key => $label)
                <option value="{{ $key }}" {{ $sort === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        @if($hasFilter)
            <div class="md:col-span-3">
                <a href="{{ route('customers.index') }}" class="text-sm text-gray-500">Hapus filter</a>
            </div>
        @endif
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        @if($customers->isEmpty())
            <x-empty-state
                :title="$hasFilter ? 'Tidak ada pelanggan yang cocok' : 'Belum ada pelanggan'"
                :description="$hasFilter ? 'Coba ubah atau hapus filter.' : 'Pelanggan baru bisa ditambahkan langsung dari halaman Transaksi Baru.'" />
        @else
            {{-- Kartu untuk HP --}}
            <div class="md:hidden print:hidden divide-y">
                @foreach($customers as $customer)
                    @php $eligible = ! $customer->is_member && $customer->is_eligible_for_member; @endphp
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('customers.show', $customer) }}" class="flex items-center gap-3 min-w-0">
                                <span class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm flex-shrink-0
                                    {{ $customer->is_member ? 'bg-gold-300 text-gray-900' : 'bg-brand-100 text-brand-700' }}">
                                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900 truncate">{{ $customer->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $customer->phone ?? 'Tanpa nomor HP' }}</span>
                                </span>
                            </a>
                            @if($customer->is_member)
                                <span class="text-[11px] font-semibold bg-gold-300 text-gray-900 rounded-md px-2 py-1 whitespace-nowrap">Member</span>
                            @elseif($eligible)
                                <span class="text-[11px] font-semibold bg-gold-100 text-gold-600 rounded-md px-2 py-1 whitespace-nowrap">Rekomendasi</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ $customer->address ?? 'Alamat belum diisi' }}</p>
                        <div class="flex items-center justify-between mt-2 text-sm">
                            <span class="text-gray-500">{{ $customer->total_transactions }} nota</span>
                            <span class="font-semibold">{{ $rp($customer->total_spent) }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-3">
                            <a href="{{ route('transactions.create', ['customer' => $customer->id]) }}"
                               class="bg-brand-50 text-brand-700 rounded-lg px-3 py-1.5 text-xs font-semibold">Buat Transaksi</a>
                            <a href="{{ route('customers.edit', $customer) }}" class="text-brand-600 text-xs">Edit</a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Tabel untuk layar lebar dan cetak --}}
            <div class="hidden md:block print:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-brand-50 text-gray-600 text-left">
                        <tr>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Pelanggan</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Alamat</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Frekuensi</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase text-right">Total belanja</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Status</th>
                            <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase no-print">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                        @php $eligible = ! $customer->is_member && $customer->is_eligible_for_member; @endphp
                        <tr class="border-t border-gray-100 hover:bg-gray-50 align-top">
                            <td class="px-4 py-3">
                                <a href="{{ route('customers.show', $customer) }}" class="flex items-center gap-3">
                                    <span class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm flex-shrink-0
                                        {{ $customer->is_member ? 'bg-gold-300 text-gray-900' : 'bg-brand-100 text-brand-700' }}">
                                        {{ strtoupper(substr($customer->name, 0, 1)) }}
                                    </span>
                                    <span>
                                        <span class="block font-semibold text-gray-900">{{ $customer->name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $customer->phone ?? 'Tanpa nomor HP' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600 max-w-xs">
                                @if($customer->address)
                                    <span class="line-clamp-2">{{ $customer->address }}</span>
                                @else
                                    <span class="text-gray-400">Alamat belum diisi</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-800">{{ $customer->total_transactions }} nota</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums whitespace-nowrap">{{ $rp($customer->total_spent) }}</td>
                            <td class="px-4 py-3">
                                @if($customer->is_member)
                                    <span class="inline-block text-xs font-semibold bg-gold-300 text-gray-900 rounded-md px-2.5 py-1 whitespace-nowrap">Member</span>
                                @elseif($eligible)
                                    <span class="inline-block text-xs font-semibold bg-gold-100 text-gold-600 rounded-md px-2.5 py-1 whitespace-nowrap">Rekomendasi Member</span>
                                @else
                                    <span class="inline-block text-xs bg-gray-100 text-gray-600 rounded-md px-2.5 py-1">Reguler</span>
                                @endif
                                @if($customer->has_online_access)
                                    <p class="text-[11px] text-success-600 mt-1">&#9679; Akun online</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 no-print">
                                <a href="{{ route('transactions.create', ['customer' => $customer->id]) }}"
                                   class="inline-block bg-brand-50 text-brand-700 rounded-lg px-3 py-1.5 text-xs font-semibold hover:bg-brand-100 whitespace-nowrap">Buat Transaksi</a>
                                <div class="flex items-center gap-3 mt-1.5 text-xs">
                                    <a href="{{ route('customers.edit', $customer) }}" class="text-brand-600">Edit</a>
                                    @role('pemilik')
                                        @if(! $customer->is_member)
                                            <form method="POST" action="{{ route('customers.promoteMember', $customer) }}"
                                                  onsubmit="return confirm(@js('Jadikan ' . $customer->name . ' sebagai member?'))">
                                                @csrf @method('PATCH')
                                                <button class="{{ $eligible ? 'text-gold-600 font-semibold' : 'text-gray-400' }}">Jadikan Member</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                                              onsubmit="return confirm('Hapus pelanggan ini?')">
                                            @csrf @method('DELETE')
                                            <button class="text-danger-600">Hapus</button>
                                        </form>
                                    @endrole
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 no-print">
                <x-pagination :paginator="$customers" />
            </div>
        @endif
    </div>
</x-layouts.app-layout>