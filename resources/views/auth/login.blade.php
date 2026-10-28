<x-guest-layout>
@php
    $biz = setting('business_name', 'Laundry Desa');
    $logo = business_logo_url();
    $hours = operating_hours_text();
    $address = trim((string) setting('business_address', ''));
    $wa = preg_replace('/\D/', '', (string) setting('business_whatsapp', ''));
    $waShow = $wa !== '' ? (str_starts_with($wa, '62') ? '0' . substr($wa, 2) : $wa) : null;
@endphp

<style>
    .login-pattern {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'%3E%3Ccircle cx='36' cy='36' r='24' fill='none' stroke='%23ffffff' stroke-opacity='0.06' stroke-width='6'/%3E%3Ccircle cx='36' cy='36' r='9' fill='none' stroke='%23ffffff' stroke-opacity='0.06' stroke-width='4'/%3E%3C/svg%3E");
    }
</style>

<div class="min-h-screen flex items-center justify-center p-4 sm:p-8" x-data="{ show: false }">
    <div class="w-full max-w-5xl grid lg:grid-cols-2 rounded-3xl overflow-hidden shadow-xl bg-white">

        {{-- Panel kiri --}}
        <div class="hidden lg:flex flex-col justify-between bg-sidebar login-pattern p-10 text-white">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    @if($logo)
                        <img src="{{ $logo }}" alt="Logo" class="w-11 h-11 rounded-xl bg-white/10 object-contain p-1">
                    @else
                        <span class="w-11 h-11 rounded-xl bg-brand-600 flex items-center justify-center">
                            <x-lucide-shirt class="w-5 h-5" />
                        </span>
                    @endif
                    <div>
                        <p class="font-bold leading-tight">{{ $biz }}</p>
                        <p class="text-xs text-white/60">Sistem kasir dan manajemen laundry</p>
                    </div>
                </div>
                @if($hours)
                    <span class="text-xs bg-white/10 rounded-full px-3 py-1.5 text-right">{{ $hours }}</span>
                @endif
            </div>

            <div class="py-12">
                <h2 class="text-4xl font-bold leading-tight">Catat cucian, hitung harga, pantau status.</h2>
                <p class="mt-4 text-white/70 max-w-md">Berat, harga, nota, pembayaran, dan antar-jemput dalam satu layar.</p>

                <div class="grid grid-cols-2 gap-3 mt-8 max-w-md">
                    <div class="rounded-xl bg-white/10 p-4">
                        <p class="text-xs text-white/60">Antar-Jemput</p>
                        <p class="font-semibold mt-0.5">{{ setting('pickup_enabled') ? 'Tersedia' : 'Tidak tersedia' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 p-4">
                        @if($address !== '')
                            <p class="text-xs text-white/60">Alamat</p>
                            <p class="font-semibold mt-0.5 text-sm leading-snug">{{ $address }}</p>
                        @else
                            <p class="text-xs text-white/60">Estimasi pengerjaan</p>
                            <p class="font-semibold mt-0.5">{{ (int) setting('work_duration_days', 2) }} hari</p>
                        @endif
                    </div>
                </div>
            </div>

            <p class="text-xs text-white/50">&copy; {{ date('Y') }} {{ $biz }}</p>
        </div>

        {{-- Panel kanan --}}
        <div class="flex items-center justify-center bg-gray-50 p-6 sm:p-10">
            <div class="w-full max-w-sm">
                <div class="text-center mb-6">
                    @if($logo)
                        <img src="{{ $logo }}" alt="Logo" class="w-14 h-14 mx-auto rounded-2xl object-contain bg-brand-50 p-1.5">
                    @else
                        <span class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center">
                            <x-lucide-shirt class="w-7 h-7" />
                        </span>
                    @endif
                    <h1 class="text-2xl font-bold text-gray-900 mt-4">Masuk ke Akun</h1>
                    <p class="text-sm text-gray-500 mt-1">Masukkan email dan kata sandi untuk membuka kasir.</p>
                </div>

                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="text-sm font-semibold text-gray-700">Email</label>
                        <div class="relative mt-1.5">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </span>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                                   autocomplete="username" placeholder="nama@email.com"
                                   class="w-full pl-11 border-gray-200 rounded-xl bg-white focus:ring-brand-500 focus:border-brand-500">
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="password" class="text-sm font-semibold text-gray-700">Kata Sandi</label>
                        <div class="relative mt-1.5">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </span>
                            <input :type="show ? 'text' : 'password'" id="password" name="password" required
                                   autocomplete="current-password" placeholder="Masukkan kata sandi"
                                   class="w-full pl-11 pr-11 border-gray-200 rounded-xl bg-white focus:ring-brand-500 focus:border-brand-500">
                            <button type="button" @click="show = !show" aria-label="Tampilkan atau sembunyikan kata sandi"
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-gray-600">Ingat saya di perangkat ini</span>
                    </label>

                    <button type="submit"
                            class="w-full bg-brand-600 text-white rounded-xl py-3 font-semibold hover:bg-brand-700 transition">
                        Masuk ke Akun
                    </button>
                </form>

                <div class="flex items-center gap-3 my-5 text-[11px] font-semibold tracking-widest text-gray-400">
                    <span class="flex-1 h-px bg-gray-200"></span>AKSES TERKELOLA<span class="flex-1 h-px bg-gray-200"></span>
                </div>

                <div class="rounded-xl bg-brand-50 text-center px-4 py-3 text-sm">
                    <p class="text-gray-700">Belum punya akun? Hubungi pemilik.</p>
                    @if($waShow)
                        <a href="https://wa.me/{{ $wa }}" target="_blank" class="font-semibold text-brand-700">WhatsApp {{ $waShow }}</a>
                    @endif
                </div>

                <p class="text-center text-sm text-gray-500 mt-4">
                    Pelanggan?
                    <a href="{{ route('portal.login') }}" class="text-brand-600 font-semibold">Masuk ke akun pelanggan</a>
                </p>
            </div>
        </div>
    </div>
</div>
</x-guest-layout>