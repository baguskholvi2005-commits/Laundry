@php $logo = business_logo_url(); @endphp

<div class="px-5 pt-5 pb-4 flex items-center gap-3">
    @if($logo)
        <img src="{{ $logo }}" alt="Logo" class="w-10 h-10 rounded-xl bg-white/10 object-contain p-1">
    @else
        <span class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center text-white">
            <x-lucide-shirt class="w-5 h-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="font-bold text-white leading-tight truncate">{{ setting('business_name', 'Laundry Desa') }}</p>
        <p class="text-[11px] text-white/60">Sistem kasir dan manajemen</p>
    </div>
</div>

@hasanyrole('pemilik|kasir')
    <a href="{{ route('transactions.create') }}"
       class="mx-4 mb-4 bg-gold-400 text-gray-900 text-sm font-semibold py-3 rounded-xl text-center hover:bg-gold-300 transition">
        + Transaksi Baru
    </a>
@endhasanyrole

<nav class="flex-1 px-3 space-y-1 overflow-y-auto pb-6">
    @role('pemilik')
        @include('layouts.partials.nav-item', ['route' => 'dashboard', 'icon' => 'layout-grid', 'label' => 'Dashboard'])
    @endrole

    @hasanyrole('pemilik|kasir')
        @include('layouts.partials.nav-item', ['route' => 'transactions.index', 'icon' => 'file-text', 'label' => 'Transaksi', 'active' => ['transactions.index', 'transactions.show', 'transactions.processing', 'transactions.ready', 'transactions.history']])
        @include('layouts.partials.nav-item', ['route' => 'customers.index', 'icon' => 'users', 'label' => 'Pelanggan', 'active' => 'customers.*'])
        @include('layouts.partials.nav-item', ['route' => 'services.index', 'icon' => 'shirt', 'label' => 'Layanan', 'active' => 'services.*'])
        @php $pendingPickupRequests = \App\Models\PickupRequest::where('status', 'menunggu')->count(); @endphp
        @include('layouts.partials.nav-item', ['route' => 'pickups.index', 'icon' => 'truck', 'label' => 'Antar-Jemput', 'active' => ['pickups.*', 'pickup-requests.*'], 'navBadge' => $pendingPickupRequests])
    @endhasanyrole

    @hasanyrole('pemilik|kasir|pegawai')
        @include('layouts.partials.nav-item', ['route' => 'process.queue', 'icon' => 'clipboard-list', 'label' => 'Antrian Proses'])
    @endhasanyrole

    @role('pemilik')
        @include('layouts.partials.nav-item', ['route' => 'promos.index', 'icon' => 'tag', 'label' => 'Promo', 'active' => 'promos.*'])
        @include('layouts.partials.nav-item', ['route' => 'packages.index', 'icon' => 'package', 'label' => 'Paket Laundry', 'active' => 'packages.*'])
        @include('layouts.partials.nav-item', ['route' => 'expenses.index', 'icon' => 'receipt', 'label' => 'Pengeluaran', 'active' => 'expenses.*'])
        @include('layouts.partials.nav-item', ['route' => 'inventory.index', 'icon' => 'box', 'label' => 'Inventori', 'active' => 'inventory.*'])
        @include('layouts.partials.nav-item', ['route' => 'reports.index', 'icon' => 'bar-chart-3', 'label' => 'Laporan', 'active' => 'reports.*'])
        @include('layouts.partials.nav-item', ['route' => 'users.index', 'icon' => 'user', 'label' => 'Pengguna', 'active' => 'users.*'])
        @include('layouts.partials.nav-item', ['route' => 'activity-logs.index', 'icon' => 'history', 'label' => 'Log Aktivitas', 'active' => 'activity-logs.*'])
        @include('layouts.partials.nav-item', ['route' => 'backups.index', 'icon' => 'database', 'label' => 'Cadangan', 'active' => 'backups.*'])
        @include('layouts.partials.nav-item', ['route' => 'settings.index', 'icon' => 'settings', 'label' => 'Pengaturan', 'active' => 'settings.*'])
    @endrole
</nav>

<p class="px-5 py-4 text-[11px] text-white/40 border-t border-white/10">{{ setting('business_name', 'Laundry Desa') }}</p>