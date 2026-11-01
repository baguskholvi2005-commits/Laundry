<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PickupRequest extends Model
{
    public const TIME_SLOTS = [
        'pagi' => 'Pagi',
        'siang' => 'Siang',
        'sore' => 'Sore',
    ];

    // [judul, keterangan untuk pelanggan]
    public const MODES = [
        'jemput' => ['Jemput saja', 'Cucianmu dijemput. Setelah selesai, kamu ambil sendiri di toko.'],
        'jemput_antar' => ['Jemput + antar', 'Cucianmu dijemput, lalu diantar kembali ke alamatmu setelah selesai.'],
    ];

    // Kolom status dan penanganan sengaja tidak masuk $fillable, hanya diubah lewat forceFill di kode yang berwenang
    protected $fillable = ['customer_id', 'service_mode','payment_method', 'address', 'preferred_date', 'time_slot', 'notes'];

    protected $casts = [
        'preferred_date' => 'date',
        'handled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function items()
    {
        return $this->hasMany(PickupRequestItem::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['menunggu', 'dikonfirmasi']);
    }

    public function getSlotLabelAttribute(): string
    {
        return self::TIME_SLOTS[$this->time_slot] ?? $this->time_slot;
    }

    public function getModeLabelAttribute(): string
    {
        return self::MODES[$this->service_mode][0] ?? (string) $this->service_mode;
    }

    public function getWantsDeliveryAttribute(): bool
    {
        return $this->service_mode === 'jemput_antar';
    }

        public function getPaymentLabelAttribute(): string
    {
        return payment_method_labels()[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    // Tunai dibayar saat cucian diantar atau diambil. Selain itu dibayar setelah nota dikirim lewat WhatsApp
    public function getPaymentHintAttribute(): string
    {
        return $this->payment_method === 'cash'
            ? 'Dibayar tunai saat cucian ' . ($this->wants_delivery ? 'diantar kembali' : 'diambil di toko') . '.'
            : 'Dibayar setelah nota dikirim lewat WhatsApp.';
    }

    // Toko buka pada hari ini? Kalau hari buka belum diatur, dianggap buka setiap hari
    public static function isOpenOn(Carbon $date): bool
    {
        $days = array_filter(explode(',', (string) setting('operating_days', '')));

        if (empty($days)) {
            return true;
        }

        return in_array((string) $date->isoWeekday(), $days, true);
    }

    // Tanggal yang bisa dipilih pelanggan: 7 hari ke depan, hanya hari buka
    public static function availableDates(): Collection
    {
        $close = (string) setting('operating_close', '');
        $closedToday = $close !== '' && now()->format('H:i') >= substr($close, 0, 5);

        return collect(range(0, 6))
            ->map(fn ($i) => today()->addDays($i))
            ->filter(fn ($d) => static::isOpenOn($d))
            ->values();
    }
}