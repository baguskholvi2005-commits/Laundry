<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Inventori</span></x-slot>

    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
        $statusStyle = [
            'habis' => ['Habis', 'bg-danger-600 text-white'],
            'kritis' => ['Kritis', 'bg-danger-50 text-danger-600'],
            'menipis' => ['Menipis', 'bg-gold-100 text-warning-700'],
            'aman' => ['Aman', 'bg-brand-50 text-brand-700'],
        ];
        $hasFilter = request()->filled('q') || request()->filled('category') || $status !== 'semua';
        $names = $needRestock->take(3)->pluck('name')->join(', ');
    @endphp

    <div x-data="{
            modal: false,
            items: @js($allItems),
            itemId: '{{ old('item_id') }}',
            type: '{{ old('type', 'masuk') }}',
            get item() { return this.items.find(i => String(i.id) === String(this.itemId)) ?? null; },
            open(id = '', type = 'masuk') { this.itemId = id === '' ? '' : String(id); this.type = type; this.modal = true; },
         }">

        <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Inventori &amp; Stok Bahan</h1>
                <p class="text-gray-500">Pantau ketersediaan deterjen, pewangi, gas, plastik kemasan, dan bahan harian lainnya.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('inventory.logs') }}" class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Riwayat Stok</a>
                <button type="button" @click="open()" class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-semibold hover:bg-brand-700">+ Catat Stok Masuk / Keluar</button>
                <a href="{{ route('inventory.create') }}" class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">+ Bahan Baru</a>
            </div>
        </div>

        {{-- Kartu angka --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Total item terdata</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['total'] }} <span class="text-base font-semibold text-gray-500">jenis bahan</span></p>
                <p class="text-sm text-brand-700 mt-1">{{ $stats['categories'] }} kategori</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 border-l-4 border-l-gold-400 p-5">
                <p class="text-[11px] font-semibold tracking-widest text-warning-700 uppercase">Peringatan stok menipis</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['restock'] }} <span class="text-base font-semibold text-gray-500">bahan butuh restock</span></p>
                <p class="text-sm text-gray-500 mt-1 truncate">{{ $names !== '' ? $names . ($needRestock->count() > 3 ? ', ...' : '') : 'Semua stok di atas ambang minimal' }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Stok aman dan cukup</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['safe'] }} <span class="text-base font-semibold text-gray-500">bahan</span></p>
                <p class="text-sm text-brand-700 mt-1">Di atas ambang minimal</p>
            </div>
        </div>

        {{-- Pencarian dan filter --}}
        <form method="GET" action="{{ route('inventory.index') }}"
              class="bg-white rounded-2xl border border-gray-100 p-3 mb-4 grid grid-cols-1 md:grid-cols-[1fr_200px_200px_auto] gap-3">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama bahan atau kategori..."
                   class="border-gray-200 rounded-xl text-sm bg-gray-50">

            <select name="category" onchange="this.form.submit()" class="border-gray-200 rounded-xl text-sm bg-gray-50">
                <option value="">Semua kategori ({{ $stats['total'] }})</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>

            <select name="status" onchange="this.form.submit()" class="border-gray-200 rounded-xl text-sm bg-gray-50">
                @foreach(['semua' => 'Semua status', 'perlu_restock' => 'Perlu restock', 'habis' => 'Habis', 'kritis' => 'Kritis', 'menipis' => 'Menipis', 'aman' => 'Aman'] as $key => $label)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-3">
                <button class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Cari</button>
                @if($hasFilter)<a href="{{ route('inventory.index') }}" class="text-sm text-gray-500">Hapus filter</a>@endif
            </div>
        </form>

        {{-- Daftar bahan --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            @if($items->isEmpty())
                <x-empty-state :title="$hasFilter ? 'Tidak ada bahan yang cocok' : 'Belum ada bahan'"
                               :description="$hasFilter ? 'Coba ubah atau hapus filter.' : 'Tambahkan deterjen, pewangi, gas, dan plastik kemasan lewat tombol Bahan Baru.'" />
            @else
                {{-- Kartu untuk HP --}}
                <div class="md:hidden divide-y">
                    @foreach($items as $item)
                        @php
                            [$label, $style] = $statusStyle[$item->stock_status];
                            $days = $forecast[$item->id] ?? null;
                        @endphp
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $item->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $item->category ?: 'Tanpa kategori' }}</p>
                                </div>
                                <span class="text-[11px] font-semibold rounded-md px-2 py-1 whitespace-nowrap {{ $style }}">{{ $label }}</span>
                            </div>
                            <div class="flex items-end justify-between mt-2">
                                <p class="text-2xl font-bold {{ in_array($item->stock_status, ['habis', 'kritis']) ? 'text-danger-600' : 'text-gray-900' }}">
                                    {{ $fmt($item->current_stock) }} <span class="text-sm font-semibold text-gray-500">{{ $item->unit }}</span>
                                </p>
                                <p class="text-xs text-gray-500">Min. {{ $fmt($item->min_stock) }} {{ $item->unit }}
                                    @if($days !== null) · ± {{ (int) floor($days) }} hari lagi @endif</p>
                            </div>
                            <div class="mt-3">@include('inventory._actions', ['item' => $item])</div>
                        </div>
                    @endforeach
                </div>

                {{-- Tabel untuk layar lebar --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-brand-50 text-gray-600 text-left">
                            <tr>
                                <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Bahan</th>
                                <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase text-right">Sisa stok</th>
                                <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase text-right">Ambang minimal</th>
                                <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Status</th>
                                <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Estimasi habis</th>
                                <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                            @php
                                [$label, $style] = $statusStyle[$item->stock_status];
                                $days = $forecast[$item->id] ?? null;
                            @endphp
                            <tr class="border-t border-gray-100 align-middle hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $item->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $item->category ?: 'Tanpa kategori' }}</p>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="text-lg font-bold tabular-nums {{ in_array($item->stock_status, ['habis', 'kritis']) ? 'text-danger-600' : 'text-gray-900' }}">{{ $fmt($item->current_stock) }}</span>
                                    <span class="text-xs text-gray-500">{{ $item->unit }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap tabular-nums">{{ $fmt($item->min_stock) }} {{ $item->unit }}</td>
                                <td class="px-4 py-3"><span class="inline-block text-[11px] font-semibold rounded-md px-2.5 py-1 {{ $style }}">{{ $label }}</span></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($item->current_stock <= 0)
                                        <span class="text-danger-600 font-semibold">Habis</span>
                                    @elseif($days === null)
                                        <span class="text-gray-400" title="Belum ada catatan pemakaian dalam 30 hari terakhir">-</span>
                                    @elseif($days < 1)
                                        <span class="text-danger-600 font-semibold">Kurang dari 1 hari</span>
                                    @else
                                        <span class="{{ $days <= 3 ? 'text-danger-600 font-semibold' : ($days <= 7 ? 'text-warning-600' : 'text-gray-700') }}">
                                            {{ $days >= 60 ? '60+ hari' : '± ' . (int) floor($days) . ' hari lagi' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">@include('inventory._actions', ['item' => $item])</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4"><x-pagination :paginator="$items" /></div>
            @endif
        </div>

        <p class="text-xs text-gray-400 mt-2">Estimasi habis dihitung dari rata-rata pemakaian 30 hari terakhir. Makin rutin kamu mencatat pemakaian, makin akurat.</p>

                {{-- Daftar belanja --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 mt-5" x-data="{ copied: false }">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-gray-900">Daftar belanja</h2>
                    <p class="text-xs text-gray-500">Bahan yang sudah di bawah ambang minimal. Jumlah disarankan sampai stok mencapai 2 kali ambang minimal.</p>
                </div>
                @if($shopping->isNotEmpty())
                    <span class="text-xs font-semibold bg-gold-100 text-warning-700 rounded-md px-2.5 py-1">{{ $shopping->count() }} bahan perlu restock</span>
                @endif
            </div>

            @if($shopping->isEmpty())
                <p class="text-sm text-gray-400 mt-4">Semua stok di atas ambang minimal. Belum ada yang perlu dibeli.</p>
            @else
                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach($shopping as $s)
                        <li class="flex justify-between gap-3 py-2">
                            <span class="text-gray-800">{{ $s['name'] }}</span>
                            <span class="font-semibold text-gray-900 whitespace-nowrap">{{ $s['qty'] }} {{ $s['unit'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <textarea x-ref="list" class="sr-only" readonly>{{ $shoppingText }}</textarea>

                <div class="flex flex-wrap gap-2 mt-4">
                    <button type="button"
                            @click="navigator.clipboard?.writeText($refs.list.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})"
                            class="border bg-white rounded-xl px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <span x-show="!copied">Salin daftar</span><span x-show="copied" x-cloak>Tersalin</span>
                    </button>
                    <a href="https://wa.me/?text={{ urlencode($shoppingText) }}" target="_blank"
                       class="bg-brand-600 text-white rounded-xl px-4 py-2 text-sm font-semibold hover:bg-brand-700">Kirim lewat WhatsApp</a>
                </div>
                <p class="text-xs text-gray-400 mt-2">WhatsApp akan meminta kamu memilih kontak pemasoknya.</p>
            @endif
        </div>

        {{-- Jendela catat stok --}}
        <div x-show="modal" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
             @keydown.escape.window="modal = false">
            <div @click.outside="modal = false" class="bg-white rounded-2xl w-full max-w-md p-6">
                <h3 class="font-semibold text-lg mb-4">Catat stok masuk / keluar</h3>

                <form method="POST" action="{{ route('inventory.move') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="text-sm font-medium text-gray-700">Bahan</label>
                        <select name="item_id" x-model="itemId" required class="w-full border-gray-200 rounded-xl mt-1">
                            <option value="">Pilih bahan</option>
                            <template x-for="i in items" :key="i.id">
                                <option :value="i.id" x-text="i.name + ' (stok ' + i.current_stock + ' ' + i.unit + ')'"></option>
                            </template>
                        </select>
                        @error('item_id') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="masuk" x-model="type" class="sr-only peer">
                            <span class="block text-center rounded-xl border px-3 py-2.5 text-sm peer-checked:bg-brand-50 peer-checked:border-brand-500 peer-checked:text-brand-700 peer-checked:font-semibold">Stok masuk (restock)</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="keluar" x-model="type" class="sr-only peer">
                            <span class="block text-center rounded-xl border px-3 py-2.5 text-sm peer-checked:bg-gold-100 peer-checked:border-gold-500 peer-checked:text-warning-700 peer-checked:font-semibold">Stok keluar (dipakai)</span>
                        </label>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Jumlah <span x-show="item" x-text="'(' + item?.unit + ')'" class="text-gray-400"></span></label>
                        <input type="number" name="qty" step="0.01" min="0.01" required value="{{ old('qty') }}" class="w-full border-gray-200 rounded-xl mt-1">
                        @error('qty') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Catatan (opsional)</label>
                        <input type="text" name="notes" maxlength="255" value="{{ old('notes') }}"
                               placeholder="Contoh: beli di Toko Kimia, atau dipakai shift pagi"
                               class="w-full border-gray-200 rounded-xl mt-1">
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="button" @click="modal = false" class="flex-1 border rounded-xl py-2.5 text-sm">Batal</button>
                        <button class="flex-1 bg-brand-600 text-white rounded-xl py-2.5 text-sm font-semibold">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app-layout>