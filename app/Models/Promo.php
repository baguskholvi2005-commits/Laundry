<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    use HasFactory;

    public const STATUSES = ['aktif', 'habis_kuota', 'belum_dimulai', 'kadaluarsa', 'nonaktif'];

    protected $fillable = [
        'name', 'description', 'type', 'value', 'value_type', 'min_transaction',
        'min_weight', 'start_date', 'end_date', 'start_time', 'end_time',
        'quota', 'used_count', 'is_active', 'stackable',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'stackable' => 'boolean',
    ];

    public function usages()
    {
        return $this->hasMany(PromoUsage::class);
    }

    /**
     * Penyaring status di database, aturannya sama persis dengan accessor status_label di bawah.
     */
    public function scopeWithStatus($query, string $status)
    {
        $today = today()->toDateString();

        return match ($status) {
            'nonaktif' => $query->where('is_active', false),
            'belum_dimulai' => $query->where('is_active', true)->whereDate('start_date', '>', $today),
            'kadaluarsa' => $query->where('is_active', true)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '<', $today),
            'habis_kuota' => $query->where('is_active', true)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->whereNotNull('quota')
                ->whereColumn('used_count', '>=', 'quota'),
            'aktif' => $query->where('is_active', true)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->where(fn ($q) => $q->whereNull('quota')->orWhereColumn('used_count', '<', 'quota')),
            default => $query,
        };
    }

    // Urutan tampil: aktif dulu, lalu habis kuota, belum dimulai, kadaluarsa, nonaktif
    public function scopeOrderByStatus($query)
    {
        $today = today()->toDateString();

        return $query->orderByRaw(
            'CASE WHEN is_active = 0 THEN 4
                  WHEN start_date > ? THEN 2
                  WHEN end_date < ? THEN 3
                  WHEN quota IS NOT NULL AND used_count >= quota THEN 1
                  ELSE 0 END',
            [$today, $today]
        );
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active) return 'nonaktif';

        $today = today();
        if ($today->lt($this->start_date)) return 'belum_dimulai';
        if ($today->gt($this->end_date)) return 'kadaluarsa';
        if ($this->quota !== null && $this->used_count >= $this->quota) return 'habis_kuota';

        return 'aktif';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'gratis_ongkir' => 'Gratis Ongkir',
            'diskon_persen' => 'Diskon Persen',
            'diskon_nominal' => 'Potongan Harga',
            'berdasarkan_berat' => 'Promo Berat',
            'minimal_transaksi' => 'Min. Belanja',
            'member' => 'Khusus Member',
            default => (string) $this->type,
        };
    }

    public function getValueTextAttribute(): string
    {
        if ($this->type === 'gratis_ongkir') {
            return 'Gratis Ongkir';
        }

        $value = (float) $this->value;

        return $this->value_type === 'persen'
            ? rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%'
            : 'Rp ' . number_format($value, 0, ',', '.');
    }

    /**
     * Alasan promo tidak bisa dipakai. null = lolos semua syarat.
     */
    public function ineligibleReason(float $subtotal, float $totalWeight, ?Customer $customer = null): ?string
    {
        if ($this->status_label !== 'aktif') {
            return match ($this->status_label) {
                'belum_dimulai' => 'Promo ini belum dimulai.',
                'kadaluarsa' => 'Promo ini sudah tidak berlaku.',
                'habis_kuota' => 'Promo sudah mencapai batas penggunaan.',
                default => 'Promo sedang dinonaktifkan.',
            };
        }

        if ($this->type === 'member' && ! ($customer && $customer->is_member)) {
            return 'Promo ini khusus pelanggan member.';
        }

        if ($this->start_time && $this->end_time) {
            $now = now()->format('H:i:s');
            if ($now < $this->start_time || $now > $this->end_time) {
                return 'Promo ini hanya berlaku pukul ' . substr($this->start_time, 0, 5) . ' - ' . substr($this->end_time, 0, 5) . '.';
            }
        }

        if ($this->min_transaction && $subtotal < $this->min_transaction) {
            return 'Minimal transaksi Rp ' . number_format($this->min_transaction, 0, ',', '.') . ' belum terpenuhi.';
        }

        if ($this->min_weight && $totalWeight < $this->min_weight) {
            return 'Minimal berat ' . (float) $this->min_weight . ' kg belum terpenuhi.';
        }

        return null;
    }

    public function isUsable(float $subtotal, float $totalWeight, ?Customer $customer = null): bool
    {
        return $this->ineligibleReason($subtotal, $totalWeight, $customer) === null;
    }

    public function calculateDiscount(float $subtotal): float
    {
        $value = (float) $this->value;

        // Gratis ongkir tidak memotong subtotal, yang digratiskan adalah ongkirnya
        if ($this->type === 'gratis_ongkir' || $value <= 0) {
            return 0;
        }

        $discount = $this->value_type === 'persen'
            ? round($subtotal * ($value / 100))
            : $value;

        // Potongan tidak boleh lebih besar dari subtotal
        return min($discount, $subtotal);
    }
}