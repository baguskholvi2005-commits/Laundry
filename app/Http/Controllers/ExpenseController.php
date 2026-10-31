<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\BusinessSetting;
use Carbon\Carbon;


class ExpenseController extends Controller
{
    public const CATEGORIES = [
        'detergen' => 'Detergen',
        'pewangi' => 'Pewangi',
        'plastik' => 'Plastik',
        'listrik' => 'Listrik',
        'air' => 'Air',
        'gas' => 'Gas',
        'perawatan_mesin' => 'Perawatan Mesin',
        'transportasi' => 'Transportasi',
        'gaji' => 'Gaji',
        'lainnya' => 'Lainnya',
    ];

    public function index(Request $request)
    {
        [$start, $end, $period] = $this->resolvePeriod($request);
        $category = array_key_exists((string) $request->get('category'), self::CATEGORIES) ? $request->get('category') : null;
        $q = is_string($request->get('q')) ? trim($request->get('q')) : '';

        $base = Expense::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($q !== '', fn ($query) => $query->where('description', 'like', "%{$q}%"));

        $total = (float) (clone $base)->sum('amount');
        $count = (clone $base)->count();

        // Rata-rata harian dihitung sampai hari ini kalau periodenya masih berjalan
        $lastDay = $end->gt(today()) ? today() : $end->copy();
        $days = max(1, (int) $start->copy()->startOfDay()->diffInDays($lastDay->copy()->startOfDay()) + 1);

        $byCategory = (clone $base)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(3)
            ->pluck('total', 'category')
            ->map(fn ($t, $c) => [
                'label' => self::CATEGORIES[$c] ?? ucwords(str_replace('_', ' ', (string) $c)),
                'total' => (float) $t,
                'share' => $total > 0 ? (int) round(($t / $total) * 100) : 0,
            ])
            ->values();

        // Plafon bulanan selalu dihitung untuk BULAN INI, tidak tergantung filter periode
        $budget = (float) setting('expense_budget', 0);
        $monthSpent = (float) Expense::whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount');
        $budgetPercent = $budget > 0 ? (int) round(($monthSpent / $budget) * 100) : null;
        $daysLeft = max(1, (int) today()->diffInDays(now()->endOfMonth()->startOfDay()) + 1);
        $remaining = $budget - $monthSpent;

        $expenses = (clone $base)->orderByDesc('date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'categories' => self::CATEGORIES,
            'total' => $total,
            'count' => $count,
            'dailyAvg' => $total / $days,
            'byCategory' => $byCategory,
            'period' => $period,
            'rangeText' => $start->translatedFormat('d M Y') . ' – ' . $end->translatedFormat('d M Y'),
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'category' => $category,
            'q' => $q,
            'budget' => $budget,
            'monthSpent' => $monthSpent,
            'budgetPercent' => $budgetPercent,
            'remaining' => $remaining,
            'perDayLeft' => $remaining > 0 ? $remaining / $daysLeft : 0,
            'daysLeft' => $daysLeft,
        ]);
    }

    public function budget(Request $request)
    {
        $data = $request->validate(['expense_budget' => 'required|numeric|min:0|max:100000000000']);

        $old = (float) setting('expense_budget', 0);
        BusinessSetting::updateOrCreate(['key' => 'expense_budget'], ['value' => (string) $data['expense_budget']]);

        if ($old !== (float) $data['expense_budget']) {
            ActivityLog::record('settings_changed', 'mengubah plafon pengeluaran bulanan dari Rp '
                . number_format($old, 0, ',', '.') . ' menjadi Rp ' . number_format((float) $data['expense_budget'], 0, ',', '.'));
        }

        return back()->with('success', 'Plafon pengeluaran bulanan disimpan.');
    }

    public function export(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request);
        $category = array_key_exists((string) $request->get('category'), self::CATEGORIES) ? $request->get('category') : null;

        $query = Expense::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('date')->orderBy('id');

        $safe = fn ($v) => ($v = (string) $v) !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v;

        return response()->streamDownload(function () use ($query, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $put = fn (array $row) => fputcsv($out, $row, ';', '"', '\\');

            $put(['Tanggal', 'Kategori', 'Keterangan', 'Nominal (Rp)']);

            foreach ($query->cursor() as $e) {
                $put([
                    $e->date->format('Y-m-d'),
                    self::CATEGORIES[$e->category] ?? $e->category,
                    $safe($e->description),
                    (int) round((float) $e->amount),
                ]);
            }

            fclose($out);
        }, 'pengeluaran-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function resolvePeriod(Request $request): array
    {
        $period = $request->get('period', 'month');

        if ($period === 'custom' && $request->filled(['start', 'end'])) {
            try {
                $start = Carbon::parse($request->get('start'))->startOfDay();
                $end = Carbon::parse($request->get('end'))->endOfDay();

                if ($end->lt($start)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                return [$start, $end, 'custom'];
            } catch (\Throwable $e) {
                // tanggal tidak valid, pakai bulan ini
            }
        }

        return match ($period) {
            'today' => [today(), now()->endOfDay(), 'today'],
            'week' => [now()->startOfWeek(), now()->endOfWeek(), 'week'],
            'year' => [now()->startOfYear(), now()->endOfYear(), 'year'],
            default => [now()->startOfMonth(), now()->endOfMonth(), 'month'],
        };
    }


    public function create()
    {
        return view('expenses.create', ['categories' => self::CATEGORIES]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'category' => 'required|in:' . implode(',', array_keys(self::CATEGORIES)),
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        $data['created_by'] = Auth::id();
        Expense::create($data);

        return redirect()->route('expenses.index')->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function edit(Expense $expense)
    {
        return view('expenses.edit', ['expense' => $expense, 'categories' => self::CATEGORIES]);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'category' => 'required|in:' . implode(',', array_keys(self::CATEGORIES)),
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Pengeluaran dihapus.');
    }
}