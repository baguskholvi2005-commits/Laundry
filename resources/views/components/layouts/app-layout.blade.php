<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? setting('business_name', 'Laundry Desa') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|jetbrains-mono:500,700&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-page text-gray-900">
@php
    $authUser = auth()->user();

    // Isi navigasi bawah di HP: [route, ikon, label, pola route aktif]
    $bottomItems = match (true) {
        $authUser->hasRole('pemilik') => [
            ['dashboard', 'layout-grid', 'Beranda', ['dashboard']],
            ['transactions.index', 'file-text', 'Transaksi', ['transactions.*']],
            ['customers.index', 'users', 'Pelanggan', ['customers.*']],
            ['reports.index', 'bar-chart-3', 'Laporan', ['reports.*']],
        ],
        $authUser->hasRole('kasir') => [
            ['transactions.create', 'plus', 'Baru', ['transactions.create']],
            ['transactions.index', 'file-text', 'Transaksi', ['transactions.index', 'transactions.show', 'transactions.processing', 'transactions.ready', 'transactions.history']],
            ['customers.index', 'users', 'Pelanggan', ['customers.*']],
            ['pickups.index', 'truck', 'Antar-Jemput', ['pickups.*']],
        ],
        default => [
            ['process.queue', 'clipboard-list', 'Antrian', ['process.queue']],
        ],
    };
@endphp

<div class="min-h-screen flex" x-data="{ mobileNavOpen: false }" @keydown.escape.window="mobileNavOpen = false">

    {{-- Sidebar desktop --}}
    <aside class="w-64 bg-sidebar hidden md:flex md:flex-col flex-shrink-0 md:sticky md:top-0 md:h-screen">
        @include('layouts.partials.nav')
    </aside>

    {{-- Menu geser untuk HP --}}
    <div x-show="mobileNavOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 md:hidden">
        <div class="absolute inset-0 bg-black/50" @click="mobileNavOpen = false"></div>
        <aside class="absolute inset-y-0 left-0 w-72 max-w-[85%] bg-sidebar flex flex-col shadow-xl">
            <button type="button" @click="mobileNavOpen = false" aria-label="Tutup menu"
                    class="absolute top-4 right-4 text-white/60 hover:text-white">&#10005;</button>
            @include('layouts.partials.nav')
        </aside>
    </div>

    {{-- Konten --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center gap-3 px-4 md:px-6 sticky top-0 z-20">
            <button type="button" @click="mobileNavOpen = true" aria-label="Buka menu"
                    class="md:hidden p-2 -ml-2 text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <div class="flex-1 min-w-0 flex items-center gap-2">
                <span class="hidden sm:inline text-[11px] font-semibold tracking-widest text-brand-700 uppercase">Aplikasi Kasir</span>
                <span class="hidden sm:inline text-gray-300">/</span>
                <div class="min-w-0 truncate text-sm text-gray-700">{{ $header ?? '' }}</div>
            </div>

            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 hover:opacity-80" title="Profil saya">
                <img src="{{ $authUser->avatar_url }}" alt="{{ $authUser->name }}" class="w-9 h-9 rounded-full object-cover">
                <span class="hidden sm:block leading-tight">
                    <span class="block text-sm font-semibold text-gray-900">{{ $authUser->name }}</span>
                    <span class="block text-xs text-brand-700">{{ ucfirst($authUser->roles->first()->name ?? '') }}</span>
                </span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="border border-gray-200 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Keluar</button>
            </form>
        </header>

        {{-- pb-24 di HP supaya isi halaman tidak tertutup navigasi bawah --}}
        <main class="p-4 md:p-6 pb-24 md:pb-6">
            @if (session('success'))
                <div class="mb-4 bg-success-50 text-success-600 px-4 py-3 rounded-xl text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 bg-danger-50 text-danger-600 px-4 py-3 rounded-xl text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Navigasi bawah (khusus HP) --}}
    <nav class="md:hidden fixed inset-x-0 bottom-0 z-40 h-16 bg-white border-t flex">
        @foreach($bottomItems as [$route, $icon, $label, $active])
            @php $isActive = request()->routeIs(...$active); @endphp
            <a href="{{ route($route) }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[11px]
               {{ $isActive ? 'text-brand-600 font-semibold' : 'text-gray-500' }}">
                <x-dynamic-component :component="'lucide-' . $icon" class="w-5 h-5" />
                {{ $label }}
            </a>
        @endforeach

        <button type="button" @click="mobileNavOpen = true"
                class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[11px] text-gray-500">
            <x-lucide-menu class="w-5 h-5" />
            Menu
        </button>
    </nav>
</div>

@stack('scripts')
</body>
</html>