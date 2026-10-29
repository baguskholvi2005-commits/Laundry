<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    private const STATUSES = ['member', 'rekomendasi', 'reguler', 'akses'];
    private const SORTS = ['belanja', 'transaksi', 'nama'];

    public function index(Request $request)
    {
        $status = $this->pick($request, 'status', self::STATUSES, 'semua');
        $sort = $this->pick($request, 'sort', self::SORTS, 'terbaru');

        $customers = $this->filteredQuery($request)->paginate(15)->withQueryString();

        $total = Customer::count();
        $members = Customer::where('is_member', true)->count();

        $stats = [
            'total' => $total,
            'new_month' => Customer::where('created_at', '>=', today()->startOfMonth())->count(),
            'members' => $members,
            'member_share' => $total > 0 ? (int) round(($members / $total) * 100) : 0,
            'eligible' => $this->applyEligibility(Customer::query()->where('is_member', false), true)->count(),
            'online' => Customer::whereNotNull('password')->count(),
        ];

        return view('customers.index', compact('customers', 'status', 'sort', 'stats'));
    }

    // Ekspor mengikuti pencarian, filter, dan urutan yang sedang aktif. Khusus pemilik (route-nya yang membatasi)
    public function export(Request $request)
    {
        $query = $this->filteredQuery($request);
        $filename = 'pelanggan-' . now()->format('Ymd-His') . '.csv';

        // Sel yang diawali = + - @ bisa dijalankan Excel sebagai rumus, jadi diberi awalan
        $safe = function ($value) {
            $value = (string) $value;

            return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
                ? "'" . $value
                : $value;
        };

        return response()->streamDownload(function () use ($query, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // supaya Excel membaca huruf dan tanda baca dengan benar

            // Titik koma karena Excel berbahasa Indonesia memakainya sebagai pemisah kolom
            $put = fn (array $row) => fputcsv($out, $row, ';', '"', '\\');

            $put(['Nama', 'No. HP', 'Alamat', 'Status', 'Jumlah Transaksi', 'Total Belanja (Rp)', 'Akses Online', 'Bergabung']);

            foreach ($query->cursor() as $c) {
                $put([
                    $safe($c->name),
                    // Dibungkus rumus teks supaya Excel tidak membuang angka 0 di depan atau memakai notasi ilmiah
                    $c->phone_normalized ? '="0' . substr($c->phone_normalized, 2) . '"' : $safe($c->phone),
                    $safe($c->address),
                    $c->is_member ? 'Member' : ($c->is_eligible_for_member ? 'Layak jadi member' : 'Reguler'),
                    (int) $c->active_transactions_count,
                    (int) round((float) $c->active_spent),
                    $c->password !== null ? 'Ya' : 'Tidak',
                    $c->created_at?->format('Y-m-d'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Dipanggil dari halaman Transaksi Baru
    public function search(Request $request)
    {
        $q = $request->get('q');

        $customers = Customer::where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(8)
            ->get();

        return response()->json($customers->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'address' => $c->address,
            'is_member' => (bool) $c->is_member,
            'is_eligible_for_member' => $c->is_eligible_for_member,
        ]));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create($data);

        if ($request->wantsJson()) {
            return response()->json($customer->fresh());
        }

        return redirect()->route('customers.index')->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['transactions' => fn ($q) => $q->latest()]);
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_member' => 'boolean',
        ]);

        // Status member hanya boleh diubah pemilik
        if (! $request->user()->hasRole('pemilik')) {
            unset($data['is_member']);
        }

        // Nomor HP dipakai sebagai nama pengguna akun online, jadi harus tetap benar dan tidak kembar
        if ($customer->has_online_access) {
            $normalized = Customer::normalizePhone($data['phone'] ?? null);

            if (! $normalized) {
                throw ValidationException::withMessages([
                    'phone' => 'Pelanggan ini punya akses online, nomor HP wajib diisi dengan benar (contoh 0812xxxxxxxx).',
                ]);
            }

            $taken = Customer::where('id', '!=', $customer->id)
                ->where('phone_normalized', $normalized)
                ->whereNotNull('password')
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'phone' => 'Nomor HP ini sudah dipakai akun online pelanggan lain.',
                ]);
            }
        }

        $customer->update($data);

        return redirect()->route('customers.index')->with('success', 'Data pelanggan diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Pelanggan dihapus.');
    }

    public function promoteMember(Customer $customer)
    {
        $customer->update(['is_member' => true]);

        return back()->with('success', "{$customer->name} berhasil dijadikan member!");
    }

    // ---------- Bantuan ----------

    private function pick(Request $request, string $key, array $allowed, string $default): string
    {
        $value = $request->get($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    // Layak jadi member = sudah memenuhi jumlah transaksi ATAU total belanja (transaksi batal tidak dihitung)
    private function applyEligibility($query, bool $eligible)
    {
        $minTrx = (int) setting('member_min_transactions', 999999);
        $minSpent = (float) setting('member_min_spent', 999999999999);

        $count = '(select count(*) from transactions t where t.customer_id = customers.id and t.status != ?)';
        $spent = '(select coalesce(sum(t.total), 0) from transactions t where t.customer_id = customers.id and t.status != ?)';

        if ($eligible) {
            return $query->where(function ($q) use ($count, $spent, $minTrx, $minSpent) {
                $q->whereRaw($count . ' >= ?', ['dibatalkan', $minTrx])
                  ->orWhereRaw($spent . ' >= ?', ['dibatalkan', $minSpent]);
            });
        }

        return $query
            ->whereRaw($count . ' < ?', ['dibatalkan', $minTrx])
            ->whereRaw($spent . ' < ?', ['dibatalkan', $minSpent]);
    }

    private function filteredQuery(Request $request)
    {
        $status = $this->pick($request, 'status', self::STATUSES, 'semua');
        $sort = $this->pick($request, 'sort', self::SORTS, 'terbaru');
        $q = is_string($request->get('q')) ? trim($request->get('q')) : '';

        $query = Customer::query()
            ->withCount(['transactions as active_transactions_count' => fn ($t) => $t->notCancelled()])
            ->withSum(['transactions as active_spent' => fn ($t) => $t->notCancelled()], 'total');

        if ($q !== '') {
            $like = '%' . $q . '%';
            // Nomor yang diketik 0812... juga cocok dengan nomor tersimpan 62812..., dengan atau tanpa tanda hubung
            $digits = ltrim((string) preg_replace('/\D/', '', $q), '0');

            $query->where(function ($sub) use ($like, $digits) {
                $sub->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('address', 'like', $like);

                if (strlen($digits) >= 3) {
                    $sub->orWhere('phone_normalized', 'like', '%' . $digits . '%');
                }
            });
        }

        if ($status === 'member') {
            $query->where('is_member', true);
        } elseif ($status === 'rekomendasi') {
            $this->applyEligibility($query->where('is_member', false), true);
        } elseif ($status === 'reguler') {
            $this->applyEligibility($query->where('is_member', false), false);
        } elseif ($status === 'akses') {
            $query->whereNotNull('password');
        }

        if ($sort === 'belanja') {
            $query->orderByDesc('active_spent')->orderBy('name');
        } elseif ($sort === 'transaksi') {
            $query->orderByDesc('active_transactions_count')->orderByDesc('active_spent');
        } elseif ($sort === 'nama') {
            $query->orderBy('name');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->orderByDesc('id');
    }
}