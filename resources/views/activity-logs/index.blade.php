<x-layouts.app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold">Log Aktivitas</h1></x-slot>

    @php
        $hasFilter = request()->filled('group') || request()->filled('user') || request()->filled('date_from')
            || request()->filled('date_to') || request()->filled('q');
    @endphp

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Log Aktivitas</h1>
        <p class="text-gray-500">Siapa melakukan apa dan kapan. Berguna untuk memeriksa pembatalan, perubahan harga, dan penghapusan data.</p>
    </div>

    <form method="GET" class="bg-white rounded-2xl border border-gray-100 p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari kegiatan..."
                   class="md:col-span-2 border-gray-200 rounded-xl text-sm">

            <select name="group" class="border-gray-200 rounded-xl text-sm">
                <option value="">Semua jenis</option>
                @foreach($groups as $key => [$label])
                    <option value="{{ $key }}" {{ request('group') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select name="user" class="border-gray-200 rounded-xl text-sm">
                <option value="">Semua pelaku</option>
                <option value="sistem" {{ request('user') === 'sistem' ? 'selected' : '' }}>Sistem / pelanggan</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ (string) request('user') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>

            <input type="date" name="date_from" value="{{ request('date_from') }}" title="Dari tanggal" class="border-gray-200 rounded-xl text-sm">
            <input type="date" name="date_to" value="{{ request('date_to') }}" title="Sampai tanggal" class="border-gray-200 rounded-xl text-sm">
        </div>
        <div class="flex items-center gap-3 mt-3">
            <button class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Terapkan</button>
            @if($hasFilter)
                <a href="{{ route('activity-logs.index') }}" class="text-sm text-gray-500">Hapus filter</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        @if($logs->isEmpty())
            <x-empty-state :title="$hasFilter ? 'Tidak ada aktivitas yang cocok' : 'Belum ada aktivitas tercatat'"
                           :description="$hasFilter ? 'Coba ubah atau hapus filter.' : 'Aktivitas mulai tercatat setelah pembaruan ini dipasang.'" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">Waktu</th>
                            <th class="px-4 py-3 font-medium">Pelaku</th>
                            <th class="px-4 py-3 font-medium">Kegiatan</th>
                            <th class="px-4 py-3 font-medium">Jenis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        @php
                            $role = $log->user?->roles->first()?->name;
                            $risky = \Illuminate\Support\Str::contains($log->description, ['membatalkan', 'menghapus', 'mencabut', 'menolak', 'melepas']);
                        @endphp
                        <tr class="border-t align-top hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="text-gray-800">{{ $log->created_at->translatedFormat('d M Y, H:i') }}</p>
                                <p class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($log->user)
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $log->user->avatar_url }}" alt="" class="w-6 h-6 rounded-full object-cover">
                                        <div>
                                            <p class="font-medium text-gray-800">{{ $log->user->name }}</p>
                                            <p class="text-xs text-gray-400">{{ ucfirst($role ?? '-') }}</p>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-400">Sistem / pelanggan</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $log->description }}
                                @if($log->transaction_id)
                                    <a href="{{ route('transactions.show', $log->transaction_id) }}" class="text-brand-600 ml-1">Lihat</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2.5 py-1 rounded-full whitespace-nowrap {{ $risky ? 'bg-danger-50 text-danger-600' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $labelByEvent[$log->event] ?? $log->event }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4"><x-pagination :paginator="$logs" /></div>
        @endif
    </div>
</x-layouts.app-layout>