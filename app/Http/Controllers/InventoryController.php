<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    private const FILTERS = ['perlu_restock', 'habis', 'kritis', 'menipis', 'aman'];

    public function index(Request $request)
    {
        $status = in_array($request->get('status'), self::FILTERS, true) ? $request->get('status') : 'semua';
        $category = is_string($request->get('category')) ? trim($request->get('category')) : '';
        $q = is_string($request->get('q')) ? trim($request->get('q')) : '';

        $items = InventoryItem::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")->orWhere('category', 'like', "%{$q}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($status !== 'semua', fn ($query) => $query->withStockStatus($status))
            ->mostUrgentFirst()
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Perkiraan hari sampai habis, dari pemakaian ("keluar") 30 hari terakhir
        $usage = InventoryLog::where('type', 'keluar')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('inventory_item_id', $items->getCollection()->pluck('id'))
            ->selectRaw('inventory_item_id, SUM(qty) as total')
            ->groupBy('inventory_item_id')
            ->pluck('total', 'inventory_item_id')
            ->all();

        $forecast = [];
        foreach ($items as $item) {
            $used = (float) ($usage[$item->id] ?? 0);
            // Bahan baru diamati minimal 7 hari supaya satu hari pemakaian tidak membuat perkiraan terlalu optimis
            $observed = max(7, min(30, abs((int) ($item->created_at?->diffInDays(now()) ?? 30))));
            $daily = $used / $observed;

            $forecast[$item->id] = ($daily > 0 && $item->current_stock > 0) ? $item->current_stock / $daily : null;
        }

        // Kartu angka dan daftar belanja
        $needRestock = InventoryItem::withStockStatus('perlu_restock')->mostUrgentFirst()->orderBy('name')->get();

        $stats = [
            'total' => InventoryItem::count(),
            'categories' => InventoryItem::whereNotNull('category')->where('category', '!=', '')->distinct()->count('category'),
            'restock' => $needRestock->count(),
            'safe' => InventoryItem::withStockStatus('aman')->count(),
        ];

        // Disarankan membeli sampai stok mencapai 2 kali ambang minimal
        $shopping = $needRestock->map(function ($i) {
            $target = max(1.0, (float) $i->min_stock * 2);

            return [
                'name' => $i->name,
                'unit' => $i->unit,
                'qty' => max(1, (int) ceil($target - (float) $i->current_stock)),
            ];
        });

        $shoppingText = 'Daftar belanja ' . setting('business_name', 'Laundry Desa') . ' (' . now()->translatedFormat('d M Y') . "):\n"
            . $shopping->map(fn ($s) => '- ' . $s['name'] . ': ' . $s['qty'] . ' ' . $s['unit'])->join("\n");

        return view('inventory.index', [
            'items' => $items,
            'forecast' => $forecast,
            'stats' => $stats,
            'needRestock' => $needRestock,
            'shopping' => $shopping,
            'shoppingText' => $shoppingText,
            'categories' => InventoryItem::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'allItems' => InventoryItem::orderBy('name')->get(['id', 'name', 'unit', 'current_stock']),
            'status' => $status,
        ]);
    }

    public function logs()
    {
        $logs = InventoryLog::with(['item', 'creator'])->latest()->paginate(20);
        return view('inventory.logs', compact('logs'));
    }

    public function create()
    {
        return view('inventory.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'current_stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data) {
            $item = InventoryItem::create($data);

            // Stok awal tercatat di riwayat supaya pergerakan stok utuh
            if ((float) $item->current_stock > 0) {
                $item->logs()->create([
                    'type' => 'masuk',
                    'qty' => $item->current_stock,
                    'notes' => 'Stok awal',
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->route('inventory.index')->with('success', 'Item inventori ditambahkan.');
    }

    public function edit(InventoryItem $item)
    {
        return view('inventory.edit', compact('item'));
    }

    public function update(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'min_stock' => 'required|numeric|min:0',
        ]);

        $item->update($data);

        return redirect()->route('inventory.index')->with('success', 'Item inventori diperbarui.');
    }

    // Satu pintu untuk semua perubahan stok, aman terhadap pencatatan bersamaan
    public function move(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|integer|exists:inventory_items,id',
            'type' => 'required|in:masuk,keluar',
            'qty' => 'required|numeric|min:0.01|max:1000000',
            'notes' => 'nullable|string|max:255',
        ]);

        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
        $qty = round((float) $data['qty'], 2);

        $result = DB::transaction(function () use ($data, $qty, $fmt) {
            $item = InventoryItem::lockForUpdate()->findOrFail($data['item_id']);
            $before = (float) $item->current_stock;

            if ($data['type'] === 'keluar' && $qty > $before) {
                return ['error' => 'Jumlah keluar (' . $fmt($qty) . ' ' . $item->unit . ') melebihi stok ' . $item->name
                    . ' yang tersedia (' . $fmt($before) . ' ' . $item->unit . ').'];
            }

            $after = $data['type'] === 'masuk' ? $before + $qty : $before - $qty;

            $item->update(['current_stock' => round($after, 2)]);

            $item->logs()->create([
                'type' => $data['type'],
                'qty' => $qty,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            return ['ok' => 'Stok ' . $item->name . ($data['type'] === 'masuk' ? ' bertambah ' : ' berkurang ')
                . $fmt($qty) . ' ' . $item->unit . '. Sekarang ' . $fmt($after) . ' ' . $item->unit . '.'];
        });

        return isset($result['error'])
            ? back()->withInput()->with('error', $result['error'])
            : back()->with('success', $result['ok']);
    }

    // Route lama tetap berfungsi dan memakai jalur yang sama
    public function stockIn(Request $request, InventoryItem $item)
    {
        $request->merge(['item_id' => $item->id, 'type' => 'masuk']);

        return $this->move($request);
    }

    public function stockOut(Request $request, InventoryItem $item)
    {
        $request->merge(['item_id' => $item->id, 'type' => 'keluar']);

        return $this->move($request);
    }

    public function destroy(InventoryItem $item)
    {
        $item->delete();

        return redirect()->route('inventory.index')->with('success', 'Item inventori dihapus.');
    }
}