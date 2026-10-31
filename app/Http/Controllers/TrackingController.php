<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    public function show(string $token)
    {
        $transaction = Transaction::where('tracking_token', $token)
            ->with(['items', 'customer', 'promoUsages.promo'])
            ->firstOrFail();

        return view('tracking.show', [
            'transaction' => $transaction,
            // Hanya nama depan yang ditampilkan di halaman publik
            'firstName' => $transaction->customer ? Str::before($transaction->customer->name, ' ') : null,
        ]);
    }
}