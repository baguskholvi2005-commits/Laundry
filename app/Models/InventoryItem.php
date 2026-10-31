<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = ['name', 'category', 'unit', 'current_stock', 'min_stock'];

    protected $casts = [
        'current_stock' => 'float',
        'min_stock' => 'float',
    ];

    public function logs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function getIsLowAttribute(): bool
    {
        return $this->current_stock <= $this->min_stock;
    }

    // Habis (0), Kritis (sisa setengah ambang atau kurang), Menipis (sisa di bawah atau sama dengan ambang), Aman
    public function getStockStatusAttribute(): string
    {
        $stock = (float) $this->current_stock;
        $min = (float) $this->min_stock;

        if ($stock <= 0) return 'habis';
        if ($stock <= $min / 2) return 'kritis';
        if ($stock <= $min) return 'menipis';

        return 'aman';
    }

    // Penyaring di database, aturannya sama dengan accessor stock_status di atas
    public function scopeWithStockStatus($query, string $status)
    {
        return match ($status) {
            'habis' => $query->where('current_stock', '<=', 0),
            'kritis' => $query->where('current_stock', '>', 0)->whereRaw('current_stock <= min_stock / 2'),
            'menipis' => $query->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'min_stock')
                ->whereRaw('current_stock > min_stock / 2'),
            'aman' => $query->where('current_stock', '>', 0)->whereColumn('current_stock', '>', 'min_stock'),
            'perlu_restock' => $query->whereColumn('current_stock', '<=', 'min_stock'),
            default => $query,
        };
    }

    public function scopeMostUrgentFirst($query)
    {
        return $query->orderByRaw(
            'CASE WHEN current_stock <= 0 THEN 0
                  WHEN current_stock <= min_stock / 2 THEN 1
                  WHEN current_stock <= min_stock THEN 2
                  ELSE 3 END'
        );
    }
}