<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PickupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PickupRequestController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->get('tab'), ['dikonfirmasi', 'riwayat'], true) ? $request->get('tab') : 'menunggu';

        $query = PickupRequest::with(['customer', 'handler', 'transaction', 'items']);

        if ($tab === 'riwayat') {
            $query->whereIn('status', ['dijemput', 'ditolak', 'dibatalkan'])->latest('updated_at');
        } else {
            $query->where('status', $tab)
                ->orderBy('preferred_date')
                ->orderByRaw("FIELD(time_slot, 'pagi', 'siang', 'sore')")
                ->orderBy('id');
        }

        $counts = PickupRequest::selectRaw('status, COUNT(*) as c')
            ->whereIn('status', ['menunggu', 'dikonfirmasi'])
            ->groupBy('status')
            ->pluck('c', 'status');

        return view('pickup-requests.index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'tab' => $tab,
            'counts' => $counts,
        ]);
    }

    public function confirm(Request $request, PickupRequest $pickupRequest)
    {
        $note = $this->cleanNote($request->input('staff_note'));

        $error = DB::transaction(function () use ($pickupRequest, $note, $request) {
            $pr = PickupRequest::with('customer')->lockForUpdate()->find($pickupRequest->id);

            if ($pr->status !== 'menunggu') {
                return 'Permintaan ini sudah tidak menunggu (status: ' . $pr->status . '). Muat ulang halaman.';
            }

            if ($pr->preferred_date->lt(today())) {
                return 'Tanggal jemput sudah lewat. Tolak permintaan ini dan minta pelanggan membuat yang baru.';
            }

            $pr->forceFill([
                'status' => 'dikonfirmasi',
                'staff_note' => $note,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ])->save();

            ActivityLog::record('pickup_request', 'mengonfirmasi permintaan jemput ' . $pr->customer->name
                . ' (' . $pr->preferred_date->translatedFormat('d M') . ')');

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Permintaan jemput dikonfirmasi.');
    }

    public function reject(Request $request, PickupRequest $pickupRequest)
    {
        $note = $this->cleanNote($request->input('staff_note'));

        if ($note === null) {
            return back()->with('error', 'Tulis alasan supaya pelanggan tahu kenapa permintaannya ditolak.');
        }

        $error = DB::transaction(function () use ($pickupRequest, $note, $request) {
            $pr = PickupRequest::with('customer')->lockForUpdate()->find($pickupRequest->id);

            if (! in_array($pr->status, ['menunggu', 'dikonfirmasi'], true)) {
                return 'Permintaan ini sudah tidak aktif (status: ' . $pr->status . '). Muat ulang halaman.';
            }

            $pr->forceFill([
                'status' => 'ditolak',
                'staff_note' => $note,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ])->save();

            ActivityLog::record('pickup_request', 'menolak permintaan jemput ' . $pr->customer->name);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Permintaan jemput ditolak. Alasanmu tampil di akun pelanggan.');
    }

    private function cleanNote($value): ?string
    {
        $note = trim((string) $value);

        return $note === '' ? null : mb_substr($note, 0, 300);
    }
}