<x-layouts.portal-layout title="Detail Laundry">
    <a href="{{ route('portal.transactions') }}" class="text-sm text-gray-400">← Riwayat</a>

    <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="text-xs text-gray-400">No. Transaksi</p>
                <p class="text-lg font-bold text-gray-900">{{ $transaction->code }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $transaction->created_at->translatedFormat('d F Y, H:i') }} WIB</p>
            </div>
            <x-badge :status="$transaction->status" />
        </div>
        @if($transaction->estimated_done_at && ! in_array($transaction->status, ['selesai', 'dibatalkan']))
            <p class="text-sm text-brand-700 bg-brand-50 rounded-xl px-3 py-2 mt-4">
                Estimasi selesai {{ $transaction->estimated_done_at->translatedFormat('d F Y') }}
            </p>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <x-progress-tracker :status="$transaction->status" :vertical="true" />
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <h2 class="font-semibold text-sm mb-3">Layanan</h2>
        @foreach($transaction->items as $item)
            <div class="flex justify-between py-2 border-b last:border-0 text-sm">
                <div>
                    <p class="font-medium">{{ $item->service_name }}</p>
                    <p class="text-xs text-gray-400">{{ (float) $item->qty }} {{ $item->unit_type }} × Rp {{ number_format($item->price_per_unit, 0, ',', '.') }}</p>
                </div>
                <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <h2 class="font-semibold text-sm mb-3">Pembayaran</h2>
        <div class="space-y-1.5 text-sm">
            <div class="flesisx justify-between"><span class="text-gray-500">Subtotal</span><span>Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</span></div>
            @if($transaction->discount > 0)
                <div class="flex justify-between text-danger-600">
                    <span>Diskon
                        <span class="text-xs">
                            @if($transaction->promoUsages->isNotEmpty())({{ $transaction->promoUsages->map(fn ($u) => $u->promo->name ?? '-')->join(', ') }})@else(Member)@endif
                        </span>
                    </span>
                    <span>- Rp {{ number_format($transaction->discount, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between"><span class="text-gray-500">Ongkir</span><span>Rp {{ number_format($transaction->delivery_fee, 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-bold text-base pt-2 border-t">
                <span>Total</span><span class="text-brand-600">Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between pt-1"><span class="text-gray-500">Sudah dibayar</span><span>Rp {{ number_format($transaction->paid_amount, 0, ',', '.') }}</span></div>
        </div>
        <div class="mt-3"><x-badge :status="$transaction->payment_status" /></div>

        @if($transaction->status !== 'dibatalkan' && $transaction->remaining > 0)
            <p class="text-sm text-warning-600 bg-warning-50 rounded-xl px-3 py-2 mt-3">
                Sisa tagihan <span class="font-bold">Rp {{ number_format($transaction->remaining, 0, ',', '.') }}</span>. Pembayaran tunai saat cucian diantar atau diambil.
                @if(setting('payment_info')) <span class="block mt-1 whitespace-pre-line">{{ setting('payment_info') }}</span> @endif
            </p>
        @endif
    </div>
</x-layouts.portal-layout>