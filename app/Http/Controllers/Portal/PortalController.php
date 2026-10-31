<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
        public function home(Request $request)
    {
        $customer = $request->user('customer');
        $base = $customer->transactions()->notCancelled();

        $totalTransactions = (clone $base)->count();
        $totalSpent = (float) (clone $base)->sum('total');
        $totalSaved = (float) (clone $base)->sum('discount');
        $monthSaved = (float) (clone $base)->where('created_at', '>=', today()->startOfMonth())->sum('discount');

        // Progres menuju syarat member (jumlah transaksi ATAU total belanja)
        $minTrx = max(1, (int) setting('member_min_transactions', 5));
        $minSpent = (float) setting('member_min_spent', 200000);

        $remainingTrx = max(0, $minTrx - $totalTransactions);
        $remainingSpent = max(0, $minSpent - $totalSpent);
        $progress = (int) round(max(
            min(1, $totalTransactions / $minTrx),
            $minSpent > 0 ? min(1, $totalSpent / $minSpent) : 0
        ) * 100);
        $eligible = $remainingTrx === 0 || ($minSpent > 0 && $remainingSpent <= 0);

        $active = (clone $base)->where('status', '!=', 'selesai')
            ->with('items')
            ->latest()
            ->get();

        $recent = (clone $base)->where('status', 'selesai')
            ->with('items')
            ->latest()
            ->take(3)
            ->get();

        $promos = Promo::where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->where(fn ($q) => $q->whereNull('quota')->orWhereColumn('used_count', '<', 'quota'))
            ->when(! $customer->is_member, fn ($q) => $q->where('type', '!=', 'member'))
            ->orderBy('end_date')
            ->get()
            ->map(fn ($p) => $this->describePromo($p));

        return view('portal.home', compact(
            'customer', 'totalTransactions', 'totalSpent', 'totalSaved', 'monthSaved',
            'minTrx', 'minSpent', 'remainingTrx', 'remainingSpent', 'progress', 'eligible',
            'active', 'recent', 'promos'
        ));
    }

    public function transactions(Request $request)
    {
        $transactions = $request->user('customer')->transactions()
            ->with('items')
            ->latest()
            ->paginate(10);

        return view('portal.transactions', compact('transactions'));
    }

    public function show(Request $request, string $token)
    {
        // Hanya transaksi milik pelanggan yang sedang masuk
        $transaction = $request->user('customer')->transactions()
            ->where('tracking_token', $token)
            ->with(['items', 'promoUsages.promo'])
            ->firstOrFail();

        return view('portal.show', compact('transaction'));
    }

    public function account(Request $request)
    {
        return view('portal.account', ['customer' => $request->user('customer')]);
    }

    public function updatePassword(Request $request)
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|max:100|confirmed',
        ]);

        if (! Hash::check($data['current_password'], $customer->password)) {
            throw ValidationException::withMessages(['current_password' => 'Kata sandi sekarang salah.']);
        }

        $customer->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', 'Kata sandi berhasil diganti.');
    }

    private function describePromo(Promo $p): array
    {
        $value = match (true) {
            $p->type === 'gratis_ongkir' => 'Gratis Ongkir',
            $p->value_type === 'persen' => rtrim(rtrim(number_format((float) $p->value, 2, '.', ''), '0'), '.') . '% off',
            default => 'Potongan Rp ' . number_format((float) $p->value, 0, ',', '.'),
        };

        $syarat = [];
        if ($p->min_transaction) {
            $syarat[] = 'Min. belanja Rp ' . number_format((float) $p->min_transaction, 0, ',', '.');
        }
        if ($p->min_weight) {
            $syarat[] = 'Min. ' . (float) $p->min_weight . ' kg';
        }
        if ($p->start_time && $p->end_time) {
            $syarat[] = 'Pukul ' . substr($p->start_time, 0, 5) . '-' . substr($p->end_time, 0, 5);
        }

        return [
            'name' => $p->name,
            'description' => $p->description,
            'value' => $value,
            'syarat' => implode(' · ', $syarat),
            'until' => $p->end_date->translatedFormat('d M Y'),
            'member_only' => $p->type === 'member',
        ];
    }
}