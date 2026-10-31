@if(empty($entries))
    <x-empty-state title="Belum ada mutasi kas pada periode ini" description="Pembayaran yang masuk dan pengeluaran yang dicatat akan muncul di sini." />
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[780px]">
            <thead class="bg-brand-50 text-gray-600 text-left">
                <tr>
                    <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Tanggal &amp; waktu</th>
                    <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">No. referensi</th>
                    <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Kategori</th>
                    <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Keterangan</th>
                    <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase">Metode</th>
                    <th class="px-4 py-3 text-[11px] font-semibold tracking-widest uppercase text-right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entries as $e)
                <tr class="border-t border-gray-100 align-top hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap text-gray-800">
                        {{ $e['at']->translatedFormat('d M Y') }}, <span class="text-gray-500">{{ $e['at']->format('H:i') }}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ $e['url'] }}" class="font-mono text-xs font-semibold text-brand-700 hover:underline">{{ $e['ref'] }}</a>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block text-xs font-medium rounded-md px-2.5 py-1 whitespace-nowrap {{ $e['kind'] === 'in' ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-700' }}">{{ $e['category'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $e['description'] }}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $e['method'] }}</td>
                    <td class="px-4 py-3 text-right font-semibold tabular-nums whitespace-nowrap {{ $e['amount'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                        {{ $e['amount'] >= 0 ? '+' : '-' }}Rp {{ number_format(abs($e['amount']), 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif