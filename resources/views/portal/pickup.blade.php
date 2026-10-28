<x-layouts.portal-layout title="Minta Jemput">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Minta Jemput</h1>
        <p class="text-sm text-gray-500">Pilih layanan dan jadwalnya. Kasir akan mengonfirmasi permintaanmu.</p>
    </div>

    @php
        $pickupFee = (float) setting('pickup_fee', 0);
        $deliveryFee = (float) setting('delivery_fee', 0);
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    @endphp

    @if(! $enabled)
        <div class="bg-white rounded-2xl border border-gray-100 p-5 text-sm text-gray-500">
            Layanan jemput sedang tidak tersedia. Hubungi toko kalau perlu bantuan.
        </div>

    @elseif($active)
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-xs text-gray-400">Permintaan aktif</p>
                    <p class="font-semibold text-gray-900">{{ $active->preferred_date->translatedFormat('l, d F Y') }} · {{ $active->slot_label }}</p>
                    <span class="inline-block mt-1.5 text-xs px-2.5 py-1 rounded-full {{ $active->wants_delivery ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $active->mode_label }}
                    </span>
                </div>
                <x-badge :status="$active->status" />
            </div>

            @if($active->items->isNotEmpty())
                <div class="mt-3 rounded-xl bg-gray-50 p-3 text-sm">
                    <p class="text-xs text-gray-400 mb-1">Layanan yang kamu minta</p>
                    @foreach($active->items as $item)
                        <div class="flex justify-between gap-3 py-0.5">
                            <span class="text-gray-800">{{ $item->service_name }}</span>
                            <span class="text-gray-500 whitespace-nowrap">{{ $item->qty_label }}</span>
                        </div>
                    @endforeach
                    <p class="text-xs text-gray-400 mt-2">Berat dan harga akhir mengikuti timbangan di toko.</p>
                </div>
            @endif

            <p class="text-sm text-gray-600 mt-3 whitespace-pre-line">{{ $active->address }}</p>
            @if($active->notes)
                <p class="text-xs text-gray-400 mt-2">Catatanmu: {{ $active->notes }}</p>
            @endif
            @if($active->staff_note)
                <p class="text-sm bg-brand-50 text-brand-700 rounded-xl px-3 py-2 mt-3">Dari kasir: {{ $active->staff_note }}</p>
            @endif

            <p class="text-xs text-gray-400 mt-3">
                @if($active->status === 'menunggu')
                    Menunggu konfirmasi kasir. Biasanya dibalas pada jam buka toko.
                @else
                    Sudah dikonfirmasi. Siapkan cucianmu di alamat di atas.
                @endif
            </p>

            <form method="POST" action="{{ route('portal.pickup.cancel', $active->id) }}" class="mt-4"
                  onsubmit="return confirm('Batalkan permintaan ini?')">
                @csrf @method('DELETE')
                <button class="w-full border border-danger-200 text-danger-600 rounded-xl py-2.5 text-sm font-medium">Batalkan Permintaan</button>
            </form>
        </div>

    @elseif($services->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 p-5 text-sm text-gray-500">
            Daftar layanan belum tersedia. Hubungi toko untuk mengatur jemput.
        </div>

    @elseif($dateOptions->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 p-5 text-sm text-gray-500">
            Tidak ada tanggal yang tersedia dalam 7 hari ke depan. Hubungi toko untuk mengatur jemput.
        </div>

    @else
        <form method="POST" action="{{ route('portal.pickup.store') }}" class="bg-white rounded-2xl border border-gray-100 p-5 space-y-6">
            @csrf

            {{-- 1. Jenis layanan --}}
            <div>
                <p class="text-sm font-medium text-gray-700">Mau diapakan cuciannya?</p>
                <div class="grid grid-cols-1 gap-2 mt-2">
                    @foreach(\App\Models\PickupRequest::MODES as $key => [$label, $hint])
                        @continue($key === 'jemput_antar' && ! $deliveryEnabled)
                        <label class="cursor-pointer">
                            <input type="radio" name="service_mode" value="{{ $key }}" class="sr-only peer"
                                   {{ old('service_mode', 'jemput') === $key ? 'checked' : '' }}>
                            <span class="block rounded-xl border p-3 peer-checked:bg-brand-50 peer-checked:border-brand-500">
                                <span class="block text-sm font-semibold text-gray-900">{{ $label }}</span>
                                <span class="block text-xs text-gray-500 mt-0.5">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('service_mode') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- 2. Layanan cucian --}}
            <div>
                <p class="text-sm font-medium text-gray-700">Layanan yang diinginkan</p>
                <p class="text-xs text-gray-400">Pilih satu atau lebih. Berat dan harga akhir mengikuti timbangan di toko.</p>
                @error('service_ids') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror

                @foreach(['kiloan' => 'Per kilo', 'satuan' => 'Per potong'] as $type => $groupLabel)
                    @php $group = $services->where('type', $type); @endphp

                    @if($group->isNotEmpty())
                        <p class="text-[11px] font-semibold tracking-wide text-gray-400 uppercase mt-4 mb-2">{{ $groupLabel }}</p>
                        <div class="space-y-2">
                            @foreach($group as $s)
                                @php $isOn = in_array((string) $s->id, array_map('strval', (array) old('service_ids', [])), true); @endphp
                                <div x-data="{ on: {{ $isOn ? 'true' : 'false' }} }" class="rounded-xl border p-3 transition"
                                     :class="on ? 'border-brand-500 bg-brand-50' : ''">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="service_ids[]" value="{{ $s->id }}" x-model="on"
                                               class="rounded border-gray-300 text-brand-600">
                                        <span class="flex-1">
                                            <span class="block text-sm font-medium text-gray-900">{{ $s->name }}</span>
                                            <span class="block text-xs text-gray-500">
                                                {{ $rp($s->price) }}/{{ $s->type === 'kiloan' ? 'kg' : 'pcs' }}
                                                @if($s->duration_days) · estimasi {{ $s->duration_days }} hari @endif
                                            </span>
                                        </span>
                                    </label>

                                    <div x-show="on" x-cloak class="mt-3 pl-8">
                                        <label class="text-xs text-gray-500">
                                            {{ $s->type === 'kiloan' ? 'Perkiraan berat (kg), boleh dikosongkan' : 'Jumlah (potong)' }}
                                        </label>
                                        <input type="number" name="qty[{{ $s->id }}]" :disabled="!on"
                                               value="{{ old('qty.' . $s->id, $s->type === 'satuan' ? 1 : '') }}"
                                               @if($s->type === 'kiloan')
                                                   step="0.5" min="0.5" max="100" placeholder="Contoh: 3.5"
                                               @else
                                                   step="1" min="1" max="99" required
                                               @endif
                                               class="w-full border-gray-200 rounded-xl mt-1 text-sm">
                                        @error('qty.' . $s->id) <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- 3. Alamat dan jadwal --}}
            <div>
                <label class="text-sm font-medium text-gray-700">Alamat jemput</label>
                <textarea name="address" rows="3" required minlength="8" maxlength="255"
                          placeholder="Jalan, RT/RW, patokan"
                          class="w-full border-gray-200 rounded-xl mt-1">{{ old('address', $customer->address) }}</textarea>
                @error('address') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700">Tanggal</label>
                <select name="preferred_date" required class="w-full border-gray-200 rounded-xl mt-1">
                    @foreach($dateOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('preferred_date') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('preferred_date') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700">Waktu</label>
                <div class="grid grid-cols-3 gap-2 mt-1">
                    @foreach(\App\Models\PickupRequest::TIME_SLOTS as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="time_slot" value="{{ $key }}" class="sr-only peer"
                                   {{ old('time_slot', 'pagi') === $key ? 'checked' : '' }}>
                            <span class="block text-center rounded-xl border px-3 py-3 text-sm text-gray-600 peer-checked:bg-brand-50 peer-checked:border-brand-500 peer-checked:text-brand-700 peer-checked:font-semibold">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-1">Jam pastinya disepakati dengan kasir.</p>
                @error('time_slot') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700">Catatan (opsional)</label>
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="300"
                       placeholder="Contoh: rumah pagar hijau, hubungi dulu"
                       class="w-full border-gray-200 rounded-xl mt-1">
                @error('notes') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-xl bg-gray-50 p-3 text-xs text-gray-500 space-y-1">
                <p>
                    Biaya jemput {{ $rp($pickupFee) }}@if($deliveryEnabled), jemput + antar {{ $rp($pickupFee + $deliveryFee) }}@endif.
                    Dibayar bersama tagihan laundry.
                </p>
                @if(setting('free_ongkir_enabled'))
                    <p>Gratis ongkir kalau laundry minimal {{ (float) setting('free_ongkir_min_weight', 0) }} kg.</p>
                @endif
            </div>

            <button class="w-full bg-brand-600 text-white rounded-xl py-3 font-medium">Kirim Permintaan</button>
        </form>
    @endif

    @if($history->isNotEmpty())
        <div>
            <h2 class="font-semibold text-gray-900 mb-2">Riwayat Permintaan</h2>
            @foreach($history as $h)
                <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $h->preferred_date->translatedFormat('d M Y') }} · {{ $h->slot_label }}</p>
                            <p class="text-xs text-gray-400">{{ $h->mode_label }}</p>
                        </div>
                        <x-badge :status="$h->status" />
                    </div>
                    <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $h->address }}</p>
                    @if($h->staff_note && $h->status !== 'dikonfirmasi')
                        <p class="text-xs text-gray-500 mt-2">Dari kasir: {{ $h->staff_note }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.portal-layout>