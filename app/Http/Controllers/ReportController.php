<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const METHOD_COLORS = [
        'cash' => '#0F766E',
        'qris' => '#34A394',
        'transfer' => '#F2C94C',
        'ewallet' => '#A4DBD1',
    ];

    public function index(Request $request)
    {
        [$start, $end, $period] = $this->resolvePeriod($request->get('period', 'month'), $request);
        [$periodLabel, $rangeText] = $this->labels($period, $start, $end);

        $summary = $this->summary($start, $end);

        // Perbandingan dengan periode sebelumnya, sampai titik waktu yang sama
        [$prevStart, $prevEnd, $compareLabel] = $this->previousRange($period, $start, $end);
        $prevRevenue = (float) Payment::valid()->whereBetween('created_at', [$prevStart, $prevEnd])->sum('amount');
        $revenueChange = $prevRevenue > 0
            ? round((($summary['totalRevenue'] - $prevRevenue) / $prevRevenue) * 100, 1)
            : null;

        // Tiga kategori pengeluaran terbesar
        $categories = ExpenseController::CATEGORIES;
        $topCategoryLabels = Expense::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(3)
            ->pluck('total', 'category')
            ->keys()
            ->map(fn ($c) => $categories[$c] ?? ucwords(str_replace('_', ' ', $c)))
            ->all();

        // Tren 6 bulan terakhir: pemasukan dan pengeluaran
        $firstMonth = now()->startOfMonth()->subMonthsNoOverflow(5);

        $revByMonth = Payment::valid()
            ->where('created_at', '>=', $firstMonth)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as t")
            ->groupBy('ym')
            ->pluck('t', 'ym');

        $expByMonth = Expense::where('date', '>=', $firstMonth->toDateString())
            ->selectRaw("DATE_FORMAT(`date`, '%Y-%m') as ym, SUM(amount) as t")
            ->groupBy('ym')
            ->pluck('t', 'ym');

        $trendLabels = [];
        $trendRevenue = [];
        $trendExpense = [];

        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonthsNoOverflow($i);
            $key = $m->format('Y-m');

            $trendLabels[] = $m->translatedFormat('M') . ($i === 0 ? ' (Kini)' : '');
            $trendRevenue[] = (float) ($revByMonth[$key] ?? 0);
            $trendExpense[] = (float) ($expByMonth[$key] ?? 0);
        }

        $ledger = $this->hydrate($this->ledgerQuery($start, $end)->limit(6)->get());

        return view('reports.finance', $summary + [
            'revenueChange' => $revenueChange,
            'compareLabel' => $compareLabel,
            'topCategoryLabels' => $topCategoryLabels,
            'trendLabels' => $trendLabels,
            'trendRevenue' => $trendRevenue,
            'trendExpense' => $trendExpense,
            'ledger' => $ledger,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'rangeText' => $rangeText,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ]);
    }

    // Seluruh mutasi kas pada periode terpilih, dengan paginasi
    public function ledger(Request $request)
    {
        [$start, $end, $period] = $this->resolvePeriod($request->get('period', 'month'), $request);
        [$periodLabel, $rangeText] = $this->labels($period, $start, $end);

        $page = $this->ledgerQuery($start, $end)->paginate(20)->withQueryString();
        $entries = $this->hydrate($page->items());

        return view('reports.ledger', $this->summary($start, $end) + [
            'page' => $page,
            'entries' => $entries,
            'periodLabel' => $periodLabel,
            'rangeText' => $rangeText,
        ]);
    }

    public function receivables(Request $request)
    {
        $base = Transaction::notCancelled()->whereColumn('paid_amount', '<', 'total');

        $totalReceivable = (float) (clone $base)->sum(DB::raw('total - paid_amount'));
        $count = (clone $base)->count();
        $oldest = (clone $base)->min('created_at');

        $transactions = (clone $base)
            ->with('customer')
            ->when($request->q, function ($query) use ($request) {
                $query->where(function ($sub) use ($request) {
                    $sub->where('code', 'like', "%{$request->q}%")
                        ->orWhereHas('customer', function ($c) use ($request) {
                            $c->where('name', 'like', "%{$request->q}%")
                                ->orWhere('phone', 'like', "%{$request->q}%");
                        });
                });
            })
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('reports.receivables', [
            'transactions' => $transactions,
            'totalReceivable' => $totalReceivable,
            'count' => $count,
            'oldest' => $oldest ? Carbon::parse($oldest) : null,
        ]);
    }

    // CSV arus kas: satu baris per pembayaran masuk atau pengeluaran
    public function export(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request->get('period', 'month'), $request);

        $filename = 'arus-kas-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.csv';
        $query = $this->ledgerQuery($start, $end);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // supaya Excel membaca huruf dan tanda baca dengan benar

            // Titik koma karena Excel berbahasa Indonesia memakainya sebagai pemisah kolom
            $put = fn (array $row) => fputcsv($out, $row, ';', '"', '\\');

            $put(['Tanggal', 'Jam', 'No. Referensi', 'Kategori', 'Keterangan', 'Metode', 'Masuk (Rp)', 'Keluar (Rp)']);

            $query->chunk(300, function ($rows) use ($put) {
                foreach ($this->hydrate($rows) as $e) {
                    $put([
                        $e['at']->format('Y-m-d'),
                        $e['at']->format('H:i'),
                        $this->csvSafe($e['ref']),
                        $this->csvSafe($e['category']),
                        $this->csvSafe($e['description']),
                        $e['method'],
                        $e['amount'] > 0 ? (int) round($e['amount']) : '',
                        $e['amount'] < 0 ? (int) round(abs($e['amount'])) : '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request)
    {
        [$start, $end, $period] = $this->resolvePeriod($request->get('period', 'month'), $request);
        [$periodLabel, $rangeText] = $this->labels($period, $start, $end);

        $limit = 500;
        $summary = $this->summary($start, $end);
        $entries = $this->hydrate($this->ledgerQuery($start, $end)->limit($limit)->get());

        $pdf = Pdf::loadView('reports.pdf', $summary + [
            'entries' => $entries,
            'truncated' => $summary['ledgerCount'] > $limit,
            'limit' => $limit,
            'periodLabel' => $periodLabel,
            'rangeText' => $rangeText,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('laporan-keuangan-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.pdf');
    }

    // ---------- Bantuan ----------

    private function summary(Carbon $start, Carbon $end): array
    {
        $range = [$start->toDateString(), $end->toDateString()];

        // Pemasukan = uang yang benar-benar diterima, tanpa transaksi dibatalkan
        $totalRevenue = (float) Payment::valid()->whereBetween('created_at', [$start, $end])->sum('amount');
        $totalExpense = (float) Expense::whereBetween('date', $range)->sum('amount');
        $netProfit = $totalRevenue - $totalExpense;

        // Piutang adalah kondisi saat ini, tidak tergantung periode
        $receivable = Transaction::notCancelled()->whereColumn('paid_amount', '<', 'total');
        $oldest = (clone $receivable)->min('created_at');

        // Distribusi metode bayar (berdasarkan nominal uang yang masuk)
        $labels = payment_method_labels();
        $distRows = Payment::valid()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $distTotal = (float) $distRows->sum('total');

        $distribution = $distRows->map(fn ($r) => [
            'label' => $labels[$r->method] ?? ucfirst((string) $r->method),
            'amount' => (float) $r->total,
            'percent' => $distTotal > 0 ? (int) round(($r->total / $distTotal) * 100) : 0,
            'color' => self::METHOD_COLORS[$r->method] ?? '#8CA39F',
        ])->all();

        return [
            'totalRevenue' => $totalRevenue,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'margin' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : null,
            'expenseRatio' => $totalRevenue > 0 ? round(($totalExpense / $totalRevenue) * 100, 1) : null,
            'receivableCount' => (clone $receivable)->count(),
            'receivableTotal' => (float) (clone $receivable)->sum(DB::raw('total - paid_amount')),
            'oldestDays' => $oldest ? (int) Carbon::parse($oldest)->startOfDay()->diffInDays(today()) : null,
            'distribution' => $distribution,
            'distTotal' => $distTotal,
            'ledgerCount' => Payment::valid()->whereBetween('created_at', [$start, $end])->count()
                + Expense::whereBetween('date', $range)->count(),
        ];
    }

    /**
     * Gabungan pembayaran masuk dan pengeluaran, diurutkan dari yang terbaru.
     * Hanya mengambil kolom ringkas, detailnya dimuat setelah halaman terpilih (lihat hydrate).
     */
    private function ledgerQuery(Carbon $start, Carbon $end)
    {
        $in = DB::table('payments')
            ->join('transactions', 'transactions.id', '=', 'payments.transaction_id')
            ->where('transactions.status', '!=', 'dibatalkan')
            ->whereBetween('payments.created_at', [$start, $end])
            ->selectRaw("payments.created_at as happened_at, 'in' as kind, payments.id as source_id, payments.amount as amount");

        $out = DB::table('expenses')
            ->whereBetween('expenses.date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("TIMESTAMP(expenses.date, TIME(expenses.created_at)) as happened_at, 'out' as kind, expenses.id as source_id, expenses.amount as amount");

        return DB::query()
            ->fromSub($in->unionAll($out), 'ledger')
            ->orderByDesc('happened_at')
            ->orderByDesc('kind')
            ->orderByDesc('source_id');
    }

    /**
     * Mengubah baris ringkas menjadi data yang siap ditampilkan.
     */
    private function hydrate($rows): array
    {
        $rows = collect($rows);

        $payments = Payment::with(['transaction.customer', 'transaction.items', 'transaction.payments'])
            ->whereIn('id', $rows->where('kind', 'in')->pluck('source_id')->all())
            ->get()
            ->keyBy('id');

        $expenses = Expense::whereIn('id', $rows->where('kind', 'out')->pluck('source_id')->all())
            ->get()
            ->keyBy('id');

        $methods = payment_method_labels();
        $categories = ExpenseController::CATEGORIES;

        return $rows->map(function ($row) use ($payments, $expenses, $methods, $categories) {
            $at = Carbon::parse($row->happened_at);

            if ($row->kind === 'in') {
                $p = $payments->get($row->source_id);
                $trx = $p?->transaction;

                if (! $p || ! $trx) {
                    return null;
                }

                // DP, cicilan, atau pelunasan, dilihat dari urutan dan jumlah pembayaran sampai titik ini
                $first = (int) $trx->payments->min('id') === (int) $p->id;
                $paidUpTo = (float) $trx->payments->where('id', '<=', $p->id)->sum('amount');
                $settled = $paidUpTo >= (float) $trx->total - 0.5;
                $label = $settled ? ($first ? 'Pembayaran' : 'Pelunasan') : ($first ? 'DP' : 'Cicilan');

                $names = $trx->items->pluck('service_name');
                $service = $names->isEmpty() ? '' : $names->first() . ($names->count() > 1 ? ' +' . ($names->count() - 1) : '');

                return [
                    'kind' => 'in',
                    'at' => $at,
                    'ref' => $trx->code,
                    'url' => route('transactions.show', $trx),
                    'category' => 'Pemasukan Kasir',
                    'description' => $label . ' ' . ($trx->customer->name ?? 'Pelanggan Umum') . ($service !== '' ? ' (' . $service . ')' : ''),
                    'method' => $methods[$p->method] ?? ucfirst((string) $p->method),
                    'amount' => (float) $p->amount,
                ];
            }

            $e = $expenses->get($row->source_id);

            if (! $e) {
                return null;
            }

            return [
                'kind' => 'out',
                'at' => $at,
                'ref' => 'EXP-' . $e->date->format('Ym') . '-' . str_pad((string) $e->id, 3, '0', STR_PAD_LEFT),
                'url' => route('expenses.edit', $e),
                'category' => $categories[$e->category] ?? ucwords(str_replace('_', ' ', (string) $e->category)),
                'description' => $e->description ?: ($categories[$e->category] ?? (string) $e->category),
                'method' => '-',
                'amount' => -1 * (float) $e->amount,
            ];
        })->filter()->values()->all();
    }

    // Sel yang diawali = + - @ bisa dijalankan Excel sebagai rumus, jadi diberi awalan
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    private function labels(string $period, Carbon $start, Carbon $end): array
    {
        $label = match ($period) {
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            'year' => 'Tahun Ini (' . $start->year . ')',
            'custom' => 'Custom',
            default => 'Bulan Ini (' . $start->translatedFormat('M Y') . ')',
        };

        return [$label, $start->translatedFormat('d M Y') . ' – ' . $end->translatedFormat('d M Y')];
    }

    // Periode pembanding dengan panjang yang sama, dihitung sampai titik waktu yang sama (bukan seluruh periode lalu)
    private function previousRange(string $period, Carbon $start, Carbon $end): array
    {
        $to = $end->gt(now()) ? now()->copy() : $end->copy();

        if ($period === 'today') {
            return [$start->copy()->subDay(), $to->copy()->subDay(), 'kemarin'];
        }
        if ($period === 'week') {
            return [$start->copy()->subWeek(), $to->copy()->subWeek(), 'minggu lalu'];
        }
        if ($period === 'month') {
            return [$start->copy()->subMonthNoOverflow(), $to->copy()->subMonthNoOverflow(), 'bulan lalu'];
        }
        if ($period === 'year') {
            return [$start->copy()->subYear(), $to->copy()->subYear(), 'tahun lalu'];
        }

        $length = max(0, (int) $start->diffInSeconds($to));
        $prevEnd = $start->copy()->subSecond();

        return [$prevEnd->copy()->subSeconds($length), $prevEnd, 'periode sebelumnya'];
    }

    private function resolvePeriod(string $period, Request $request): array
    {
        if ($period === 'custom' && $request->filled(['start', 'end'])) {
            try {
                $start = Carbon::parse($request->get('start'))->startOfDay();
                $end = Carbon::parse($request->get('end'))->endOfDay();

                if ($end->lt($start)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                return [$start, $end, 'custom'];
            } catch (\Throwable $e) {
                // Tanggal tidak valid, pakai bulan ini
            }
        }

        return match ($period) {
            'today' => [today(), now()->endOfDay(), 'today'],
            'week' => [now()->startOfWeek(), now()->endOfWeek(), 'week'],
            'year' => [now()->startOfYear(), now()->endOfYear(), 'year'],
            default => [now()->startOfMonth(), now()->endOfMonth(), 'month'],
        };
    }
}