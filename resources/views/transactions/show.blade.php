<x-layouts.app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold">Detail Transaksi</h1></x-slot>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $transaction->code }}</h1>
            <p class="text-gray-500 text-sm">{{ $transaction->created_at->translatedFormat('d F Y, H:i') }} WIB</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('transactions.nota', $transaction) }}" target="_blank"
               class="border rounded-xl px-4 py-2 text-sm">🖨️ Cetak Nota</a>
            <a href="{{ route('transactions.nota.download', $transaction) }}"
               class="border rounded-xl px-4 py-2 text-sm">⬇ Download PDF</a>
            <a href="{{ route('transactions.create', ['from' => $transaction->id]) }}"
               class="border rounded-xl px-4 py-2 text-sm" title="Buat transaksi baru dengan isi yang sama">⧉ Salin ke Transaksi Baru</a>

             @if($transaction->canBeCancelledBy(auth()->user()))
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open"
                            class="border border-danger-200 text-danger-600 rounded-xl px-4 py-2 text-sm">Batalkan</button>

                    <form method="POST" action="{{ route('transactions.cancel', $transaction) }}"
                          x-show="open" x-cloak @click.outside="open = false"
                          onsubmit="return confirm('Batalkan transaksi ini? Tugas antar-jemput yang belum selesai ikut dibatalkan dan kuota promo serta paket dikembalikan.')"
                          class="absolute right-0 mt-2 z-20 w-72 max-w-[85vw] bg-white border rounded-2xl shadow-lg p-4 space-y-3">
                        @csrf @method('PATCH')
                        <label class="text-sm font-medium text-gray-700">Alasan pembatalan <span class="text-danger-500">*</span></label>
                        <input type="text" name="reason" required minlength="3" maxlength="255"
                               placeholder="Contoh: salah input berat"
                               class="w-full border-gray-200 rounded-xl text-sm">
                        <p class="text-xs text-gray-400">Pembatalan tercatat atas namamu di Log Aktivitas.</p>
                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="open = false" class="border rounded-xl px-3 py-2 text-sm">Tutup</button>
                            <button class="bg-danger-600 text-white rounded-xl px-3 py-2 text-sm font-medium">Batalkan Transaksi</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

        @if($transaction->status === 'dibatalkan')
        <div class="bg-danger-50 text-danger-600 rounded-xl px-4 py-3 text-sm mb-6">
            <p class="font-medium">Transaksi ini dibatalkan.</p>
            <p>
                {{ $transaction->canceller ? 'Oleh ' . $transaction->canceller->name : 'Pembatal tidak tercatat' }}
                @if($transaction->cancelled_at)
                    pada {{ $transaction->cancelled_at->translatedFormat('d M Y, H:i') }} WIB
                @endif
            </p>
            @if($transaction->cancel_reason)
                <p>Alasan: {{ $transaction->cancel_reason }}</p>
            @endif
        </div>
    @endif

    {{-- Progress Tracker --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
        <x-progress-tracker :status="$transaction->status" />

        @if(!in_array($transaction->status, ['selesai', 'dibatalkan']))
        <form method="POST" action="{{ route('transactions.updateStatus', $transaction) }}"
              class="flex gap-2 mt-5 pt-4 border-t"
              @if($transaction->remaining > 0)
              onsubmit="return this.status.value !== 'selesai' || confirm('Tagihan belum lunas (sisa Rp {{ number_format($transaction->remaining, 0, ',', '.') }}). Tetap tandai Selesai?')"
              @endif>
            @csrf @method('PATCH')
            <select name="status" class="flex-1 border-gray-200 rounded-xl text-sm">
                @foreach(\App\Models\Transaction::STATUS_FLOW as $s)
                    <option value="{{ $s }}" {{ $transaction->status === $s ? 'selected' : '' }}>
                        {{ ucwords(str_replace('_', ' ', $s)) }}
                    </option>
                @endforeach
            </select>
            <button class="bg-brand-600 text-white px-5 rounded-xl text-sm font-medium">Update Status</button>
        </form>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kiri --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-5"
                 x-data='{
                     open: false,
                     query: "",
                     results: [],
                     customerId: @js($transaction->customer_id),
                     customerName: @js($transaction->customer?->name ?? "Pelanggan Umum"),
                     async search() {
                         if (!this.query) { this.results = []; return; }
                         const res = await fetch("/customers/search?q=" + encodeURIComponent(this.query));
                         this.results = await res.json();
                     }
                 }'>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold">Informasi Pelanggan</h3>
                    @if($transaction->status !== 'dibatalkan')
                        <button type="button" @click="open = !open" class="text-xs text-brand-600">Ubah pelanggan / catatan</button>
                    @endif
                </div>

                @if($transaction->customer)
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full flex items-center justify-center font-semibold
                            {{ $transaction->customer->is_member ? 'bg-amber-100 text-amber-700' : 'bg-brand-50 text-brand-600' }}">
                            {{ strtoupper(substr($transaction->customer->name, 0, 1)) }}
                        </span>
                        <div>
                            <a href="{{ route('customers.show', $transaction->customer) }}" class="font-medium text-brand-600">
                                {{ $transaction->customer->name }}
                            </a>
                            <p class="text-sm text-gray-500">{{ $transaction->customer->phone ?? '-' }}</p>
                        </div>
                        @if($transaction->customer->is_member)
                            <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full ml-auto">⭐ Member</span>
                        @endif
                    </div>
                @else
                    <p class="text-gray-500 text-sm">Pelanggan Umum (tanpa data terdaftar)</p>
                @endif

                @if($transaction->notes)
                    <p class="text-sm text-gray-500 mt-3 whitespace-pre-line"><span class="text-gray-400">Catatan:</span> {{ $transaction->notes }}</p>
                @endif

                @if($transaction->status !== 'dibatalkan')
                <form method="POST" action="{{ route('transactions.updateInfo', $transaction) }}" x-show="open" x-cloak
                      class="mt-4 pt-4 border-t space-y-3">
                    @csrf @method('PATCH')
                    <input type="hidden" name="customer_id" :value="customerId ?? ''">

                    <div class="relative">
                        <label class="text-sm font-medium text-gray-700">Pelanggan</label>
                        <div class="flex gap-2 mt-1">
                            <input type="text" x-model="query" @input.debounce.400ms="search()" placeholder="Cari nama atau No. HP..."
                                   class="flex-1 border-gray-200 rounded-xl text-sm">
                            <button type="button" @click="customerId = null; customerName = 'Pelanggan Umum'; results = []"
                                    class="border rounded-xl px-3 text-xs">Pelanggan Umum</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Dipilih: <span class="font-medium" x-text="customerName"></span></p>

                        <div x-show="results.length" x-cloak
                             class="absolute z-10 left-0 right-0 bg-white border rounded-xl shadow-lg mt-1 max-h-56 overflow-y-auto">
                            <template x-for="c in results" :key="c.id">
                                <button type="button" @click="customerId = c.id; customerName = c.name; results = []; query = ''"
                                        class="w-full text-left px-4 py-2 hover:bg-gray-50 border-b last:border-0 text-sm">
                                    <span x-text="c.name"></span>
                                    <span class="text-xs text-gray-400" x-text="c.phone"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Catatan</label>
                        <textarea name="notes" rows="2" class="w-full border-gray-200 rounded-xl mt-1 text-sm">{{ old('notes', $transaction->notes) }}</textarea>
                    </div>

                    <p class="text-xs text-gray-400">Mengubah pelanggan tidak menghitung ulang diskon dan total. Kalau angkanya yang salah, pakai "Salin ke Transaksi Baru" lalu batalkan transaksi ini.</p>

                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="open = false" class="border rounded-xl px-4 py-2 text-sm">Batal</button>
                        <button class="bg-brand-600 text-white rounded-xl px-4 py-2 text-sm font-medium">Simpan</button>
                    </div>
                </form>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h3 class="font-semibold mb-3">Daftar Layanan</h3>
                @foreach($transaction->items as $item)
                    <div class="flex justify-between items-center py-2.5 border-b last:border-0 text-sm">
                        <div>
                            <p class="font-medium">{{ $item->service_name }}</p>
                            <p class="text-gray-400 text-xs">{{ (float) $item->qty }} {{ $item->unit_type }} × Rp {{ number_format($item->price_per_unit, 0, ',', '.') }}</p>
                        </div>
                        <span class="font-medium">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            @if($transaction->pickupDeliveries->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h3 class="font-semibold mb-3">Antar-Jemput</h3>
                @foreach($transaction->pickupDeliveries as $task)
                    <div class="flex justify-between items-center py-2 text-sm">
                        <a href="{{ route('pickups.show', $task) }}" class="text-brand-600">
                            {{ $task->type === 'pickup' ? 'Jemput' : 'Antar' }}
                        </a>
                        <x-badge :status="$task->status" />
                    </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Kanan --}}
        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <h3 class="font-semibold mb-4">Ringkasan Pembayaran</h3>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between text-danger-600">
                        <span>Diskon</span>
                        <span>- Rp {{ number_format($transaction->discount, 0, ',', '.') }}</span>
                    </div>
                    @if($transaction->promoUsages->isNotEmpty())
                        <p class="text-xs text-gray-400">Promo: {{ $transaction->promoUsages->map(fn ($u) => $u->promo->name ?? '-')->join(', ') }}</p>
                    @elseif($transaction->discount > 0)
                        <p class="text-xs text-gray-400">Diskon member</p>
                    @endif
                    <div class="flex justify-between"><span class="text-gray-500">Ongkir</span><span>Rp {{ number_format($transaction->delivery_fee, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between font-bold text-base pt-2 border-t">
                        <span>Total</span><span class="text-brand-600">Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between pt-2"><span class="text-gray-500">Sudah Dibayar</span><span>Rp {{ number_format($transaction->paid_amount, 0, ',', '.') }}</span></div>
                    @if($transaction->status !== 'dibatalkan' && $transaction->remaining > 0)
                        <div class="flex justify-between text-danger-600 font-medium"><span>Sisa Tagihan</span><span>Rp {{ number_format($transaction->remaining, 0, ',', '.') }}</span></div>
                    @endif
                </div>

                <div class="mt-3"><x-badge :status="$transaction->payment_status" /></div>

                @if($transaction->payment_status !== 'lunas' && $transaction->status !== 'dibatalkan')
                <form method="POST" action="{{ route('payments.store', $transaction) }}" class="mt-4 pt-4 border-t space-y-2">
                    @csrf
                    <label class="text-sm font-medium text-gray-700">Terima Pembayaran</label>
                    <input type="number" name="amount" min="1" required placeholder="Jumlah diterima"
                           class="w-full border-gray-200 rounded-xl text-sm">
                    @error('amount') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    <select name="method" class="w-full border-gray-200 rounded-xl text-sm">
                        @foreach(payment_methods() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="w-full bg-brand-600 text-white rounded-xl py-2.5 text-sm font-medium">Catat Pembayaran</button>
                    <p class="text-xs text-gray-400">Kalau uang yang diterima lebih besar dari sisa tagihan, selisihnya ditampilkan sebagai kembalian.</p>
                </form>
                @endif

                @if($transaction->customer && $transaction->customer->phone)
                    @php
                        $phone = preg_replace('/^0/', '62', preg_replace('/\D/', '', $transaction->customer->phone));
                        $trackUrl = route('tracking.show', $transaction->tracking_token);
                        $name = $transaction->customer->name;
                        $message = in_array($transaction->status, ['siap_diambil', 'selesai'])
                            ? "Halo {$name}, laundry dengan nomor transaksi {$transaction->code} sudah selesai dan dapat diambil. Total Rp " . number_format($transaction->total, 0, ',', '.') . "."
                            : "Halo {$name}, transaksi {$transaction->code} sedang kami proses. Total Rp " . number_format($transaction->total, 0, ',', '.') . ", sisa pembayaran Rp " . number_format($transaction->remaining, 0, ',', '.') . ".";
                        $message .= " Cek status laundry: {$trackUrl}";
                    @endphp
                    <a href="https://wa.me/{{ $phone }}?text={{ urlencode($message) }}" target="_blank"
                       class="mt-3 w-full border border-success-200 text-success-600 rounded-xl py-2.5 text-sm font-medium flex items-center justify-center gap-2">
                        💬 Kirim WhatsApp
                    </a>
                @endif
            </div>

            @if($transaction->status !== 'dibatalkan')
            <div class="bg-white rounded-2xl border border-gray-100 p-5 text-center">
                <h3 class="font-semibold text-sm mb-3">QR Code Tracking</h3>
                <div class="flex justify-center">
                    {!! QrCode::size(160)->generate(route('tracking.show', $transaction->tracking_token)) !!}
                </div>
                <p class="text-xs text-gray-400 mt-2">Pelanggan bisa scan untuk cek status tanpa install app</p>
                <a href="{{ route('tracking.show', $transaction->tracking_token) }}" target="_blank"
                   class="text-xs text-brand-600 mt-1 inline-block break-all">
                    {{ route('tracking.show', $transaction->tracking_token) }}
                </a>
            </div>
            @endif
        </div>
    </div>
</x-layouts.app-layout>