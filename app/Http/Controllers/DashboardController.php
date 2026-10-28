<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const PROCESS_STATUSES = ['diproses', 'dicuci', 'dikeringkan', 'disetrika'];

    public function index(Request $request)
    {
        $user = $request->user();

        // Dashboard berisi omzet dan laba, hanya untuk pemilik. Role lain diarahkan ke halaman kerjanya
        if (! $user->hasRole('pemilik')) {
            if ($user->hasRole('kasir')) {
                return redirect()->route('transactions.create');
            }
            if ($user->hasRole('pegawai')) {
                return redirect()->route('process.queue');
            }
            abort(403);
        }

        $today = today();
        $now = now();

        // ---------- Pendapatan hari ini, dibandingkan kemarin pada jam yang sama ----------
        $revenueToday = (float) Payment::valid()->whereDate('created_at', $today)->sum('amount');

        $revenueYesterdaySameTime = (float) Payment::valid()
            ->whereBetween('created_at', [$today->copy()->subDay()->startOfDay(), $now->copy()->subDay()])
            ->sum('amount');

        $revenueBadge = null;
        $revenueUp = true;
        $yesterdayNote = null;

        if ($revenueYesterdaySameTime > 0) {
            $change = (int) round((($revenueToday - $revenueYesterdaySameTime) / $revenueYesterdaySameTime) * 100);
            $revenueUp = $change >= 0;
            $revenueBadge = ($change >= 0 ? '+' : '') . $change . '% vs kemarin';
            $yesterdayNote = 'Kemarin tercatat Rp ' . number_format($revenueYesterdaySameTime, 0, ',', '.')
                . ' pada jam yang sama (pukul ' . $now->format('H:i') . ' WIB)';
        }

        // ---------- Pengeluaran dan laba ----------
        $expensesToday = Expense::whereDate('date', $today);
        $expenseToday = (float) (clone $expensesToday)->sum('amount');
        $expenseCount = (clone $expensesToday)->count();
        $lastExpense = (clone $expensesToday)->latest('id')->first();
        $lastExpenseLabel = $lastExpense
            ? ($lastExpense->description ?: (ExpenseController::CATEGORIES[$lastExpense->category] ?? $lastExpense->category))
            : null;

        $profitToday = $revenueToday - $expenseToday;
        $margin = $revenueToday > 0 ? round(($profitToday / $revenueToday) * 100, 1) : null;

        // ---------- Nota hari ini ----------
        $todayTrx = Transaction::notCancelled()->whereDate('created_at', $today);
        $transactionsToday = (clone $todayTrx)->count();

        $kgToday = TransactionItem::whereIn('transaction_id', (clone $todayTrx)->select('id'))
            ->where('unit_type', 'kg')
            ->selectRaw('COUNT(DISTINCT transaction_id) as trx, COALESCE(SUM(qty), 0) as kg')
            ->first();

        $kiloanCount = (int) ($kgToday->trx ?? 0);
        $satuanCount = max(0, $transactionsToday - $kiloanCount);
        $totalKgToday = (float) ($kgToday->kg ?? 0);
        $avgKg = $kiloanCount > 0 ? $totalKgToday / $kiloanCount : 0;

        // ---------- Antrean ----------
        $processCounts = Transaction::whereIn('status', self::PROCESS_STATUSES)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $inProcess = (int) $processCounts->sum();
        $inProcessKg = (float) TransactionItem::where('unit_type', 'kg')
            ->whereIn('transaction_id', Transaction::whereIn('status', self::PROCESS_STATUSES)->select('id'))
            ->sum('qty');

        $readyToPickup = Transaction::where('status', 'siap_diambil')->count();
        $readyKg = (float) TransactionItem::where('unit_type', 'kg')
            ->whereIn('transaction_id', Transaction::where('status', 'siap_diambil')->select('id'))
            ->sum('qty');

        $unpaidQuery = Transaction::notCancelled()->whereColumn('paid_amount', '<', 'total');
        $unpaid = (clone $unpaidQuery)->count();
        $unpaidAmount = (float) (clone $unpaidQuery)->sum(DB::raw('total - paid_amount'));

        // ---------- Tren pendapatan bersih sesuai periode terpilih ----------
        ['start' => $start, 'end' => $end, 'period' => $period, 'clamped' => $clamped] = $this->resolvePeriod($request);

        $dates = [];
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $day) {
            $dates[] = $day->toDateString();
        }

        $revenueByDay = Payment::valid()
            ->selectRaw('DATE(created_at) as d, SUM(amount) as t')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('d')
            ->pluck('t', 'd');

        $expenseByDay = Expense::selectRaw('`date` as d, SUM(amount) as t')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('d')
            ->pluck('t', 'd');

        $short = count($dates) > 31;
        $labels = collect($dates)->map(fn ($d) => Carbon::parse($d)->translatedFormat($short ? 'd/m' : 'd M'))->all();
        $revenueSeries = collect($dates)->map(fn ($d) => (float) ($revenueByDay[$d] ?? 0))->all();
        $expenseSeries = collect($dates)->map(fn ($d) => (float) ($expenseByDay[$d] ?? 0))->all();
        $netSeries = array_map(fn ($r, $e) => $r - $e, $revenueSeries, $expenseSeries);

        $periodNet = array_sum($netSeries);
        $periodAvg = count($dates) > 0 ? $periodNet / count($dates) : 0;

        $periodLabel = match ($period) {
            '30d' => '30 Hari Terakhir',
            'month' => 'Bulan Ini',
            'custom' => $start->translatedFormat('d M Y') . ' – ' . $end->translatedFormat('d M Y'),
            default => '7 Hari Terakhir',
        };

        // ---------- Volume nota minggu ini (Senin sampai Minggu) ----------
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $weekCounts = Transaction::notCancelled()
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->groupBy('d')
            ->pluck('c', 'd');

        $weekDays = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
        $weekLabels = $weekDays->map(fn ($d) => $d->translatedFormat('D'))->all();
        $weekSeries = $weekDays->map(fn ($d) => (int) ($weekCounts[$d->toDateString()] ?? 0))->all();
        $weekTotal = array_sum($weekSeries);

        $peak = max($weekSeries);
        $peakIndex = $peak > 0 ? array_search($peak, $weekSeries, true) : null;
        $todayIndex = (int) $today->isoWeekday() - 1;
        $peakDayName = $peakIndex !== null && $peakIndex !== false ? $weekDays[$peakIndex]->translatedFormat('l') : null;

        $weekColors = collect($weekSeries)->map(function ($v, $i) use ($todayIndex, $peakIndex) {
            if ($i === $todayIndex) return '#F2C94C';
            if ($peakIndex !== null && $i === $peakIndex) return '#0F766E';
            return '#2F8F86';
        })->all();

        $weekKg = (float) TransactionItem::where('unit_type', 'kg')
            ->whereIn('transaction_id', Transaction::notCancelled()->whereBetween('created_at', [$weekStart, $weekEnd])->select('id'))
            ->sum('qty');

        // ---------- Layanan terlaris bulan ini ----------
        $serviceRows = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->leftJoin('services', 'services.id', '=', 'transaction_items.service_id')
            ->where('transactions.status', '!=', 'dibatalkan')
            ->where('transactions.created_at', '>=', $today->copy()->startOfMonth())
            ->groupBy('transaction_items.service_name', 'transaction_items.unit_type')
            ->selectRaw('transaction_items.service_name as name, transaction_items.unit_type as unit,
                COUNT(DISTINCT transaction_items.transaction_id) as trx_count,
                SUM(transaction_items.qty) as total_qty,
                MAX(transaction_items.price_per_unit) as used_price,
                MAX(services.price) as current_price')
            ->get();

        $totalLines = max(1, (int) $serviceRows->sum('trx_count'));

        $topServices = $serviceRows->sortByDesc('trx_count')->take(4)->values()->map(function ($r) use ($totalLines) {
            $price = ($r->unit === 'paket' || $r->current_price === null) ? $r->used_price : $r->current_price;

            return [
                'name' => $r->name,
                'unit' => $r->unit,
                'price' => (float) $price,
                'trx' => (int) $r->trx_count,
                'qty' => (float) $r->total_qty,
                'share' => (int) round(($r->trx_count / $totalLines) * 100),
            ];
        })->all();

        $activities = ActivityLog::with('user.roles')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'revenueToday', 'revenueBadge', 'revenueUp', 'yesterdayNote',
            'expenseToday', 'expenseCount', 'lastExpenseLabel', 'profitToday', 'margin',
            'transactionsToday', 'kiloanCount', 'satuanCount', 'totalKgToday', 'avgKg',
            'processCounts', 'inProcess', 'inProcessKg', 'readyToPickup', 'readyKg', 'unpaid', 'unpaidAmount',
            'period', 'periodLabel', 'clamped', 'labels', 'revenueSeries', 'expenseSeries', 'netSeries',
            'periodNet', 'periodAvg',
            'weekLabels', 'weekSeries', 'weekColors', 'weekTotal', 'weekKg', 'peakDayName',
            'topServices', 'activities'
        ) + [
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ]);
    }

    private function resolvePeriod(Request $request): array
    {
        $period = $request->get('period', '7d');

        if ($period === 'custom' && $request->filled(['start', 'end'])) {
            try {
                $start = Carbon::parse($request->get('start'))->startOfDay();
                $end = Carbon::parse($request->get('end'))->endOfDay();

                if ($end->lt($start)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                // Grafik dibatasi 92 hari supaya tetap terbaca
                $clamped = false;
                if ($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) > 91) {
                    $start = $end->copy()->subDays(91)->startOfDay();
                    $clamped = true;
                }

                return ['start' => $start, 'end' => $end, 'period' => 'custom', 'clamped' => $clamped];
            } catch (\Throwable $e) {
                // Tanggal tidak valid, jatuh ke 7 hari
            }
        }

        return match ($period) {
            '30d' => ['start' => today()->subDays(29), 'end' => now()->endOfDay(), 'period' => '30d', 'clamped' => false],
            'month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfDay(), 'period' => 'month', 'clamped' => false],
            default => ['start' => today()->subDays(6), 'end' => now()->endOfDay(), 'period' => '7d', 'clamped' => false],
        };
    }
}