<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickupRequestItem extends Model
{
    protected $fillable = ['pickup_request_id', 'service_id', 'service_name', 'unit_type', 'qty'];

    protected $casts = ['qty' => 'float'];

    public function request()
    {
        return $this->belongsTo(PickupRequest::class, 'pickup_request_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Teks perkiraan untuk kasir dan pelanggan
    public function getQtyLabelAttribute(): string
    {
        if ($this->unit_type === 'pcs') {
            return $this->qty === null ? 'jumlah belum diisi' : (int) $this->qty . ' pcs';
        }

        if ($this->qty === null) {
            return 'berat belum diketahui';
        }

        return '± ' . rtrim(rtrim(number_format($this->qty, 2, ',', '.'), '0'), ',') . ' kg';
    }
}