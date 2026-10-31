<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #E8F6F3; text-align: left; font-size: 9px; padding: 5px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .summary td { border: 1px solid #d5e3e0; padding: 8px; width: 25%; }
        .big { font-size: 13px; font-weight: bold; }
        .in { color: #2E7D32; font-weight: bold; }
        .out { color: #B42318; font-weight: bold; }
        .note { margin-top: 8px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    @php $rp = fn ($n) => 'Rp ' . number_format(abs($n), 0, ',', '.'); @endphp

    <h1>{{ setting('business_name', 'Laundry Desa') }}</h1>
    <div class="muted">Laporan Arus Kas &amp; Keuangan · {{ $periodLabel }} · {{ $rangeText }}</div>
    <div class="muted">Dicetak {{ now()->translatedFormat('d M Y, H:i') }} WIB</div>

    <h2>Ringkasan</h2>
    <table class="summary">
        <tr>
            <td><span class="muted">Total pemasukan</span><br><span class="big">{{ $rp($totalRevenue) }}</span></td>
            <td><span class="muted">Total beban operasional</span><br><span class="big">{{ $rp($totalExpense) }}</span></td>
            <td><span class="muted">Estimasi laba bersih</span><br><span class="big">{{ $netProfit < 0 ? '-' : '' }}{{ $rp($netProfit) }}</span>
                @if($margin !== null)<br><span class="muted">Margin {{ number_format($margin, 1, ',', '.') }}%</span>@endif</td>
            <td><span class="muted">Piutang (per hari ini)</span><br><span class="big">{{ $rp($receivableTotal) }}</span><br><span class="muted">{{ $receivableCount }} nota</span></td>
        </tr>
    </table>

    @if(count($distribution))
        <h2>Distribusi metode bayar</h2>
        <table>
            <tr><th>Metode</th><th class="right">Nominal</th><th class="right">Porsi</th></tr>
            @foreach($distribution as $d)
                <tr><td>{{ $d['label'] }}</td><td class="right">{{ $rp($d['amount']) }}</td><td class="right">{{ $d['percent'] }}%</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Mutasi buku kas ({{ $ledgerCount }})</h2>
    @if(empty($entries))
        <div class="muted">Belum ada mutasi kas pada periode ini.</div>
    @else
        <table>
            <tr>
                <th style="width:12%">Tanggal</th>
                <th style="width:15%">Referensi</th>
                <th style="width:14%">Kategori</th>
                <th>Keterangan</th>
                <th style="width:9%">Metode</th>
                <th class="right" style="width:14%">Nominal</th>
            </tr>
            @foreach($entries as $e)
                <tr>
                    <td>{{ $e['at']->format('d/m/Y') }}<br><span class="muted">{{ $e['at']->format('H:i') }}</span></td>
                    <td>{{ $e['ref'] }}</td>
                    <td>{{ $e['category'] }}</td>
                    <td>{{ $e['description'] }}</td>
                    <td>{{ $e['method'] }}</td>
                    <td class="right {{ $e['amount'] >= 0 ? 'in' : 'out' }}">{{ $e['amount'] >= 0 ? '+' : '-' }}{{ $rp($e['amount']) }}</td>
                </tr>
            @endforeach
        </table>
        <div class="note">
            Total masuk +{{ $rp($totalRevenue) }} · Total keluar -{{ $rp($totalExpense) }}.
            @if($truncated) Hanya {{ $limit }} mutasi terbaru yang dicetak. Gunakan Export CSV untuk seluruhnya. @endif
        </div>
    @endif
</body>
</html>