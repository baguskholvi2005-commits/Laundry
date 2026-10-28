<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Akun Pelanggan' }} · {{ setting('business_name', 'Laundry Desa') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
<div class="min-h-screen max-w-2xl mx-auto pb-24">

    <header class="bg-white border-b px-4 h-14 flex items-center gap-3 sticky top-0 z-30">
        @if($logoUrl = business_logo_url())
            <img src="{{ $logoUrl }}" alt="Logo" class="h-8 w-auto object-contain">
        @endif
        <span class="font-bold text-brand-600 truncate">{{ setting('business_name', 'Laundry Desa') }}</span>
    </header>

    <main class="p-4 space-y-4">
        @if (session('success'))
            <div class="bg-success-50 text-success-600 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-danger-50 text-danger-600 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>
</div>

@auth('customer')
    @php
        $portalNav = [
            ['portal.home', 'layout-grid', 'Beranda', ['portal.home']],
            ['portal.transactions', 'file-text', 'Riwayat', ['portal.transactions', 'portal.transactions.show']],
        ];

        // Menu Jemput hanya muncul kalau layanan jemput menyala di Pengaturan
        if (setting('pickup_enabled')) {
            $portalNav[] = ['portal.pickup', 'truck', 'Jemput', ['portal.pickup', 'portal.pickup.*']];
        }

        $portalNav[] = ['portal.account', 'user', 'Akun', ['portal.account']];
    @endphp

    <nav class="fixed inset-x-0 bottom-0 z-40 bg-white border-t">
        <div class="max-w-2xl mx-auto h-16 flex">
            @foreach($portalNav as [$route, $icon, $label, $active])
                <a href="{{ route($route) }}"
                   class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[11px]
                   {{ request()->routeIs(...$active) ? 'text-brand-600 font-semibold' : 'text-gray-500' }}">
                    <x-dynamic-component :component="'lucide-' . $icon" class="w-5 h-5" />
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </nav>
@endauth
</body>
</html>