<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    public const STATUS_FLOW = [
        'menunggu', 'diproses', 'dicuci', 'dikeringkan',
        'disetrika', 'siap_diambil', 'selesai',
    ];

    // Batas waktu kasir boleh membatalkan transaksi (kalau diizinkan pemilik)
    public const CASHIER_CANCEL_HOURS = 2;

    protected $fillable = [
        'code', 'tracking_token', 'customer_id', 'cashier_id', 'subtotal', 'discount',
        'delivery_fee', 'total', 'paid_amount', 'payment_status',
        'payment_method', 'status', 'cancelled_by', 'cancelled_at', 'cancel_reason',
        'promo_id', 'use_pickup', 'use_delivery', 'estimated_done_at', 'notes',
    ];

    protected $casts = [
        'use_pickup' => 'boolean',
        'use_delivery' => 'boolean',
        'estimated_done_at' => 'date',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (Transaction $t) {
            $t->code = $t->code ?: generate_transaction_code();
            $t->tracking_token = $t->tracking_token ?: static::newTrackingToken();
        });
    }

    public static function newTrackingToken(): string
    {
        do {
            $token = Str::lower(Str::random(16));
        } while (static::where('tracking_token', $token)->exists());

        return $token;
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('status', '!=', 'dibatalkan');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function pickupDeliveries()
    {
        return $this->hasMany(PickupDelivery::class);
    }

    public function promoUsages()
    {
        return $this->hasMany(PromoUsage::class);
    }

    public function getRemainingAttribute(): float
    {
        return max(0, $this->total - $this->paid_amount);
    }

    public function getChangeAttribute(): float
    {
        return max(0, $this->paid_amount - $this->total);
    }

    /**
     * Pemilik: boleh selama belum selesai atau dibatalkan.
     * Kasir: hanya kalau diizinkan di Pengaturan, belum ada pembayaran, dan masih dalam batas waktu.
     */
    public function canBeCancelledBy(?User $user): bool
    {
        if (! $user || in_array($this->status, ['selesai', 'dibatalkan'], true)) {
            return false;
        }

        if ($user->hasRole('pemilik')) {
            return true;
        }

        if (! $user->hasRole('kasir') || ! setting('cashier_can_cancel')) {
            return false;
        }

        return (float) $this->paid_amount <= 0
            && $this->created_at->gte(now()->subHours(self::CASHIER_CANCEL_HOURS));
    }
}