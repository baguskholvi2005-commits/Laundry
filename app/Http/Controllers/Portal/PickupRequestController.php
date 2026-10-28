<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\PickupRequest;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PickupRequestController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user('customer');

        $dateOptions = PickupRequest::availableDates()->mapWithKeys(fn ($d) => [
            $d->toDateString() => ($d->isToday() ? 'Hari ini' : ($d->isTomorrow() ? 'Besok' : $d->translatedFormat('l')))
                . ', ' . $d->translatedFormat('d M'),
        ]);

        // Daftar layanan dibaca langsung dari layanan yang aktif, jadi layanan baru otomatis ikut muncul
        $services = Service::active()
            ->orderByRaw("FIELD(type, 'kiloan', 'satuan')")
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'price', 'duration_days']);

        return view('portal.pickup', [
            'customer' => $customer,
            'enabled' => (bool) setting('pickup_enabled'),
            'deliveryEnabled' => (bool) setting('delivery_enabled'),
            'services' => $services,
            'active' => $customer->pickupRequests()->active()->with('items')->latest()->first(),
            'history' => $customer->pickupRequests()->latest()->take(8)->get(),
            'dateOptions' => $dateOptions,
        ]);
    }

    public function store(Request $request)
    {
        $customer = $request->user('customer');

        if (! setting('pickup_enabled')) {
            return redirect()->route('portal.pickup')->with('error', 'Layanan jemput sedang tidak tersedia.');
        }

        $modes = setting('delivery_enabled') ? ['jemput', 'jemput_antar'] : ['jemput'];
        $dates = PickupRequest::availableDates()->map->toDateString()->all();

        $data = $request->validate([
            'service_mode' => ['required', Rule::in($modes)],
            'service_ids' => 'required|array|min:1|max:30',
            'service_ids.*' => 'integer|distinct',
            'address' => 'required|string|min:8|max:255',
            'preferred_date' => ['required', Rule::in($dates)],
            'time_slot' => ['required', Rule::in(array_keys(PickupRequest::TIME_SLOTS))],
            'notes' => 'nullable|string|max:300',
        ], [
            'service_mode.required' => 'Pilih jenis layanan: jemput saja atau jemput + antar.',
            'service_mode.in' => 'Jenis layanan yang dipilih tidak tersedia.',
            'service_ids.required' => 'Pilih minimal satu layanan.',
            'service_ids.min' => 'Pilih minimal satu layanan.',
            'address.required' => 'Alamat jemput wajib diisi.',
            'address.min' => 'Alamat terlalu singkat. Tulis lengkap: jalan, RT/RW, dan patokan.',
            'preferred_date.required' => 'Pilih tanggal jemput.',
            'preferred_date.in' => 'Tanggal yang dipilih tidak tersedia. Pilih tanggal lain.',
            'time_slot.required' => 'Pilih waktu jemput.',
            'time_slot.in' => 'Waktu jemput tidak valid.',
        ]);

        // Layanan dicek ulang saat disimpan, kalau pemilik baru saja menonaktifkannya
        $services = Service::active()->whereIn('id', $data['service_ids'])->get()->keyBy('id');

        if ($services->count() !== count($data['service_ids'])) {
            throw ValidationException::withMessages([
                'service_ids' => 'Ada layanan yang sudah tidak tersedia. Muat ulang halaman, lalu pilih lagi.',
            ]);
        }

        $items = [];

        foreach ($data['service_ids'] as $id) {
            $service = $services->get((int) $id);

            $raw = $request->input('qty.' . $service->id);
            $raw = is_string($raw) ? trim(str_replace(',', '.', $raw)) : $raw;

            if ($service->type === 'satuan') {
                $number = is_numeric($raw) ? (float) $raw : null;

                if ($number === null || $number != floor($number) || $number < 1 || $number > 99) {
                    throw ValidationException::withMessages([
                        'qty.' . $service->id => 'Isi jumlah ' . $service->name . ' (bilangan bulat 1 sampai 99).',
                    ]);
                }

                $qty = (int) $number;
            } elseif ($raw === null || $raw === '') {
                $qty = null; // berat belum diketahui, kasir yang menimbang
            } else {
                $number = is_numeric($raw) ? (float) $raw : null;

                if ($number === null || $number < 0.5 || $number > 100) {
                    throw ValidationException::withMessages([
                        'qty.' . $service->id => 'Perkiraan berat ' . $service->name . ' harus 0,5 sampai 100 kg, atau kosongkan kalau belum tahu.',
                    ]);
                }

                $qty = round($number, 1);
            }

            $items[] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'unit_type' => $service->type === 'kiloan' ? 'kg' : 'pcs',
                'qty' => $qty,
            ];
        }

        $created = DB::transaction(function () use ($customer, $data, $items) {
            // Kunci baris pelanggan supaya dua kali tekan tombol tidak membuat dua permintaan
            Customer::whereKey($customer->id)->lockForUpdate()->first();

            if (PickupRequest::where('customer_id', $customer->id)->active()->exists()) {
                return null;
            }

            $pickupRequest = PickupRequest::create([
                'customer_id' => $customer->id,
                'service_mode' => $data['service_mode'],
                'address' => $data['address'],
                'preferred_date' => $data['preferred_date'],
                'time_slot' => $data['time_slot'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $pickupRequest->items()->create($item);
            }

            return $pickupRequest;
        });

        if (! $created) {
            return redirect()->route('portal.pickup')
                ->with('error', 'Kamu masih punya permintaan jemput yang aktif. Batalkan dulu kalau mau membuat yang baru.');
        }

        ActivityLog::record(
            'pickup_request',
            'menerima permintaan ' . strtolower(PickupRequest::MODES[$data['service_mode']][0]) . ' dari ' . $customer->name,
            null,
            true
        );

        return redirect()->route('portal.pickup')
            ->with('success', 'Permintaan terkirim. Kasir akan mengonfirmasi, cek statusnya di halaman ini.');
    }

    public function cancel(Request $request, int $id)
    {
        $customer = $request->user('customer');

        // Hanya permintaan milik pelanggan yang sedang masuk
        $own = $customer->pickupRequests()->whereKey($id)->firstOrFail();

        $error = DB::transaction(function () use ($own, $customer) {
            $pr = PickupRequest::lockForUpdate()->find($own->id);

            if (! in_array($pr->status, ['menunggu', 'dikonfirmasi'], true)) {
                return 'Permintaan ini sudah tidak bisa dibatalkan.';
            }

            $pr->forceFill(['status' => 'dibatalkan'])->save();

            ActivityLog::record('pickup_request', $customer->name . ' membatalkan permintaan jemput', null, true);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('portal.pickup')->with('success', 'Permintaan dibatalkan.');
    }
}