@php
    $isDelivery = $task->type === 'delivery';
    $ready = in_array($trx->status, ['siap_diambil', 'selesai'], true);
    $remaining = max(0, (float) $trx->total - (float) $trx->paid_amount);

    $primary = 'bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-medium hover:bg-brand-700';
    $ghost = 'border bg-white text-gray-700 rounded-xl px-4 py-2.5 text-sm font-medium hover:bg-gray-50';
    $danger = 'border border-danger-200 bg-white text-danger-600 rounded-xl px-4 py-2.5 text-sm font-medium hover:bg-danger-50';
    $cancelAsk = 'Batalkan tugas ini? Transaksi ' . $trx->code . ' tidak ikut dibatalkan.';
@endphp

<div class="flex flex-wrap items-start gap-2">
    @if($task->status === 'dijadwalkan')
        @if(! $isDelivery)
            @include('pickups._action', ['task' => $task, 'status' => 'dalam_perjalanan', 'label' => 'Berangkat Jemput', 'class' => $primary])
        @elseif($ready)
            @include('pickups._action', ['task' => $task, 'status' => 'dalam_perjalanan', 'label' => 'Kirim Sekarang', 'class' => $primary])
            <a href="{{ route('transactions.nota', $trx) }}" target="_blank" class="{{ $ghost }}">Bawa Nota Fisik</a>
        @else
            <p class="basis-full text-sm text-gray-500">
                Menunggu cucian selesai diproses (status transaksi: {{ ucwords(str_replace('_', ' ', $trx->status)) }}).
                Tombol kirim aktif setelah statusnya Siap Diambil.
            </p>
            <a href="{{ route('transactions.show', $trx) }}" class="{{ $ghost }}">Buka Transaksi</a>
        @endif

        @include('pickups._action', ['task' => $task, 'status' => 'dibatalkan', 'label' => 'Batal', 'class' => $danger, 'confirm' => $cancelAsk])

    @elseif($task->status === 'dalam_perjalanan')
        <form method="POST" action="{{ route('pickups.updateStatus', $task) }}" class="basis-full space-y-2">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="selesai">

            @if($isDelivery && $remaining > 0)
                <label class="flex items-start gap-2 rounded-xl bg-gold-50 px-3 py-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="collect_payment" value="1" class="mt-0.5 rounded border-gray-300 text-brand-600">
                    <span>
                        Tagihan <span class="font-semibold">Rp {{ number_format($remaining, 0, ',', '.') }}</span> sudah diterima tunai dari pelanggan.
                        Centang untuk mencatatnya sebagai pembayaran lunas.
                    </span>
                </label>
            @endif

            <button class="w-full {{ $primary }}">
                {{ $isDelivery ? 'Selesaikan Pengantaran (Telah Diterima Pelanggan)' : 'Selesaikan Penjemputan (Cucian Diambil)' }}
            </button>
        </form>

        @include('pickups._action', ['task' => $task, 'status' => 'dijadwalkan', 'label' => 'Kembalikan ke Jadwal', 'class' => $ghost])
        @include('pickups._action', ['task' => $task, 'status' => 'dibatalkan', 'label' => 'Batal', 'class' => $danger, 'confirm' => $cancelAsk])
    @endif
</div>