<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Pengeluaran</span></x-slot>

    @php
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $periodTabs = ['today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini', 'year' => 'Tahun Ini'];
        $hasFilter = $category || $q !== '' || $period !== 'month';
        $barColors = ['bg-brand-600', 'bg-gold-400', 'bg-brand-800'];
        $over = $budgetPercent !== null && $budgetPercent > 100;
        $warn = $budgetPercent !== null && $budgetPercent >= 90;
    @endphp

    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-5 flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Pencatatan beban operasional</p>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">Pengeluaran &amp; Belanja Toko</h1>
            <p class="text-gray-500 mt-1 max-w-2xl">Catat belanja bahan, listrik, gas, bensin kurir, dan kebutuhan harian outlet.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('expenses.export', request()->query()) }}" class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Export Laporan Biaya</a>
            <a href="{{ route('expenses.create') }}" class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-semibold hover:bg-brand-700">+ Catat Pengeluaran Baru</a>
        </div>
    </div>

    {{-- Kartu angka --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Total pengeluaran</p>
            <p class="text-3xl font-bold text-gray-900 mt-1 tabular-nums">{{ $rp($total) }}</p>
            <p class="text-sm text-brand-700 mt-2">Rata-rata {{ $rp($dailyAvg) }} / hari · {{ $count }} catatan</p>
        </div>

        @forelse($byCategory as $i => $c)
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <p class="text-sm text-gray-500 truncate">{{ $c['label'] }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $rp($c['total']) }}</p>
                <div class="flex justify-between text-xs text-gray-500 mt-3"><span>Porsi pengeluaran</span><span class="font-semibold text-gray-700">{{ $c['share'] }}%</span></div>
                <div class="h-2 bg-gray-100 rounded-full mt-1.5"><div class="h-2 rounded-full {{ $barColors[$i] }}" style="width: {{ $c['share'] }}%"></div></div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-gray-100 p-5 md:col-span-2 xl:col-span-3 text-sm text-gray-400">Belum ada pengeluaran pada filter ini.</div>
        @endforelse
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start">

        {{-- Kiri: filter dan daftar --}}
        <div>
            <div x-data="{ custom: {{ $period === 'custom' ? 'true' : 'false' }} }" class="bg-white rounded-2xl border border-gray-100 p-3 mb-4">
                <form method="GET" action="{{ route('expenses.index') }}" class="grid grid-cols-1 md:grid-cols-[1fr_200px_auto] gap-3">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari keterangan..." class="border-gray-200 rounded-xl text-sm bg-gray-50">
                    <select name="category" onchange="this.form.submit()" class="border-gray-200 rounded-xl text-sm bg-gray-50">
                        <option value="">Semua kategori</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="period" value="{{ $period }}">
                    @if($period === 'custom')
                        <input type="hidden" name="start" value="{{ $startDate }}"><input type="hidden" name="end" value="{{ $endDate }}">
                    @endif
                    <div class="flex items-center gap-3">
                        <button class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Cari</button>
                        @if($hasFilter)<a href="{{ route('expenses.index') }}" class="text-sm text-gray-500">Hapus filter</a>@endif
                    </div>
                </form>

                <div class="flex flex-wrap items-center gap-1 mt-3 text-sm">
                    @foreach($periodTabs as $key => $label)
                        <a href="{{ route('expenses.index', array_filter(['period' => $key, 'category' => $category, 'q' => $q])) }}"
                           class="px-3.5 py-1.5 rounded-lg {{ $period === $key ? 'bg-brand-600 text-white font-medium' : 'text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
                    @endforeach
                    <button type="button" @click="custom = !custom" class="px-3.5 py-1.5 rounded-lg {{ $period === 'custom' ? 'bg-brand-600 text-white font-medium' : 'text-gray-600 hover:bg-gray-50' }}">Custom</button>
                    <span class="ml-auto text-xs text-gray-500 bg-brand-50 rounded-lg px-2.5 py-1.5">{{ $rangeText }}</span>
                </div>

                <form x-show="custom" x-cloak method="GET" action="{{ route('expenses.index') }}" class="flex flex-wrap items-center gap-2 mt-3">
                    <input type="hidden" name="period" value="custom">
                    @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                    <input type="date" name="start" value="{{ $startDate }}" required class="border-gray-200 rounded-xl text-sm">
                    <span class="text-gray-400 text-sm">s/d</span>
                    <input type="date" name="end" value="{{ $endDate }}" required class="border-gray-200 rounded-xl text-sm">
                    <button class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Terapkan</button>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">Riwayat catatan ({{ $expenses->total() }})</h2>
                </div>

                @if($expenses->isEmpty())
                    <x-empty-state :title="$hasFilter ? 'Tidak ada pengeluaran yang cocok' : 'Belum ada pengeluaran'" description="Catat belanja bahan, listrik, gas, dan lainnya lewat tombol di atas." />
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($expenses as $e)
                            <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-3 hover:bg-gray-50">
                                <div class="sm:w-28 flex-shrink-0">
                                    <p class="font-semibold text-gray-900 text-sm">{{ $e->date->translatedFormat('d M Y') }}</p>
                                    <p class="text-[11px] font-mono text-gray-400">EXP-{{ $e->date->format('Ym') }}-{{ str_pad((string) $e->id, 3, '0', STR_PAD_LEFT) }}</p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="inline-block text-xs font-medium bg-brand-50 text-brand-700 rounded-md px-2 py-0.5">{{ $categories[$e->category] ?? ucwords(str_replace('_', ' ', (string) $e->category)) }}</span>
                                    <p class="text-sm text-gray-800 mt-1">{{ $e->description ?: '-' }}</p>
                                </div>
                                <div class="flex items-center gap-4 sm:justify-end">
                                    <p class="text-lg font-bold text-gray-900 tabular-nums whitespace-nowrap">{{ $rp($e->amount) }}</p>
                                    <div class="flex items-center gap-3 text-xs">
                                        <a href="{{ route('expenses.edit', $e) }}" class="text-brand-600">Edit</a>
                                        <form method="POST" action="{{ route('expenses.destroy', $e) }}" onsubmit="return confirm('Hapus catatan pengeluaran ini?')">
                                            @csrf @method('DELETE')
                                            <button class="text-gray-400 hover:text-danger-600">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="p-4"><x-pagination :paginator="$expenses" /></div>
                @endif
            </div>
        </div>

        {{-- Kanan: plafon --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 xl:sticky xl:top-20" x-data="{ edit: {{ $budget > 0 ? 'false' : 'true' }} }">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Plafon anggaran bulanan</h2>
                <span class="text-xs font-semibold bg-brand-50 text-brand-700 rounded-md px-2 py-1">{{ now()->translatedFormat('M Y') }}</span>
            </div>

            @if($budget > 0)
                <div class="rounded-xl p-4 mt-4 {{ $over ? 'bg-danger-50' : ($warn ? 'bg-gold-50' : 'bg-brand-50') }}">
                    <p class="text-xs text-gray-500">Status anggaran</p>
                    <p class="text-xl font-bold {{ $over ? 'text-danger-600' : ($warn ? 'text-warning-700' : 'text-brand-800') }}">
                        {{ $over ? 'Melebihi plafon' : ($warn ? 'Hampir habis' : 'Terkendali') }} · {{ $budgetPercent }}%
                    </p>
                    <div class="h-2 bg-white rounded-full mt-3"><div class="h-2 rounded-full {{ $over ? 'bg-danger-600' : ($warn ? 'bg-gold-500' : 'bg-brand-600') }}" style="width: {{ min(100, $budgetPercent) }}%"></div></div>
                </div>

                <dl class="text-sm space-y-2 mt-4">
                    <div class="flex justify-between"><dt class="text-gray-500">Realisasi pengeluaran</dt><dd class="font-semibold tabular-nums">{{ $rp($monthSpent) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Batas plafon</dt><dd class="font-semibold tabular-nums">{{ $rp($budget) }}</dd></div>
                    <div class="flex justify-between"><dt class="{{ $over ? 'text-danger-600' : 'text-brand-700' }} font-medium">{{ $over ? 'Kelebihan' : 'Sisa plafon' }}</dt>
                        <dd class="font-bold tabular-nums {{ $over ? 'text-danger-600' : 'text-brand-700' }}">{{ $rp(abs($remaining)) }}</dd></div>
                    @unless($over)
                        <p class="text-xs text-gray-400">Sisa {{ $daysLeft }} hari bulan ini, kira-kira {{ $rp($perDayLeft) }} per hari.</p>
                    @endunless
                </dl>
            @else
                <p class="text-sm text-gray-500 mt-3">Belum ada plafon. Isi batas pengeluaran per bulan untuk memantau kapan pengeluaran mendekati batas.</p>
            @endif

            @role('pemilik')
                <button type="button" x-show="!edit" @click="edit = true" class="mt-4 border bg-white rounded-xl px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 w-full">Ubah plafon</button>
                <form x-show="edit" x-cloak method="POST" action="{{ route('expenses.budget') }}" class="mt-4 space-y-2">
                    @csrf @method('PATCH')
                    <label class="text-sm font-medium text-gray-700">Plafon per bulan (Rp)</label>
                    <input type="number" name="expense_budget" min="0" required value="{{ (int) $budget }}" class="w-full border-gray-200 rounded-xl text-sm">
                    <button class="w-full bg-brand-600 text-white rounded-xl py-2 text-sm font-medium">Simpan plafon</button>
                    <p class="text-xs text-gray-400">Isi 0 untuk mematikan pemantauan plafon.</p>
                </form>
            @endrole
        </div>
    </div>
</x-layouts.app-layout>