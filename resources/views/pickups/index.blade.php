<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Antar-Jemput</span></x-slot>

    @php
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $kgFmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, ',', '.'), '0'), ',');
        $dayLabel = fn ($d) => $d->isToday() ? 'Hari ini' : ($d->isTomorrow() ? 'Besok' : $d->translatedFormat('l, d M'));
        $methodLabels = payment_method_labels();
        $chip = 'text-[11px] font-semibold tracking-wide rounded-md px-2.5 py-1 uppercase whitespace-nowrap';
    @endphp

    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <x-lucide-truck class="w-8 h-8 text-brand-600" /> Antar-Jemput Cucian
            </h1>
            <p class="text-gray-500 mt-1">Penjemputan cucian pelanggan dan pengantaran cucian yang sudah bersih.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('pickup-requests.index', ['tab' => 'riwayat']) }}"
               class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Riwayat Permintaan</a>
            @if(setting('pickup_enabled'))
                <a href="{{ route('transactions.create', ['pickup' => 1]) }}"
                   class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-medium hover:bg-brand-700">+ Jadwalkan Jemput Baru</a>
            @endif
        </div>
    </div>

    {{-- Kartu angka --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-warning-600 uppercase">Menunggu penjemputan</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $stats['pickup_waiting'] }} <span class="text-base font-semibold text-gray-500">titik jemput</span></p>
            <p class="text-sm text-gray-500 mt-1">
                {{ $stats['requests'] > 0 ? $stats['requests'] . ' dari permintaan akun pelanggan' : 'Pelanggan minta cucian diambil di rumah' }}
            </p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-success-600 uppercase">Siap diantar ke rumah</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $stats['delivery_ready'] }} <span class="text-base font-semibold text-gray-500">cucian selesai</span></p>
            <p class="text-sm text-gray-500 mt-1">Sudah bersih, siap dikirim balik</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Sedang di jalan</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $stats['on_the_road'] }} <span class="text-base font-semibold text-gray-500">tugas</span></p>
            <p class="text-sm text-gray-500 mt-1">Penjemputan atau pengantaran berjalan</p>
        </div>
    </div>

    {{-- Tab dan pencarian --}}
    @php $qs = $q !== '' ? ['q' => $q] : []; @endphp
    <div class="bg-white rounded-2xl border border-gray-100 p-3 mb-5 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex flex-wrap bg-gray-50 border rounded-xl p-1 text-sm w-fit">
            <a href="{{ route('pickups.index', $qs) }}" class="px-4 py-1.5 rounded-lg {{ $type === null ? 'bg-brand-600 text-white' : 'text-gray-600' }}">Semua Tugas ({{ $counts['all'] }})</a>
            <a href="{{ route('pickups.pickup', $qs) }}" class="px-4 py-1.5 rounded-lg {{ $type === 'pickup' ? 'bg-brand-600 text-white' : 'text-gray-600' }}">Jemput Cucian ({{ $counts['pickup'] }})</a>
            <a href="{{ route('pickups.delivery', $qs) }}" class="px-4 py-1.5 rounded-lg {{ $type === 'delivery' ? 'bg-brand-600 text-white' : 'text-gray-600' }}">Antar Cucian Bersih ({{ $counts['delivery'] }})</a>
        </div>

        <form method="GET" action="{{ url()->current() }}" class="flex-1 lg:max-w-md lg:ml-auto">
            <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama, nota, atau alamat/dusun..."
                   class="w-full border-gray-200 rounded-xl text-sm bg-gray-50">
        </form>
    </div>

    @if($visible->isEmpty())
        <x-empty-state
            :title="$q !== '' ? 'Tidak ada tugas yang cocok' : 'Tidak ada tugas antar-jemput'"
            :description="$q !== '' ? 'Coba kata kunci lain.' : 'Tugas muncul saat transaksi memakai Jemput atau Antar, dan saat pelanggan mengirim permintaan jemput.'" />
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            @foreach($visible as $card)

                {{-- ============ PERMINTAAN DARI AKUN PELANGGAN ============ --}}
                @if($card['kind'] === 'request')
                    @php
                        $r = $card['request'];
                        $overdue = $r->preferred_date->lt(today());
                        $wa = $r->customer->phone_normalized;
                    @endphp
                    <div class="bg-white rounded-2xl border border-gray-100 p-5 flex flex-col" x-data="{ panel: null }">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="{{ $chip }} bg-brand-600 text-white">Jemput cucian</span>
                            <span class="{{ $chip }} {{ $r->status === 'menunggu' ? 'bg-gold-100 text-warning-700' : 'bg-brand-100 text-brand-700' }}">
                                {{ $r->status === 'menunggu' ? 'Menunggu konfirmasi' : 'Dikonfirmasi' }}
                            </span>
                            <span class="text-[11px] text-gray-400">Permintaan pelanggan</span>
                        </div>

                        <div class="flex items-start justify-between gap-3 mt-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="{{ route('customers.show', $r->customer) }}" class="text-lg font-bold text-gray-900 hover:text-brand-600">{{ $r->customer->name }}</a>
                                    @if($r->customer->is_member)
                                        <span class="text-[11px] font-semibold bg-gold-300 text-gray-900 rounded-md px-2 py-0.5">Member</span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-500">
                                    Diminta: {{ $dayLabel($r->preferred_date) }} · {{ $r->slot_label }}
                                    @if($overdue)<span class="text-danger-600">(tanggal sudah lewat)</span>@endif
                                </p>
                            </div>
                            <span class="text-xs px-2.5 py-1 rounded-full whitespace-nowrap {{ $r->wants_delivery ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->mode_label }}</span>
                        </div>

                        <p class="text-sm text-gray-700 mt-3 whitespace-pre-line">{{ $r->address }}</p>
                        <p class="text-sm text-gray-500 mt-1">
                            No. WhatsApp: {{ $r->customer->phone }}
                            @if($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" class="ml-2 text-xs bg-brand-50 text-brand-700 rounded-md px-2 py-0.5 font-medium">Chat WA</a>@endif
                        </p>

                        @if($r->items->isNotEmpty())
                            <div class="rounded-xl bg-gray-50 p-3 mt-3 text-sm">
                                <p class="text-xs text-gray-400 mb-1">Layanan yang diminta (perkiraan)</p>
                                @foreach($r->items as $it)
                                    <div class="flex justify-between gap-3 py-0.5">
                                        <span class="text-gray-800">{{ $it->service_name }}</span>
                                        <span class="text-gray-500 whitespace-nowrap">{{ $it->qty_label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($r->notes)
                            <p class="rounded-xl bg-brand-50 text-sm text-gray-700 px-3 py-2 mt-3"><span class="font-semibold">Catatan khusus:</span> {{ $r->notes }}</p>
                        @endif
                        @if($r->staff_note)
                            <p class="text-xs text-brand-700 mt-2">Catatan kasir: {{ $r->staff_note }}</p>
                        @endif

                        <div class="flex flex-wrap gap-2 mt-auto pt-4">
                            @if($r->status === 'menunggu')
                                @unless($overdue)
                                    <button type="button" @click="panel = panel === 'confirm' ? null : 'confirm'"
                                            class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-medium hover:bg-brand-700">Konfirmasi</button>
                                @endunless
                                <button type="button" @click="panel = panel === 'reject' ? null : 'reject'"
                                        class="border border-danger-200 bg-white text-danger-600 rounded-xl px-4 py-2.5 text-sm font-medium">Tolak</button>
                            @else
                                <a href="{{ route('transactions.create', ['pickup_request' => $r->id]) }}"
                                   class="bg-brand-600 text-white rounded-xl px-4 py-2.5 text-sm font-medium hover:bg-brand-700">Buat Transaksi</a>
                                <button type="button" @click="panel = panel === 'reject' ? null : 'reject'"
                                        class="border border-danger-200 bg-white text-danger-600 rounded-xl px-4 py-2.5 text-sm font-medium">Batalkan Permintaan</button>
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
                    </div>

                {{-- ============ TUGAS JEMPUT / ANTAR ============ --}}
                @else
                    @php
                        $task = $card['task'];
                        $linked = $card['request'];
                        $trx = $task->transaction;
                        $cust = $trx->customer;
                        $isDelivery = $task->type === 'delivery';
                        $ready = in_array($trx->status, ['siap_diambil', 'selesai'], true);
                        $transit = $task->status === 'dalam_perjalanan';
                        $kgTotal = $trx->items->where('unit_type', 'kg')->sum('qty');
                        $names = $trx->items->pluck('service_name');
                        $remaining = max(0, (float) $trx->total - (float) $trx->paid_amount);
                        $lastPay = $trx->payments->sortByDesc('id')->first();
                        $notes = collect([$linked?->notes, $trx->notes])->filter()->unique()->values();
                        $wa = $cust?->phone_normalized;
                    @endphp
                    <div class="bg-white rounded-2xl border p-5 flex flex-col {{ $transit ? 'border-brand-300' : 'border-gray-100' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($isDelivery)
                                <span class="{{ $chip }} bg-success-700 text-white">Antar cucian bersih</span>
                            @else
                                <span class="{{ $chip }} bg-brand-600 text-white">Jemput cucian</span>
                            @endif

                            @if($transit)
                                <span class="{{ $chip }} bg-brand-100 text-brand-700">Dalam perjalanan</span>
                            @elseif($isDelivery && $ready)
                                <span class="{{ $chip }} bg-success-50 text-success-700">Siap diantar</span>
                            @elseif($isDelivery)
                                <span class="{{ $chip }} bg-gray-100 text-gray-500">Menunggu cucian selesai</span>
                            @else
                                <span class="{{ $chip }} bg-gold-100 text-warning-700">Menunggu penjemputan</span>
                            @endif

                            <a href="{{ route('transactions.show', $trx) }}" class="text-xs bg-brand-50 text-brand-700 rounded-md px-2 py-1 font-medium">Nota {{ $trx->code }}</a>
                        </div>

                        <div class="flex items-start justify-between gap-3 mt-3">
                            <div class="min-w-0">
                                @if($cust)
                                    <a href="{{ route('customers.show', $cust) }}" class="text-lg font-bold text-gray-900 hover:text-brand-600">{{ $cust->name }}</a>
                                @else
                                    <p class="text-lg font-bold text-gray-900">Pelanggan Umum</p>
                                @endif
                                <p class="text-sm text-gray-500">
                                    @if($isDelivery)
                                        @if($trx->estimated_done_at) Estimasi cucian selesai {{ $trx->estimated_done_at->translatedFormat('d M') }} @endif
                                    @elseif($linked)
                                        Diminta: {{ $dayLabel($linked->preferred_date) }} · {{ $linked->slot_label }}
                                    @else
                                        Dicatat {{ $task->created_at->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            <p class="text-sm text-right text-gray-700 font-medium">
                                @if($kgTotal > 0){{ $kgFmt($kgTotal) }} kg @endif
                                <span class="block text-xs font-normal text-gray-500">{{ $names->take(2)->join(', ') }}{{ $names->count() > 2 ? ' +' . ($names->count() - 2) : '' }}</span>
                            </p>
                        </div>

                        @if($task->address)
                            <p class="text-sm text-gray-700 mt-3 whitespace-pre-line">{{ $task->address }}</p>
                        @else
                            <p class="text-sm text-danger-600 mt-3">Alamat belum diisi. Lengkapi lewat Edit Pelanggan.</p>
                        @endif

                        @if($cust?->phone)
                            <p class="text-sm text-gray-500 mt-1">
                                No. WhatsApp: {{ $cust->phone }}
                                @if($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" class="ml-2 text-xs bg-brand-50 text-brand-700 rounded-md px-2 py-0.5 font-medium">Chat WA</a>@endif
                            </p>
                        @endif

                        @foreach($notes as $n)
                            <p class="rounded-xl bg-brand-50 text-sm text-gray-700 px-3 py-2 mt-3"><span class="font-semibold">Catatan khusus:</span> {{ $n }}</p>
                        @endforeach

                        @if($isDelivery)
                            @if($remaining > 0)
                                <div class="flex items-center justify-between gap-3 rounded-xl bg-gold-50 border border-gold-300 px-3 py-2 mt-3">
                                    <span class="text-xs font-semibold text-warning-700 uppercase">Belum lunas, tagih tunai di tempat</span>
                                    <span class="font-bold text-warning-700 tabular-nums">{{ $rp($remaining) }}</span>
                                </div>
                            @else
                                <div class="flex items-center justify-between gap-3 rounded-xl bg-success-50 px-3 py-2 mt-3">
                                    <span class="text-xs font-semibold text-success-700 uppercase">Status tagihan: sudah lunas</span>
                                    <span class="font-bold text-success-700 tabular-nums">
                                        {{ $lastPay ? ($methodLabels[$lastPay->method] ?? ucfirst($lastPay->method)) . ' ' : '' }}{{ $rp($trx->total) }}
                                    </span>
                                </div>
                            @endif
                        @endif

                        <div class="mt-auto pt-4">
                            @include('pickups._actions', ['task' => $task, 'trx' => $trx])
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</x-layouts.app-layout>