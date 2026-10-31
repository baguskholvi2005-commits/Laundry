@props(['title' => 'Akun Pelanggan', 'wide' => false])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ setting('business_name', 'Laundry Desa') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-page text-gray-900">
@php
    $portalNav = [
        ['portal.home', 'layout-grid', 'Beranda', ['portal.home']],
        ['portal.transactions', 'file-text', 'Riwayat', ['portal.transactions', 'portal.transactions.show']],
    ];

    // Menu Jemput hanya muncul kalau layanan jemput menyala di Pengaturan
    if (setting('pickup_enabled')) {
        $portalNav[] = ['portal.pickup', 'truck', 'Jemput', ['portal.pickup', 'portal.pickup.*']];
    }

    $portalNav[] = ['portal.account', 'user', 'Profil', ['portal.account']];
@endphp

<div class="min-h-screen pb-24 md:pb-10">

    <header class="bg-white border-b sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 h-14 flex items-center gap-3">
            @if($logoUrl = business_logo_url())
                <img src="{{ $logoUrl }}" alt="Logo" class="h-8 w-auto object-contain">
            @endif
            <span class="font-bold text-brand-700 truncate">{{ setting('business_name', 'Laundry Desa') }}</span>

            @auth('customer')
                {{-- Menu atas khusus layar lebar --}}
                <nav class="hidden md:flex items-center gap-1 ml-auto">
                    @foreach($portalNav as [$route, $icon, $label, $active])
                        <a href="{{ route($route) }}"
                           class="px-3.5 py-2 rounded-lg text-sm font-medium {{ request()->routeIs(...$active) ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            @endauth
        </div>
    </header>

    <main class="mx-auto px-4 py-4 space-y-4 {{ $wide ? 'max-w-5xl' : 'max-w-2xl' }}">
        @if (session('success'))
            <div class="bg-success-50 text-success-600 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="bg-danger-50 text-danger-600 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>
</div>

{{-- Menu bawah khusus HP --}}
@auth('customer')
    <nav class="md:hidden fixed inset-x-0 bottom-0 z-40 bg-white border-t">
        <div class="h-16 flex">
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