<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof Transaction) {
            $this->log('transaction_created',
                'membuat transaksi ' . $model->code . ' (' . $this->rupiah($model->total) . ')', $model->id);
            return;
        }

        if ($model instanceof Payment) {
            $trx = $model->transaction;

            // Pembayaran awal saat transaksi dibuat sudah tercakup di "membuat transaksi", jadi tidak dicatat dua kali
            if ($trx && abs($trx->created_at->diffInSeconds($model->created_at)) > 5) {
                $this->log('payment_received',
                    'menerima pembayaran ' . $this->rupiah($model->amount) . ' untuk ' . $trx->code, $trx->id);
            }
            return;
        }

        if ($model instanceof Expense) {
            $this->log('expense_created',
                'mencatat pengeluaran ' . $this->label($model->category) . ' ' . $this->rupiah($model->amount));
            return;
        }

        if ($model instanceof User) {
            $this->log('user_changed', 'menambahkan pengguna ' . $model->name);
        }
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Transaction && $model->wasChanged('status')) {
            if ($model->status === 'dibatalkan') {
                $reason = $model->cancel_reason ? ' (alasan: ' . $model->cancel_reason . ')' : '';
                $this->log('status_changed', 'membatalkan transaksi ' . $model->code . $reason, $model->id);
            } else {
                $this->log('status_changed',
                    'mengubah status ' . $model->code . ' menjadi ' . $this->label($model->status), $model->id);
            }
            return;
        }

        if ($model instanceof Expense && $model->wasChanged(['category', 'amount', 'date', 'description'])) {
            $text = $model->wasChanged('amount')
                ? 'mengubah pengeluaran ' . $this->label($model->category) . ' dari '
                    . $this->rupiah($model->getOriginal('amount')) . ' menjadi ' . $this->rupiah($model->amount)
                : 'mengubah data pengeluaran ' . $this->label($model->category);

            $this->log('expense_changed', $text);
            return;
        }

        if ($model instanceof Service) {
            if ($model->wasChanged('price')) {
                $this->log('service_changed', 'mengubah harga layanan ' . $model->name . ' dari '
                    . $this->rupiah($model->getOriginal('price')) . ' menjadi ' . $this->rupiah($model->price));
            } elseif ($model->wasChanged('is_active')) {
                $this->log('service_changed',
                    ($model->is_active ? 'mengaktifkan' : 'menonaktifkan') . ' layanan ' . $model->name);
            }
            return;
        }

        if ($model instanceof Promo && $model->wasChanged('is_active')) {
            $this->log('promo_changed',
                ($model->is_active ? 'mengaktifkan' : 'menonaktifkan') . ' promo ' . $model->name);
            return;
        }

        if ($model instanceof Customer && $model->wasChanged('is_member')) {
            $this->log('customer_changed',
                ($model->is_member ? 'menjadikan ' . $model->name . ' sebagai member' : 'melepas status member ' . $model->name));
        }
    }

    public function deleted(Model $model): void
    {
        $text = match (true) {
            $model instanceof Expense => 'menghapus pengeluaran ' . $this->label($model->category) . ' ' . $this->rupiah($model->amount),
            $model instanceof Customer => 'menghapus pelanggan ' . $model->name,
            $model instanceof Promo => 'menghapus promo ' . $model->name,
            $model instanceof Service => 'menghapus layanan ' . $model->name,
            $model instanceof User => 'menghapus pengguna ' . $model->name,
            default => null,
        };

        if ($text === null) {
            return;
        }

        $event = match (true) {
            $model instanceof Expense => 'expense_changed',
            $model instanceof Customer => 'customer_changed',
            $model instanceof Promo => 'promo_changed',
            $model instanceof Service => 'service_changed',
            default => 'user_changed',
        };

        $this->log($event, $text);
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace('_', ' ', (string) $value));
    }

    private function rupiah($amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    private function log(string $event, string $description, ?int $transactionId = null): void
    {
        ActivityLog::record($event, $description, $transactionId);
    }
}