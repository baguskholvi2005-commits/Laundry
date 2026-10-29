<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Detail Antar-Jemput</span></x-slot>

    @php
        $trx = $pickup->transaction;
        $cust = $trx->customer;
        $isDelivery = $pickup->type === 'delivery';
        $remaining = max(0, (float) $trx->total - (float) $trx->paid_amount);
        $active = in_array($pickup->status, ['dijadwalkan', 'dalam_perjalanan'], true);
        $kgTotal = $trx->items->where('unit_type', 'kg')->sum('qty');
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    @endphp

    <a href="{{ route('pickups.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Antar-Jemput</a>

    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 mt-2 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $isDelivery ? 'Antar cucian bersih' : 'Jemput cucian' }}</h1>
            <p class="text-gray-500">
                Nota <a href="{{ route('transactions.show', $trx) }}" class="text-brand-600 font-medium">{{ $trx->code }}</a>
                · transaksi {{ ucwords(str_replace('_', ' ', $trx->status)) }}
            </p>
        </div>
        <x-badge :status="$pickup->status" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h2 class="font-semibold mb-3">Pelanggan dan alamat</h2>
                <p class="font-semibold text-gray-900">
                    @if($cust)<a href="{{ route('customers.show', $cust) }}" class="hover:text-brand-600">{{ $cust->name }}</a>@else Pelanggan Umum @endif
                </p>
                @if($cust?->phone)
                    <p class="text-sm text-gray-500">
                        {{ $cust->phone }}
                        @if($cust->phone_normalized)<a href="https://wa.me/{{ $cust->phone_normalized }}" target="_blank" class="ml-2 text-xs bg-brand-50 text-brand-700 rounded-md px-2 py-0.5 font-medium">Chat WA</a>@endif
                    </p>
                @endif
                <p class="text-sm text-gray-700 mt-3 whitespace-pre-line">{{ $pickup->address ?: 'Alamat belum diisi' }}</p>
                @if($pickup->scheduled_at)
                    <p class="text-xs text-gray-400 mt-2">Dijadwalkan {{ $pickup->scheduled_at->translatedFormat('d M Y, H:i') }} WIB</p>
                @endif

                @if($linkedRequest)
                    <div class="rounded-xl bg-gray-50 p-3 mt-4 text-sm">
                        <p class="text-xs text-gray-400 mb-1">Dari permintaan pelanggan</p>
                        <p>{{ $linkedRequest->mode_label }} · {{ $linkedRequest->preferred_date->translatedFormat('d M Y') }} · {{ $linkedRequest->slot_label }}</p>
                        @if($linkedRequest->notes)<p class="text-gray-600 mt-1">Catatan: {{ $linkedRequest->notes }}</p>@endif
                    </div>
                @endif
                @if($trx->notes)
                    <p class="rounded-xl bg-brand-50 text-sm text-gray-700 px-3 py-2 mt-3"><span class="font-semibold">Catatan transaksi:</span> {{ $trx->notes }}</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h2 class="font-semibold mb-3">Cucian @if($kgTotal > 0)<span class="text-sm font-normal text-gray-500">· {{ rtrim(rtrim(number_format((float) $kgTotal, 1, ',', '.'), '0'), ',') }} kg</span>@endif</h2>
                @foreach($trx->items as $item)
                    <div class="flex justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <span>{{ $item->service_name }}</span>
                        <span class="text-gray-500">{{ (float) $item->qty }} {{ $item->unit_type }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h2 class="font-semibold mb-3">Tagihan</h2>
                <div class="flex justify-between text-sm"><span class="text-gray-500">Total</span><span class="font-semibold">{{ $rp($trx->total) }}</span></div>
                <div class="flex justify-between text-sm mt-1"><span class="text-gray-500">Sudah dibayar</span><span>{{ $rp($trx->paid_amount) }}</span></div>
                @if($remaining > 0)
                    <p class="rounded-xl bg-gold-50 text-warning-700 text-sm font-semibold px-3 py-2 mt-3">Sisa {{ $rp($remaining) }}</p>
                @else
                    <p class="rounded-xl bg-success-50 text-success-700 text-sm font-semibold px-3 py-2 mt-3">Lunas</p>
                @endif
            </div>

            @if($active)
                <div class="bg-white rounded-2xl border border-gray-100 p-5">
                    <h2 class="font-semibold mb-3">Tindakan</h2>
                    @include('pickups._actions', ['task' => $pickup, 'trx' => $trx])
                </div>
            @endif
        </div>
    </div>
</x-layouts.app-layout>