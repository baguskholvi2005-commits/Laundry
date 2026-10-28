<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PickupRequest;
use App\Models\Promo;
use App\Models\PromoUsage;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Transaction;
use App\Services\TransactionCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = $this->listTransactions($request);
        return view('transactions.index', compact('transactions'));
    }

    public function processing(Request $request)
    {
        $transactions = $this->listTransactions($request, ['diproses', 'dicuci', 'dikeringkan', 'disetrika']);
        return view('transactions.index', compact('transactions'));
    }

    public function ready(Request $request)
    {
        $transactions = $this->listTransactions($request, ['siap_diambil']);
        return view('transactions.index', compact('transactions'));
    }

    public function history(Request $request)
    {
        $transactions = $this->listTransactions($request, ['selesai', 'dibatalkan']);
        return view('transactions.index', compact('transactions'));
    }

    private function listTransactions(Request $request, ?array $statuses = null)
    {
        $from = $this->validDate($request->get('date_from'));
        $to = $this->validDate($request->get('date_to'));
        $payment = array_key_exists((string) $request->get('payment'), payment_method_labels())
            ? $request->get('payment')
            : null;

        return Transaction::with(['customer', 'cashier'])
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($payment, fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('method', $payment)))
            ->when($request->q, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('code', 'like', "%{$request->q}%")
                        ->orWhereHas('customer', function ($c) use ($request) {
                            $c->where('name', 'like', "%{$request->q}%")
                                ->orWhere('phone', 'like', "%{$request->q}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();
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

    private function customerPayload(?Customer $customer): ?array
    {
        if (! $customer) {
            return null;
        }

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'is_member' => (bool) $customer->is_member,
            'is_eligible_for_member' => $customer->is_eligible_for_member,
        ];
    }

    public function create(Request $request)
    {
        $services = Service::active()->get(['id', 'name', 'type', 'price', 'duration_days']);
        
        $packages = ServicePackage::with('service:id,name,price')
            ->available()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'weight_kg' => (float) $p->weight_kg,
                'package_price' => (float) $p->package_price,
                'normal_price' => $p->normal_price_live,
                'service_name' => $p->service->name,
            ])
            ->values();

        $promos = Promo::where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->get(['id', 'name', 'type', 'value', 'value_type', 'min_transaction', 'min_weight', 'quota', 'used_count', 'stackable']);

        $totalPromo = Promo::count();

        $prefill = null;
        $prefillSkipped = 0;
        $prefillNote = null;
        $pickupRequestId = null;

        if ($request->filled('from')) {
            // "Salin ke transaksi baru": isi keranjang dari transaksi lama dengan harga dan ketersediaan saat ini
            $source = Transaction::with(['items', 'customer'])->find($request->get('from'));

            if ($source) {
                $cartItems = [];

                foreach ($source->items as $item) {
                    if ($item->package_id) {
                        $pkg = $packages->firstWhere('id', $item->package_id);

                        if ($pkg) {
                            $cartItems[] = [
                                'service_id' => null,
                                'package_id' => $pkg['id'],
                                'name' => 'Paket ' . $pkg['name'],
                                'type' => 'paket',
                                'price' => $pkg['package_price'],
                                'weight_kg' => $pkg['weight_kg'],
                                'qty' => max(1, (int) $item->qty),
                            ];
                        }
                    } else {
                        $svc = $services->firstWhere('id', $item->service_id);

                        if ($svc) {
                            $cartItems[] = [
                                'service_id' => $svc->id,
                                'package_id' => null,
                                'name' => $svc->name,
                                'type' => $svc->type,
                                'price' => (float) $svc->price,
                                'qty' => (float) $item->qty,
                            ];
                        }
                    }
                }

                $prefill = ['customer' => $this->customerPayload($source->customer), 'items' => $cartItems];
                $prefillSkipped = $source->items->count() - count($cartItems);
                $prefillNote = 'Disalin dari transaksi ' . $source->code . '. Harga memakai harga hari ini. Periksa lagi sebelum menyimpan.';
            }
        } elseif ($request->filled('pickup_request')) {
            // Dari permintaan jemput pelanggan: pelanggan, layanan, Jemput, dan Antar sudah terisi
            $pr = PickupRequest::with(['customer', 'items'])->find($request->get('pickup_request'));

            if (! $pr || $pr->status !== 'dikonfirmasi') {
                return redirect()->route('pickup-requests.index')
                    ->with('error', 'Permintaan jemput itu tidak ditemukan atau sudah diproses.');
            }

            $cartItems = [];

            foreach ($pr->items as $item) {
                $svc = $services->firstWhere('id', $item->service_id);

                if ($svc) {
                    $cartItems[] = [
                        'service_id' => $svc->id,
                        'package_id' => null,
                        'name' => $svc->name,
                        'type' => $svc->type,
                        'price' => (float) $svc->price,
                        // Berat kiloan yang belum diketahui dibiarkan kosong supaya kasir wajib menimbang
                        'qty' => $item->qty !== null ? (float) $item->qty : null,
                    ];
                }
            }

            $prefill = [
                'customer' => $this->customerPayload($pr->customer),
                'items' => $cartItems,
                'use_pickup' => true,
                'use_delivery' => $pr->wants_delivery,
            ];
            $prefillSkipped = $pr->items->count() - count($cartItems);
            $pickupRequestId = $pr->id;

            $prefillNote = 'Dari permintaan ' . strtolower($pr->mode_label) . ' ' . $pr->customer->name
                . ' (' . $pr->preferred_date->translatedFormat('d M Y') . '). '
                . ($pr->items->isNotEmpty()
                    ? 'Layanan dan berat di keranjang hanya perkiraan pelanggan: timbang dulu, lalu sesuaikan. Berat yang masih kosong wajib diisi sebelum menyimpan.'
                    : 'Pelanggan belum memilih layanan, pilih sesuai cucian yang dijemput.')
                . ($pr->wants_delivery
                    ? ' Jemput dan Antar sudah tercentang otomatis.'
                    : ' Jemput sudah tercentang otomatis, cucian nanti diambil sendiri oleh pelanggan.');
        }

        return view('transactions.create', compact(
            'services', 'packages', 'promos', 'totalPromo', 'prefill', 'prefillSkipped', 'prefillNote', 'pickupRequestId'
        ));
    }

    /**
     * Susun item dari data server. Harga dan nama TIDAK diambil dari kiriman browser.
     */
    private function buildItems(array $rawItems): array
    {
        $services = Service::active()
            ->whereIn('id', collect($rawItems)->pluck('service_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $packages = ServicePackage::with('service')
            ->whereIn('id', collect($rawItems)->pluck('package_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($rawItems as $raw) {
            if (! empty($raw['package_id'])) {
                $package = $packages->get($raw['package_id']);

                if (! $package) {
                    throw ValidationException::withMessages(['items' => 'Paket tidak ditemukan.']);
                }

                $qty = max(1, (int) round((float) $raw['qty']));

                $items[] = [
                    'service_id' => $package->service_id,
                    'package_id' => $package->id,
                    'name' => 'Paket ' . $package->name,
                    'unit_type' => 'paket',
                    'qty' => $qty,
                    'price_per_unit' => (float) $package->package_price,
                    'weight_kg' => (float) $package->weight_kg * $qty,
                ];
                continue;
            }

            $service = $services->get($raw['service_id'] ?? 0);

            if (! $service) {
                throw ValidationException::withMessages(['items' => 'Layanan tidak ditemukan atau sudah dinonaktifkan.']);
            }

            $isKilo = $service->type === 'kiloan';

            $items[] = [
                'service_id' => $service->id,
                'package_id' => null,
                'name' => $service->name,
                'unit_type' => $isKilo ? 'kg' : 'pcs',
                'qty' => (float) $raw['qty'],
                'price_per_unit' => (float) $service->price,
                'weight_kg' => $isKilo ? (float) $raw['qty'] : 0,
            ];
        }

        return $items;
    }

    public function store(Request $request, TransactionCalculator $calculator)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'nullable|integer',
            'items.*.package_id' => 'nullable|integer',
            'items.*.qty' => 'required|numeric|min:0.1',
            'promo_ids' => 'nullable|array|max:3',
            'promo_ids.*' => 'integer|exists:promos,id',
            'use_pickup' => 'boolean',
            'use_delivery' => 'boolean',
            'pickup_request_id' => 'nullable|integer|exists:pickup_requests,id',
            'payment_method' => ['nullable', Rule::in(array_keys(payment_methods()))],
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $items = $this->buildItems($data['items']);
        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;

        $transaction = DB::transaction(function () use ($data, $items, $customer, $calculator) {
            // Permintaan jemput dari pelanggan: kunci barisnya dan pastikan masih berlaku
            $pickupRequest = null;

            if (! empty($data['pickup_request_id'])) {
                $pickupRequest = PickupRequest::lockForUpdate()->find($data['pickup_request_id']);

                if (! $pickupRequest || $pickupRequest->status !== 'dikonfirmasi') {
                    throw ValidationException::withMessages([
                        'pickup_request_id' => 'Permintaan jemput ini sudah diproses atau dibatalkan pelanggan. Buka ulang dari halaman Permintaan Jemput.',
                    ]);
                }

                if ((int) $pickupRequest->customer_id !== (int) ($data['customer_id'] ?? 0)) {
                    throw ValidationException::withMessages([
                        'pickup_request_id' => 'Pelanggan di transaksi harus sama dengan pelanggan yang meminta jemput.',
                    ]);
                }
            }

            // Transaksi dari permintaan jemput selalu bertanda Jemput
            $usePickup = ($data['use_pickup'] ?? false) || $pickupRequest !== null;

            // Antar hanya berlaku kalau layanan antar masih menyala di Pengaturan
            $useDelivery = (bool) ($data['use_delivery'] ?? false) && (bool) setting('delivery_enabled');

            // Paket: kunci barisnya, cek ketersediaan, lalu kurangi kuota
            $packageQty = collect($items)
                ->whereNotNull('package_id')
                ->groupBy('package_id')
                ->map(fn ($rows) => (int) $rows->sum('qty'));

            foreach ($packageQty as $packageId => $qty) {
                $package = ServicePackage::with('service')->lockForUpdate()->find($packageId);
                $reason = $package ? $package->unavailableReason($qty) : 'Paket tidak ditemukan.';

                if ($reason) {
                    throw ValidationException::withMessages(['items' => $reason]);
                }

                $package->increment('used_count', $qty);
            }

            // Promo: kunci barisnya (berurutan, supaya aman) agar 2 kasir tidak bisa melewati kuota bersamaan
            $promos = ! empty($data['promo_ids'])
                ? Promo::whereIn('id', array_unique($data['promo_ids']))->orderBy('id')->lockForUpdate()->get()
                : collect();

            $result = $calculator->calculate(
                $items,
                $promos,
                $usePickup,
                $useDelivery,
                $customer
            );

            // Ada promo dipilih yang tidak berlaku: tolak dengan alasan jelas, jangan disimpan diam-diam tanpa diskon
            $failed = collect($result['promo_results'])->first(fn ($r) => ! $r['applied']);
            if ($failed) {
                throw ValidationException::withMessages([
                    'promo_ids' => $failed['reason'] ?? 'Promo tidak dapat digunakan.',
                ]);
            }

            $paid = (float) ($data['paid_amount'] ?? 0);
            $paymentStatus = 'belum_bayar';
            if ($paid >= $result['total'] && $result['total'] > 0) {
                $paymentStatus = 'lunas';
            } elseif ($paid > 0) {
                $paymentStatus = 'dp';
            }

            // Estimasi selesai: layanan paling lama. Kalau tidak diisi, ikut pengaturan umum
            $durations = Service::whereIn('id', collect($items)->pluck('service_id')->filter()->unique())
                ->pluck('duration_days');
            $days = (int) ($durations->filter()->max() ?: setting('work_duration_days', 2));

            $transaction = Transaction::create([
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => Auth::id(),
                'subtotal' => $result['subtotal'],
                'discount' => $result['discount'],
                'delivery_fee' => $result['delivery_fee'],
                'total' => $result['total'],
                'paid_amount' => min($paid, $result['total']),
                'payment_status' => $paymentStatus,
                'payment_method' => $data['payment_method'] ?? null,
                'promo_id' => $promos->first()?->id,
                'use_pickup' => $usePickup,
                'use_delivery' => $useDelivery,
                'estimated_done_at' => now()->addDays($days),
            ]);

            foreach ($items as $item) {
                $transaction->items()->create([
                    'service_id' => $item['service_id'],
                    'package_id' => $item['package_id'],
                    'service_name' => $item['name'],
                    'unit_type' => $item['unit_type'],
                    'qty' => $item['qty'],
                    'price_per_unit' => $item['price_per_unit'],
                    'subtotal' => $item['qty'] * $item['price_per_unit'],
                ]);
            }

            if ($transaction->use_pickup) {
                $transaction->pickupDeliveries()->create([
                    'type' => 'pickup',
                    // Kalau berasal dari permintaan pelanggan, laundry sudah dijemput saat transaksi ini dibuat
                    'status' => $pickupRequest ? 'selesai' : 'dijadwalkan',
                    'scheduled_at' => now(),
                    'address' => $pickupRequest?->address ?? $customer?->address,
                ]);
            }

            if ($transaction->use_delivery) {
                $transaction->pickupDeliveries()->create([
                    'type' => 'delivery',
                    'status' => 'dijadwalkan',
                    'scheduled_at' => $transaction->estimated_done_at,
                    // Diantar kembali ke alamat yang pelanggan tulis di permintaan jemput
                    'address' => $pickupRequest?->address ?? $customer?->address,
                ]);
            }

            if ($paid > 0) {
                $transaction->payments()->create([
                    'amount' => min($paid, $result['total']),
                    'method' => $data['payment_method'] ?? array_key_first(payment_methods()),
                    'received_by' => Auth::id(),
                ]);
            }

            // Kuota tiap promo berkurang hanya kalau promonya benar-benar berefek (lolos pengecekan di atas)
            foreach ($result['promo_results'] as $r) {
                $promo = $promos->firstWhere('id', $r['id']);
                $promo->increment('used_count');
                $promo->usages()->create([
                    'transaction_id' => $transaction->id,
                    'discount_amount' => $r['amount'],
                ]);
            }

            // Permintaan jemput selesai dan tertaut ke transaksi ini
            if ($pickupRequest) {
                $pickupRequest->forceFill([
                    'status' => 'dijemput',
                    'transaction_id' => $transaction->id,
                ])->save();
            }

            return $transaction;
        });

        if ($request->wantsJson()) {
            return response()->json(['id' => $transaction->id, 'code' => $transaction->code]);
        }

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaksi berhasil disimpan.');
    }

    public function preview(Request $request, TransactionCalculator $calculator)
    {
        $data = $request->validate([
            'items' => 'array',
            'items.*.service_id' => 'nullable|integer',
            'items.*.package_id' => 'nullable|integer',
            'items.*.qty' => 'required_with:items|numeric|min:0',
            'promo_ids' => 'nullable|array|max:3',
            'promo_ids.*' => 'integer|exists:promos,id',
            'use_pickup' => 'boolean',
            'use_delivery' => 'boolean',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $items = $this->buildItems($data['items'] ?? []);
        $promos = Promo::whereIn('id', $data['promo_ids'] ?? [])->orderBy('id')->get();
        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;

        $result = $calculator->calculate(
            $items,
            $promos,
            $data['use_pickup'] ?? false,
            $data['use_delivery'] ?? false,
            $customer
        );

        return response()->json($result);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['customer', 'cashier', 'canceller', 'items', 'payments', 'promoUsages.promo', 'pickupDeliveries']);
        return view('transactions.show', compact('transaction'));
    }

    // Memperbaiki salah input yang tidak mengubah angka: pelanggan dan catatan
    public function updateInfo(Request $request, Transaction $transaction)
    {
        if ($transaction->status === 'dibatalkan') {
            return back()->with('error', 'Transaksi yang sudah dibatalkan tidak dapat diubah.');
        }

        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $transaction->update([
            'customer_id' => $data['customer_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Data transaksi diperbarui.');
    }

    public function printNota(Transaction $transaction)
    {
        $transaction->load(['customer', 'cashier', 'items']);
        $qrCodeSvg = QrCode::size(120)->generate(route('tracking.show', $transaction->tracking_token));

        $pdf = Pdf::loadView('transactions.nota', compact('transaction', 'qrCodeSvg'))
            ->setPaper([0, 0, 226.77, 800], 'portrait');

        return $pdf->stream("nota-{$transaction->code}.pdf");
    }

    public function downloadNota(Transaction $transaction)
    {
        $transaction->load(['customer', 'cashier', 'items']);
        $qrCodeSvg = QrCode::size(120)->generate(route('tracking.show', $transaction->tracking_token));

        $pdf = Pdf::loadView('transactions.nota', compact('transaction', 'qrCodeSvg'))
            ->setPaper([0, 0, 226.77, 800], 'portrait');

        return $pdf->download("nota-{$transaction->code}.pdf");
    }

    public function updateStatus(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', Transaction::STATUS_FLOW),
        ]);

        if ($transaction->status === 'dibatalkan') {
            return back()->with('error', 'Transaksi yang sudah dibatalkan tidak dapat diubah statusnya.');
        }

        $transaction->update(['status' => $request->status]);

        return back()->with('success', 'Status transaksi diperbarui menjadi ' . str_replace('_', ' ', $request->status) . '.');
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        $user = $request->user();
        $reason = trim((string) $request->input('reason'));

        if (mb_strlen($reason) < 3) {
            return back()->with('error', 'Alasan pembatalan wajib diisi (minimal 3 huruf).');
        }

        $reason = mb_substr($reason, 0, 255);

        $error = DB::transaction(function () use ($transaction, $user, $reason) {
            $trx = Transaction::lockForUpdate()->findOrFail($transaction->id);

            if (in_array($trx->status, ['selesai', 'dibatalkan'], true)) {
                return 'Transaksi berstatus ' . str_replace('_', ' ', $trx->status) . ' tidak dapat dibatalkan.';
            }

            if (! $trx->canBeCancelledBy($user)) {
                return $user->hasRole('pemilik')
                    ? 'Transaksi ini tidak dapat dibatalkan.'
                    : 'Kasir hanya bisa membatalkan transaksi yang belum ada pembayarannya, dalam '
                        . Transaction::CASHIER_CANCEL_HOURS . ' jam pertama, dan hanya kalau diizinkan pemilik di Pengaturan.';
            }

            $trx->update([
                'status' => 'dibatalkan',
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            // Tugas antar-jemput yang belum selesai ikut dibatalkan
            $trx->pickupDeliveries()
                ->where('status', '!=', 'selesai')
                ->update(['status' => 'dibatalkan']);

            // Kuota tiap promo yang dipakai dikembalikan
            $usages = PromoUsage::where('transaction_id', $trx->id)->get();
            foreach ($usages->groupBy('promo_id') as $promoId => $rows) {
                $count = $rows->count();
                Promo::whereKey($promoId)
                    ->where('used_count', '>=', $count)
                    ->decrement('used_count', $count);
            }
            PromoUsage::where('transaction_id', $trx->id)->delete();

            // Kuota paket dikembalikan
            $trx->items()->whereNotNull('package_id')->get()
                ->groupBy('package_id')
                ->each(function ($rows, $packageId) {
                    $qty = (int) $rows->sum('qty');
                    ServicePackage::whereKey($packageId)
                        ->where('used_count', '>=', $qty)
                        ->decrement('used_count', $qty);
                });

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        $message = 'Transaksi dibatalkan.';
        $paid = (float) $transaction->paid_amount;
        if ($paid > 0) {
            $message .= ' Pembayaran Rp ' . number_format($paid, 0, ',', '.') . ' yang sudah diterima perlu dikembalikan ke pelanggan.';
        }

        return back()->with('success', $message);
    }

    public function processQueue()
    {
        $transactions = Transaction::with(['customer', 'items'])
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->orderBy('created_at')
            ->get();

        return view('transactions.process-queue', compact('transactions'));
    }
}