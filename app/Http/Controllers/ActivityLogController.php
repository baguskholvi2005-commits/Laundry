<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    // Kelompok untuk filter: [label, daftar jenis kejadian]
    public const GROUPS = [
        'transaksi' => ['Transaksi', ['transaction_created', 'status_changed']],
        'pembayaran' => ['Pembayaran', ['payment_received']],
        'keuangan' => ['Pengeluaran', ['expense_created', 'expense_changed']],
        'data' => ['Pelanggan, layanan, promo', ['customer_changed', 'service_changed', 'promo_changed']],
        'akses' => ['Pengguna dan pengaturan', ['user_changed', 'customer_access', 'settings_changed']],
        'jemput' => ['Permintaan jemput', ['pickup_request']],
        'sistem' => ['Cadangan', ['backup']],
    ];

    public function index(Request $request)
    {
        $group = array_key_exists((string) $request->get('group'), self::GROUPS) ? $request->get('group') : null;
        $userFilter = (string) $request->get('user', '');
        $from = $this->validDate($request->get('date_from'));
        $to = $this->validDate($request->get('date_to'));

        $logs = ActivityLog::with('user.roles')
            ->when($group, fn ($q) => $q->whereIn('event', self::GROUPS[$group][1]))
            ->when($userFilter === 'sistem', fn ($q) => $q->whereNull('user_id'))
            ->when(ctype_digit($userFilter), fn ($q) => $q->where('user_id', (int) $userFilter))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%' . $request->q . '%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $labelByEvent = collect(self::GROUPS)
            ->flatMap(fn ($g) => collect($g[1])->mapWithKeys(fn ($event) => [$event => $g[0]]))
            ->all();

        return view('activity-logs.index', [
            'logs' => $logs,
            'groups' => self::GROUPS,
            'labelByEvent' => $labelByEvent,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function validDate($value): ?string
    {
        if (! $value || ! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}