<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PickupDelivery;
use App\Models\PickupRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PickupDeliveryController extends Controller
{
    // Cucian dianggap siap diantar kalau transaksinya sudah Siap Diambil (atau sudah Selesai)
    private const READY_STATUSES = ['siap_diambil', 'selesai'];

    public function index(Request $request)
    {
        $type = in_array($request->get('type'), ['pickup', 'delivery'], true) ? $request->get('type') : null;
        $q = is_string($request->get('q')) ? trim($request->get('q')) : '';
        $like = '%' . $q . '%';

        // ---------- Angka ringkas (tidak terpengaruh pencarian) ----------
        $activeRequests = PickupRequest::active()->count();

        $stats = [
            'requests' => $activeRequests,
            'pickup_waiting' => $activeRequests + PickupDelivery::where('type', 'pickup')
                ->where('status', 'dijadwalkan')
                ->whereHas('transaction', fn ($t) => $t->notCancelled())
                ->count(),
            'delivery_ready' => PickupDelivery::where('type', 'delivery')
                ->where('status', 'dijadwalkan')
                ->whereHas('transaction', fn ($t) => $t->whereIn('status', self::READY_STATUSES))
                ->count(),
            'on_the_road' => PickupDelivery::where('status', 'dalam_perjalanan')
                ->whereHas('transaction', fn ($t) => $t->notCancelled())
                ->count(),
        ];

        // ---------- Tugas aktif ----------
        $tasks = PickupDelivery::with(['transaction.customer', 'transaction.items', 'transaction.payments'])
            ->whereIn('status', ['dijadwalkan', 'dalam_perjalanan'])
            ->whereHas('transaction', fn ($t) => $t->notCancelled())
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($sub) use ($like) {
                    $sub->where('address', 'like', $like)
                        ->orWhereHas('transaction', function ($t) use ($like) {
                            $t->where('code', 'like', $like)
                              ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like));
                        });
                });
            })
            ->get();

        // ---------- Permintaan pelanggan yang masih aktif ----------
        $requests = PickupRequest::with(['customer', 'items'])
            ->active()
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($sub) use ($like) {
                    $sub->where('address', 'like', $like)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like));
                });
            })
            ->get();

        // Permintaan yang sudah jadi transaksi, untuk menampilkan catatan khusus dari pelanggan
        $linked = PickupRequest::whereIn('transaction_id', $tasks->pluck('transaction_id')->unique())
            ->get()
            ->keyBy('transaction_id');

        // ---------- Satukan jadi satu papan ----------
        $slotOrder = array_flip(array_keys(PickupRequest::TIME_SLOTS));
        $cards = collect();

        foreach ($requests as $r) {
            $cards->push([
                'kind' => 'request',
                'type' => 'pickup',
                'priority' => $r->status === 'menunggu' ? 1 : 4,
                'sort' => $r->preferred_date->toDateString() . ' ' . ($slotOrder[$r->time_slot] ?? 9),
                'request' => $r,
                'task' => null,
            ]);
        }

        foreach ($tasks as $task) {
            $ready = in_array($task->transaction->status, self::READY_STATUSES, true);

            $priority = match (true) {
                $task->status === 'dalam_perjalanan' => 0,
                $task->type === 'delivery' && $ready => 2,
                $task->type === 'delivery' => 5,
                default => 3,
            };

            $cards->push([
                'kind' => 'task',
                'type' => $task->type,
                'priority' => $priority,
                'sort' => ($task->scheduled_at ?? $task->created_at)->format('Y-m-d H:i:s'),
                'request' => $linked->get($task->transaction_id),
                'task' => $task,
            ]);
        }

        $cards = $cards->sortBy([['priority', 'asc'], ['sort', 'asc']])->values();

        $counts = [
            'all' => $cards->count(),
            'pickup' => $cards->where('type', 'pickup')->count(),
            'delivery' => $cards->where('type', 'delivery')->count(),
        ];

        $visible = $type ? $cards->where('type', $type)->values() : $cards;

        return view('pickups.index', compact('visible', 'counts', 'stats', 'type', 'q'));
    }

    public function pickupOnly(Request $request)
    {
        $request->merge(['type' => 'pickup']);
        return $this->index($request);
    }

    public function deliveryOnly(Request $request)
    {
        $request->merge(['type' => 'delivery']);
        return $this->index($request);
    }

    public function show(PickupDelivery $pickup)
    {
        $pickup->load(['transaction.customer', 'transaction.items', 'transaction.payments']);
        $linkedRequest = PickupRequest::with('items')->where('transaction_id', $pickup->transaction_id)->first();

        return view('pickups.show', compact('pickup', 'linkedRequest'));
    }

    public function updateStatus(Request $request, PickupDelivery $pickup)
    {
        $data = $request->validate([
            'status' => 'required|in:dijadwalkan,dalam_perjalanan,selesai,dibatalkan',
            'collect_payment' => 'nullable|boolean',
        ]);

        $new = $data['status'];
        $collect = (bool) ($data['collect_payment'] ?? false);
        $user = $request->user();

        $result = DB::transaction(function () use ($pickup, $new, $collect, $user) {
            // Kunci transaksi dulu, baru tugasnya (urutan sama dengan pembatalan transaksi)
            $trx = Transaction::lockForUpdate()->findOrFail($pickup->transaction_id);
            $task = PickupDelivery::lockForUpdate()->findOrFail($pickup->id);

            $isDelivery = $task->type === 'delivery';
            $label = $isDelivery ? 'pengantaran' : 'penjemputan';

            if ($trx->status === 'dibatalkan') {
                return ['error' => 'Transaksi ' . $trx->code . ' sudah dibatalkan, tugas tidak bisa diubah.'];
            }

            if ($task->status === $new) {
                return ['error' => 'Tugas ini sudah berstatus ' . str_replace('_', ' ', $new) . '.'];
            }

            $allowed = [
                'dijadwalkan' => ['dalam_perjalanan', 'selesai', 'dibatalkan'],
                'dalam_perjalanan' => ['dijadwalkan', 'selesai', 'dibatalkan'],
            ];

            if (! in_array($new, $allowed[$task->status] ?? [], true)) {
                return ['error' => 'Tugas ' . $label . ' ini sudah ' . str_replace('_', ' ', $task->status) . ' dan tidak bisa diubah lagi.'];
            }

            // Cucian yang belum selesai diproses tidak boleh dikirim
            if ($isDelivery && in_array($new, ['dalam_perjalanan', 'selesai'], true)
                && ! in_array($trx->status, self::READY_STATUSES, true)) {
                return ['error' => 'Cucian ' . $trx->code . ' belum siap diantar (status: ' . str_replace('_', ' ', $trx->status)
                    . '). Ubah status transaksi ke Siap Diambil dulu.'];
            }

            $task->update(['status' => $new]);

            $note = '';

            if ($isDelivery && $new === 'selesai') {
                // Cucian sudah diterima pelanggan, transaksinya selesai
                if ($trx->status === 'siap_diambil') {
                    $trx->update(['status' => 'selesai']);
                }

                $remaining = max(0, (float) $trx->total - (float) $trx->paid_amount);

                if ($collect && $remaining > 0) {
                    $trx->payments()->create([
                        'amount' => $remaining,
                        'method' => 'cash',
                        'received_by' => $user->id,
                    ]);

                    $trx->update([
                        'paid_amount' => $trx->total,
                        'payment_status' => 'lunas',
                        'payment_method' => 'cash',
                    ]);

                    $note = ' Pembayaran tunai Rp ' . number_format($remaining, 0, ',', '.') . ' dicatat, transaksi lunas.';
                }
            }

            $text = match ($new) {
                'dalam_perjalanan' => 'memberangkatkan ' . $label . ' ' . $trx->code,
                'selesai' => 'menyelesaikan ' . $label . ' ' . $trx->code,
                'dibatalkan' => 'membatalkan tugas ' . $label . ' ' . $trx->code,
                default => 'mengembalikan ' . $label . ' ' . $trx->code . ' ke jadwal',
            };

            ActivityLog::record('pickup_task', $text, $trx->id);

            return ['ok' => 'Tugas ' . $label . ' diperbarui.' . $note];
        });

        return isset($result['error'])
            ? back()->with('error', $result['error'])
            : back()->with('success', $result['ok']);
    }
}