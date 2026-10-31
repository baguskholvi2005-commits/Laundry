<?php

namespace App\Http\Controllers;

use App\Models\PickupRequestItem;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->get('tab'), ['kiloan', 'satuan', 'nonaktif'], true) ? $request->get('tab') : 'semua';

        $services = Service::query()
            ->when($tab === 'kiloan', fn ($q) => $q->where('type', 'kiloan')->where('is_active', true))
            ->when($tab === 'satuan', fn ($q) => $q->where('type', 'satuan')->where('is_active', true))
            ->when($tab === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($tab === 'semua', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $counts = [
            'semua' => Service::where('is_active', true)->count(),
            'kiloan' => Service::where('is_active', true)->where('type', 'kiloan')->count(),
            'satuan' => Service::where('is_active', true)->where('type', 'satuan')->count(),
            'nonaktif' => Service::where('is_active', false)->count(),
        ];

        // Layanan terlaris bulan ini: jumlah nota yang memuatnya dibanding seluruh nota bulan ini
        $monthStart = today()->startOfMonth();
        $totalNotes = Transaction::notCancelled()->where('created_at', '>=', $monthStart)->count();

        $top = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', '!=', 'dibatalkan')
            ->where('transactions.created_at', '>=', $monthStart)
            ->whereNotNull('transaction_items.service_id')
            ->whereNull('transaction_items.package_id')
            ->groupBy('transaction_items.service_id')
            ->selectRaw('transaction_items.service_id, COUNT(DISTINCT transaction_items.transaction_id) as notes')
            ->orderByDesc('notes')
            ->first();

        $topService = $top ? Service::find($top->service_id) : null;
        $topShare = ($top && $totalNotes > 0) ? (int) round(($top->notes / $totalNotes) * 100) : 0;

        $default = (int) setting('work_duration_days', 2);
        $active = Service::where('is_active', true)->get(['duration_days']);
        $avgDuration = $active->isNotEmpty() ? $active->avg(fn ($s) => $s->duration_days ?: $default) : null;

        return view('services.index', [
            'services' => $services,
            'tab' => $tab,
            'counts' => $counts,
            'topService' => $topService,
            'topShare' => $topShare,
            'avgDuration' => $avgDuration,
            'defaultDays' => $default,
        ]);
    }

    // Daftar tarif yang bisa dicetak dan ditempel di toko
    public function tariff()
    {
        $services = Service::where('is_active', true)->orderBy('type')->orderBy('name')->get();

        return Pdf::loadView('services.tariff', [
            'kiloan' => $services->where('type', 'kiloan'),
            'satuan' => $services->where('type', 'satuan'),
            'defaultDays' => (int) setting('work_duration_days', 2),
        ])->setPaper('a4', 'portrait')->stream('daftar-tarif.pdf');
    }

    public function create()
    {
        return view('services.create');
    }

    public function store(Request $request)
    {
        Service::create($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Layanan ditambahkan.');
    }

    public function edit(Service $service)
    {
        return view('services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Layanan diperbarui.');
    }

    public function toggleActive(Service $service)
    {
        $service->update(['is_active' => ! $service->is_active]);

        return back()->with('success', 'Status layanan diperbarui.');
    }

    public function destroy(Service $service)
    {
        // Layanan yang pernah dipakai disimpan sebagai riwayat, tidak boleh dihapus
        $used = TransactionItem::where('service_id', $service->id)->exists()
            || ServicePackage::where('service_id', $service->id)->exists()
            || PickupRequestItem::where('service_id', $service->id)->exists();

        if ($used) {
            return back()->with('error', 'Layanan yang sudah pernah dipakai (transaksi, paket, atau permintaan jemput) tidak bisa dihapus. Nonaktifkan saja supaya riwayatnya tetap utuh.');
        }

        $service->delete();

        return redirect()->route('services.index')->with('success', 'Layanan dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:kiloan,satuan',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'nullable|integer|min:1|max:60',
        ]);
    }
}