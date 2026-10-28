<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ActivityLog;

class SettingController extends Controller
{
    public function index()
    {
        $settings = BusinessSetting::pluck('value', 'key');
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:100',
            'business_address' => 'nullable|string|max:255',
            'business_whatsapp' => 'nullable|string|max:25',
            'business_logo' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',

            'operating_open' => 'nullable|required_with:operating_close|date_format:H:i',
            'operating_close' => 'nullable|required_with:operating_open|date_format:H:i|after:operating_open',
            'operating_days' => 'nullable|array',
            'operating_days.*' => 'integer|between:1,7',

            'pickup_fee' => 'required|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'free_ongkir_min_weight' => 'required|numeric|min:0',
            'work_duration_days' => 'required|integer|min:1|max:60',

            'invoice_prefix' => 'required|alpha_num|max:6',
            'invoice_date_format' => 'required|in:ymd,ym,y,none',
            'invoice_digits' => 'required|integer|between:3,5',

            'payment_methods' => 'required|array|min:1',
            'payment_methods.*' => 'in:cash,transfer,qris,ewallet',
            'payment_info' => 'nullable|string|max:500',

            'member_min_transactions' => 'required|integer|min:1',
            'member_min_spent' => 'required|numeric|min:0',
            'member_discount_percent' => 'required|numeric|min:0|max:100',
        ]);

        // Nomor WhatsApp disimpan dalam format 62xxxxxxxxxx
        $wa = preg_replace('/\D/', '', (string) ($data['business_whatsapp'] ?? ''));
        if (str_starts_with($wa, '0')) {
            $wa = '62' . substr($wa, 1);
        }

        $values = [
            'business_name' => $data['business_name'],
            'business_address' => $data['business_address'] ?? '',
            'business_whatsapp' => $wa,

            'operating_open' => $data['operating_open'] ?? '',
            'operating_close' => $data['operating_close'] ?? '',
            'operating_days' => implode(',', collect($data['operating_days'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->all()),

            'pickup_enabled' => $request->boolean('pickup_enabled') ? '1' : '0',
            'delivery_enabled' => $request->boolean('delivery_enabled') ? '1' : '0',
            'pickup_fee' => $data['pickup_fee'],
            'delivery_fee' => $data['delivery_fee'],
            'free_ongkir_enabled' => $request->boolean('free_ongkir_enabled') ? '1' : '0',
            'free_ongkir_min_weight' => $data['free_ongkir_min_weight'],
            'work_duration_days' => $data['work_duration_days'],

            'invoice_prefix' => strtoupper($data['invoice_prefix']),
            'invoice_date_format' => $data['invoice_date_format'],
            'invoice_digits' => $data['invoice_digits'],

            'payment_methods' => implode(',', array_unique($data['payment_methods'])),
            'payment_info' => $data['payment_info'] ?? '',

            'promo_stacking_enabled' => $request->boolean('promo_stacking_enabled') ? '1' : '0',
            'cashier_can_cancel' => $request->boolean('cashier_can_cancel') ? '1' : '0',


            'member_min_transactions' => $data['member_min_transactions'],
            'member_min_spent' => $data['member_min_spent'],
            'member_discount_percent' => $data['member_discount_percent'],
        ];

        // Logo: unggah baru, atau hapus
        $currentLogo = setting('business_logo');

        if ($request->hasFile('business_logo')) {
            if ($currentLogo) {
                Storage::disk('public')->delete($currentLogo);
            }
            $values['business_logo'] = $request->file('business_logo')->store('branding', 'public');
        } elseif ($request->boolean('remove_logo') && $currentLogo) {
            Storage::disk('public')->delete($currentLogo);
            $values['business_logo'] = '';
        }

                // Catat apa saja yang berubah (nama pengaturannya saja, bukan isinya)
        $before = BusinessSetting::pluck('value', 'key');

        foreach ($values as $key => $value) {
            BusinessSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        $names = [
            'business_name' => 'nama laundry', 'business_address' => 'alamat', 'business_whatsapp' => 'nomor WhatsApp',
            'business_logo' => 'logo', 'operating_open' => 'jam buka', 'operating_close' => 'jam tutup',
            'operating_days' => 'hari buka', 'pickup_enabled' => 'layanan jemput', 'delivery_enabled' => 'layanan antar',
            'pickup_fee' => 'biaya jemput', 'delivery_fee' => 'biaya antar', 'free_ongkir_enabled' => 'gratis ongkir otomatis',
            'free_ongkir_min_weight' => 'minimal berat gratis ongkir', 'work_duration_days' => 'estimasi pengerjaan umum',
            'invoice_prefix' => 'awalan nomor transaksi', 'invoice_date_format' => 'format tanggal nomor transaksi',
            'invoice_digits' => 'jumlah angka nomor transaksi', 'payment_methods' => 'metode pembayaran',
            'payment_info' => 'info pembayaran', 'promo_stacking_enabled' => 'penggabungan promo',
            'member_min_transactions' => 'syarat transaksi member', 'member_min_spent' => 'syarat belanja member',
            'member_discount_percent' => 'diskon member', 'cashier_can_cancel' => 'izin kasir membatalkan transaksi',
        ];

        $changed = collect($values)
            ->filter(function ($new, $key) use ($before) {
                $old = (string) ($before[$key] ?? '');
                $new = (string) $new;

                return is_numeric($old) && is_numeric($new) ? (float) $old !== (float) $new : $old !== $new;
            })
            ->keys()
            ->map(fn ($key) => $names[$key] ?? $key);

        if ($changed->isNotEmpty()) {
            ActivityLog::record('settings_changed', 'mengubah pengaturan: ' . $changed->join(', '));
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}