<x-layouts.app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold">Permintaan Jemput</h1></x-slot>

    @php
        $tabs = [
            'menunggu' => ['Menunggu', $counts['menunggu'] ?? 0],
            'dikonfirmasi' => ['Dikonfirmasi', $counts['dikonfirmasi'] ?? 0],
            'riwayat' => ['Riwayat', 0],
        ];
        $biz = setting('business_name', 'Laundry Desa');
    @endphp

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Permintaan Jemput</h1>
            <p class="text-gray-500">Permintaan dari pelanggan lewat akun online mereka.</p>
        </div>
        <a href="{{ route('pickups.index') }}" class="border rounded-xl px-4 py-2.5 text-sm font-medium w-fit">← Jadwal Antar-Jemput</a>
    </div>

    <div class="flex flex-wrap bg-white border rounded-xl p-1 text-sm w-fit mb-5">
        @foreach($tabs as $key => [$label, $count])
            <a href="{{ route('pickup-requests.index', ['tab' => $key]) }}"
               class="px-4 py-1.5 rounded-lg flex items-center gap-2 {{ $tab === $key ? 'bg-brand-600 text-white' : '' }}">
                {{ $label }}
                @if($count > 0)
                    <span class="text-[11px] rounded-full px-1.5 {{ $tab === $key ? 'bg-white/25' : 'bg-danger-500 text-white' }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if($requests->isEmpty())
        <x-empty-state
            :title="match($tab) {
                'menunggu' => 'Tidak ada permintaan yang menunggu',
                'dikonfirmasi' => 'Belum ada permintaan yang dikonfirmasi',
                default => 'Belum ada riwayat',
            }"
            description="Permintaan baru dari pelanggan muncul di sini." />
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            @foreach($requests as $r)
            @php
                $active = in_array($r->status, ['menunggu', 'dikonfirmasi'], true);
                $overdue = $active && $r->preferred_date->lt(today());
                $wa = $r->customer->phone_normalized
                    ? 'https://wa.me/' . $r->customer->phone_normalized . '?text=' . urlencode(
                        'Halo ' . $r->customer->name . ', terkait permintaan ' . strtolower($r->mode_label) . ' laundry Anda pada '
                        . $r->preferred_date->translatedFormat('d M Y') . ' (' . $r->slot_label . ') di ' . $biz . '.')
                    : null;
            @endphp
            <div class="bg-white rounded-2xl border border-gray-100 p-5" x-data="{ panel: null }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('customers.show', $r->customer) }}" class="font-semibold text-gray-900 hover:text-brand-600">{{ $r->customer->name }}</a>
                            @if($r->customer->is_member)
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">⭐ Member</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400">{{ $r->customer->phone }}</p>
                    </div>
                    <x-badge :status="$r->status" />
                </div>

                <div class="mt-3 text-sm space-y-2">
                    <p class="font-medium text-gray-800">
                        {{ $r->preferred_date->translatedFormat('l, d M Y') }} · {{ $r->slot_label }}
                        @if($overdue)<span class="text-xs text-danger-600 font-normal ml-1">tanggal sudah lewat</span>@endif
                    </p>

                    {{-- Jenis layanan --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs px-2.5 py-1 rounded-full font-medium {{ $r->wants_delivery ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $r->mode_label }}
                        </span>
                        <span class="text-xs text-gray-400">
                            {{ $r->wants_delivery ? 'diantar kembali setelah selesai' : 'diambil sendiri di toko setelah selesai' }}
                        </span>
                    </div>

                    {{-- Layanan yang diminta --}}
                    <div class="rounded-xl bg-gray-50 p-3">
                        <p class="text-xs text-gray-400 mb-1">Layanan yang diminta (perkiraan pelanggan)</p>
                        @forelse($r->items as $it)
                            <div class="flex justify-between gap-3 py-0.5">
                                <span class="text-gray-800">{{ $it->service_name }}</span>
                                <span class="text-gray-500 whitespace-nowrap">{{ $it->qty_label }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">Pelanggan belum memilih layanan (permintaan lama).</p>
                        @endforelse
                    </div>

                    <p class="text-gray-600 whitespace-pre-line">{{ $r->address }}</p>
                    @if($r->notes)
                        <p class="text-xs text-gray-400">Catatan pelanggan: {{ $r->notes }}</p>
                    @endif
                    @if($r->staff_note)
                        <p class="text-xs text-brand-700 bg-brand-50 rounded-lg px-2.5 py-1.5">Catatan kasir: {{ $r->staff_note }}</p>
                    @endif
                    <p class="text-xs text-gray-400">Dikirim {{ $r->created_at->diffForHumans() }}</p>
                </div>

                {{-- Aksi --}}
                @if($active)
                <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t">
                    @if($r->status === 'menunggu')
                        @unless($overdue)
                            <button type="button" @click="panel = panel === 'confirm' ? null : 'confirm'"
                                    class="bg-brand-600 text-white rounded-xl px-4 py-2 text-sm font-medium">Konfirmasi</button>
                        @endunless
                        <button type="button" @click="panel = panel === 'reject' ? null : 'reject'"
                                class="border border-danger-200 text-danger-600 rounded-xl px-4 py-2 text-sm">Tolak</button>
                    @else
                        <a href="{{ route('transactions.create', ['pickup_request' => $r->id]) }}"
                           class="bg-brand-600 text-white rounded-xl px-4 py-2 text-sm font-medium">Buat Transaksi</a>
                        <button type="button" @click="panel = panel === 'reject' ? null : 'reject'"
                                class="border border-danger-200 text-danger-600 rounded-xl px-4 py-2 text-sm">Batalkan Permintaan</button>
                    @endif

                    @if($wa)
                        <a href="{{ $wa }}" target="_blank" class="border border-success-200 text-success-600 rounded-xl px-4 py-2 text-sm">WhatsApp</a>
                    @endif
                </div>

                <form method="POST" action="{{ route('pickup-requests.confirm', $r) }}" x-show="panel === 'confirm'" x-cloak class="mt-3 space-y-2">
                    @csrf @method('PATCH')
                    <input type="text" name="staff_note" maxlength="300" placeholder="Catatan untuk pelanggan (opsional), misalnya: kurir datang sekitar jam 9"
                           class="w-full border-gray-200 rounded-xl text-sm">
                    <button class="bg-brand-600 text-white rounded-xl px-4 py-2 text-sm font-medium">Simpan Konfirmasi</button>
                </form>

                <form method="POST" action="{{ route('pickup-requests.reject', $r) }}" x-show="panel === 'reject'" x-cloak class="mt-3 space-y-2"
                      onsubmit="return confirm('Tolak atau batalkan permintaan ini? Alasanmu akan terlihat oleh pelanggan.')">
                    @csrf @method('PATCH')
                    <input type="text" name="staff_note" maxlength="300" required placeholder="Alasan (wajib), misalnya: alamat di luar jangkauan"
                           class="w-full border-gray-200 rounded-xl text-sm">
                    <button class="border border-danger-200 text-danger-600 rounded-xl px-4 py-2 text-sm font-medium">Kirim Penolakan</button>
                </form>

                @else
                <div class="mt-4 pt-4 border-t text-xs text-gray-400 space-y-1">
                    @if($r->handler)<p>Ditangani {{ $r->handler->name }}@if($r->handled_at), {{ $r->handled_at->translatedFormat('d M Y, H:i') }}@endif</p>@endif
                    @if($r->transaction)
                        <p>Transaksi: <a href="{{ route('transactions.show', $r->transaction) }}" class="text-brand-600 font-medium">{{ $r->transaction->code }}</a></p>
                    @endif
                </div>
                @endif
            </div>
            @endforeach
        </div>

        <div class="mt-4">
            <x-pagination :paginator="$requests" />
        </div>
    @endif
</x-layouts.app-layout>