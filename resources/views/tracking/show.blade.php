<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Lacak Cucian {{ $transaction->code }} · {{ setting('business_name', 'Laundry Desa') }}</title>
    @unless(in_array($transaction->status, ['selesai', 'dibatalkan'], true))
        <meta http-equiv="refresh" content="120">
    @endunless
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-page text-gray-900">
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $logo = business_logo_url();
    $wa = preg_replace('/\D/', '', (string) setting('business_whatsapp', ''));
    $waShow = $wa !== '' ? (str_starts_with($wa, '62') ? '0' . substr($wa, 2) : $wa) : null;
    $cancelled = $transaction->status === 'dibatalkan';
    $done = in_array($transaction->status, ['selesai', 'dibatalkan'], true);
    $remaining = $transaction->remaining;
    $waText = 'Halo, saya ingin menanyakan cucian dengan nomor ' . $transaction->code . '.';
    $statusLabel = ucwords(str_replace('_', ' ', $transaction->status));
@endphp

<div class="max-w-md lg:max-w-4xl mx-auto px-4 py-5 pb-10">

    <header class="flex items-center gap-3 mb-4">
        @if($logo)
            <img src="{{ $logo }}" alt="Logo" class="h-10 w-10 rounded-xl object-contain bg-white p-1">
        @else
            <span class="w-10 h-10 rounded-xl bg-brand-600 text-white flex items-center justify-center"><x-lucide-shirt class="w-5 h-5" /></span>
        @endif
        <div class="min-w-0">
            <p class="font-bold text-gray-900 leading-tight truncate">{{ setting('business_name', 'Laundry Desa') }}</p>
            <p class="text-xs text-gray-500">Lacak cucian mandiri</p>
        </div>
    </header>

    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs text-gray-400">Nomor transaksi</p>
                <p class="font-mono font-bold text-gray-900">{{ $transaction->code }}</p>
            </div>
            <span class="text-xs font-semibold rounded-md px-2.5 py-1 whitespace-nowrap
                {{ $cancelled ? 'bg-danger-50 text-danger-600' : ($transaction->status === 'selesai' ? 'bg-success-50 text-success-700' : 'bg-gold-100 text-warning-700') }}">
                {{ $statusLabel }}
            </span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mt-3">Halo{{ $firstName ? ', ' . $firstName : '' }}</h1>
        <p class="text-sm text-gray-500">Pantau tahapan cucianmu tanpa perlu login. Halaman ini memperbarui diri tiap 2 menit.</p>
        @if($transaction->estimated_done_at && ! $done)
            <p class="text-sm text-brand-800 bg-brand-50 rounded-xl px-3 py-2 mt-3">Estimasi selesai {{ $transaction->estimated_done_at->translatedFormat('l, d F Y') }}</p>
        @endif
    </div>

    <div class="grid lg:grid-cols-2 gap-4 items-start">

        {{-- Progres --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">Pelacak proses cucian</h2>
            <x-progress-tracker :status="$transaction->status" :vertical="true" />
            <p class="text-xs text-gray-400 mt-4">Diterima {{ $transaction->created_at->translatedFormat('d M Y, H:i') }} WIB</p>
        </div>

        <div class="space-y-4">

            {{-- Rincian --}}
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">Rincian cucian</h2>
                @foreach($transaction->items as $item)
                    <div class="flex justify-between gap-3 py-2 border-b border-gray-100 last:border-0 text-sm">
                        <div>
                            <p class="font-medium text-gray-900">{{ $item->service_name }}</p>
                            <p class="text-xs text-gray-400">{{ (float) $item->qty }} {{ $item->unit_type }} × {{ $rp($item->price_per_unit) }}</p>
                        </div>
                        <span class="font-medium whitespace-nowrap">{{ $rp($item->subtotal) }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Pembayaran --}}
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-gray-900">Status pembayaran</h2>
                    <x-badge :status="$transaction->payment_status" />
                </div>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Total biaya</span><span class="tabular-nums">{{ $rp($transaction->total) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Sudah dibayar</span><span class="tabular-nums">{{ $rp($transaction->paid_amount) }}</span></div>
                    @if(! $cancelled && $remaining > 0)
                        <div class="flex justify-between items-end pt-2 border-t border-gray-100">
                            <span class="font-semibold text-gray-900">Sisa kurang bayar</span>
                            <span class="text-xl font-bold text-warning-700 tabular-nums">{{ $rp($remaining) }}</span>
                        </div>
                    @endif
                </div>
                @if(! $cancelled && $remaining > 0)
                    <p class="text-xs text-gray-500 bg-brand-50 rounded-xl px-3 py-2 mt-3">
                        Pelunasan dilakukan di toko saat pengambilan cucian.
                        @if(setting('payment_info')) <span class="block mt-1 whitespace-pre-line">{{ setting('payment_info') }}</span> @endif
                    </p>
                @endif
            </div>

            {{-- Kontak --}}
            <div class="space-y-2">
                @if($wa !== '')
                    <a href="https://wa.me/{{ $wa }}?text={{ urlencode($waText) }}" target="_blank"
                       class="block text-center bg-brand-700 text-white rounded-xl py-3 text-sm font-semibold hover:bg-brand-800">
                        Tanya kasir lewat WhatsApp ({{ $waShow }})
                    </a>
                @endif
                <button type="button" onclick="location.reload()"
                        class="w-full bg-white border rounded-xl py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Muat ulang status terkini</button>
            </div>
        </div>
    </div>

    <div class="text-center text-xs text-gray-400 mt-6 space-y-1">
        @if($hours = operating_hours_text())<p>Jam buka: {{ $hours }}</p>@endif
        @if(setting('business_address'))<p>{{ setting('business_address') }}</p>@endif
        <p>Punya akun pelanggan? <a href="{{ route('portal.login') }}" class="text-brand-600">Masuk</a></p>
    </div>
</div>
</body>
</html>