<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Tarif</title>
    <style>
        @page { margin: 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 22px; margin: 0; color: #0C5F59; }
        h2 { font-size: 14px; margin: 22px 0 6px; color: #0C5F59; border-bottom: 2px solid #0F766E; padding-bottom: 4px; }
        .muted { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { font-size: 10px; color: #6b7280; text-transform: uppercase; }
        .right { text-align: right; }
        .price { font-weight: bold; font-size: 13px; text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    @php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp

    <h1>{{ setting('business_name', 'Laundry Desa') }}</h1>
    @if(setting('business_tagline'))<div class="muted">{{ setting('business_tagline') }}</div>@endif
    <div class="muted">
        {{ setting('business_address') }}
        @if(setting('business_whatsapp')) · WA 0{{ substr(setting('business_whatsapp'), 2) }} @endif
    </div>
    @if($hours = operating_hours_text())<div class="muted">Jam buka: {{ $hours }}</div>@endif

    <h2>Daftar Tarif Layanan</h2>

    @if($kiloan->isNotEmpty())
        <table>
            <tr><th>Layanan per kilo</th><th>Estimasi</th><th class="right">Tarif</th></tr>
            @foreach($kiloan as $s)
                <tr><td>{{ $s->name }}</td><td>{{ $s->duration_days ?: $defaultDays }} hari</td><td class="price">{{ $rp($s->price) }} / kg</td></tr>
            @endforeach
        </table>
    @endif

    @if($satuan->isNotEmpty())
        <table style="margin-top: 14px;">
            <tr><th>Layanan per potong</th><th>Estimasi</th><th class="right">Tarif</th></tr>
            @foreach($satuan as $s)
                <tr><td>{{ $s->name }}</td><td>{{ $s->duration_days ?: $defaultDays }} hari</td><td class="price">{{ $rp($s->price) }} / pcs</td></tr>
            @endforeach
        </table>
    @endif

    <p class="muted" style="margin-top: 18px;">Harga dapat berubah sewaktu-waktu. Berat final mengikuti timbangan di toko. Berlaku per {{ now()->translatedFormat('d F Y') }}.</p>
</body>
</html>