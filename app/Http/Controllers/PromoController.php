<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->get('status'), Promo::STATUSES, true) ? $request->get('status') : 'semua';

        $counts = ['semua' => Promo::count()];
        foreach (Promo::STATUSES as $s) {
            $counts[$s] = Promo::withStatus($s)->count();
        }

        $promos = Promo::query()
            ->when($status !== 'semua', fn ($q) => $q->withStatus($status))
            ->orderByStatus()
            ->orderBy('end_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('promos.index', compact('promos', 'status', 'counts'));
    }

    // Alamat lama tetap berfungsi dan diarahkan ke tab yang sesuai
    public function active()
    {
        return redirect()->route('promos.index', ['status' => 'aktif']);
    }

    public function history()
    {
        return redirect()->route('promos.index', ['status' => 'kadaluarsa']);
    }

    public function create()
    {
        return view('promos.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:gratis_ongkir,diskon_persen,diskon_nominal,berdasarkan_berat,minimal_transaksi,member',
            'value' => 'nullable|numeric|min:0',
            'value_type' => 'nullable|in:persen,nominal',
            'min_transaction' => 'nullable|numeric|min:0',
            'min_weight' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'quota' => 'nullable|integer|min:1',
            'stackable' => 'boolean',
        ]);

        Promo::create($this->normalizeValue($data));

        return redirect()->route('promos.index')->with('success', 'Promo berhasil dibuat.');
    }

    public function show(Promo $promo)
    {
        $usages = $promo->usages()->with('transaction.customer')->latest()->paginate(10);
        $totalDiscount = (float) $promo->usages()->sum('discount_amount');

        return view('promos.show', compact('promo', 'usages', 'totalDiscount'));
    }

    public function edit(Promo $promo)
    {
        return view('promos.edit', compact('promo'));
    }

    public function update(Request $request, Promo $promo)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'value' => 'nullable|numeric|min:0',
            'value_type' => 'nullable|in:persen,nominal',
            'min_transaction' => 'nullable|numeric|min:0',
            'min_weight' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'quota' => 'nullable|integer|min:1',
            'stackable' => 'boolean',
        ]);

        $data['type'] = $promo->type;
        $promo->update($this->normalizeValue($data));

        return redirect()->route('promos.index')->with('success', 'Promo diperbarui.');
    }

    public function toggleActive(Promo $promo)
    {
        $promo->update(['is_active' => ! $promo->is_active]);

        return back()->with('success', 'Status promo diperbarui.');
    }

    // Menambah kuota promo yang habis. Atomik, jadi aman kalau dua orang menekan bersamaan
    public function addQuota(Request $request, Promo $promo)
    {
        $data = $request->validate(['add' => 'required|integer|min:1|max:100000']);

        $changed = Promo::whereKey($promo->id)->whereNotNull('quota')->increment('quota', $data['add']);

        if ($changed === 0) {
            return back()->with('error', 'Promo ini tanpa batas kuota, tidak perlu ditambah.');
        }

        ActivityLog::record('promo_changed', 'menambah kuota promo ' . $promo->name . ' sebanyak ' . $data['add']);

        return back()->with('success', 'Kuota ditambah ' . $data['add'] . '.');
    }

    // Salinan dibuat nonaktif dengan tanggal baru, lalu dibuka di halaman edit
    public function duplicate(Promo $promo)
    {
        $copy = Promo::create([
            'name' => Str::limit($promo->name, 235, '') . ' (salinan)',
            'description' => $promo->description,
            'type' => $promo->type,
            'value' => $promo->value,
            'value_type' => $promo->value_type,
            'min_transaction' => $promo->min_transaction,
            'min_weight' => $promo->min_weight,
            'start_date' => today(),
            'end_date' => today()->addDays(29),
            'start_time' => $promo->start_time,
            'end_time' => $promo->end_time,
            'quota' => $promo->quota,
            'used_count' => 0,
            'is_active' => false,
            'stackable' => $promo->stackable,
        ]);

        ActivityLog::record('promo_changed', 'menduplikasi promo ' . $promo->name);

        return redirect()->route('promos.edit', $copy)
            ->with('success', 'Salinan dibuat dalam keadaan nonaktif. Atur nilai dan tanggalnya, lalu aktifkan dari daftar promo.');
    }

    public function destroy(Promo $promo)
    {
        // Promo yang sudah dipakai disimpan sebagai riwayat, tidak boleh dihapus
        if ($promo->used_count > 0 || $promo->usages()->exists()) {
            return back()->with('error', 'Promo yang sudah pernah dipakai tidak bisa dihapus. Nonaktifkan saja supaya riwayatnya tetap tersimpan.');
        }

        $promo->delete();

        return redirect()->route('promos.index')->with('success', 'Promo dihapus.');
    }

    private function normalizeValue(array $data): array
    {
        $type = $data['type'];

        // Diskon Persen selalu persen, Diskon Nominal selalu rupiah. Tiga jenis lain boleh dipilih
        $data['value_type'] = match ($type) {
            'diskon_persen' => 'persen',
            'diskon_nominal', 'gratis_ongkir' => 'nominal',
            default => $data['value_type'] ?? 'nominal',
        };

        if ($type === 'gratis_ongkir') {
            $data['value'] = null;
            return $data;
        }

        $value = (float) ($data['value'] ?? 0);

        if ($value <= 0) {
            throw ValidationException::withMessages(['value' => 'Nilai promo wajib diisi dan harus lebih dari 0.']);
        }

        if ($data['value_type'] === 'persen' && $value > 100) {
            throw ValidationException::withMessages(['value' => 'Diskon persen tidak boleh lebih dari 100.']);
        }

        return $data;
    }
}