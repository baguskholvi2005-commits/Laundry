<x-layouts.app-layout>
    <x-slot name="header"><h1 class="text-lg font-semibold">Pengaturan</h1></x-slot>

    @php
        $val = fn ($key, $default = '') => old($key, $settings[$key] ?? $default);
        $on = fn ($key, $default = '0') => (string) old($key, $settings[$key] ?? $default) === '1';

        $days = array_map('strval', (array) old('operating_days', explode(',', $settings['operating_days'] ?? '1,2,3,4,5,6,7')));
        $methods = (array) old('payment_methods', explode(',', $settings['payment_methods'] ?? 'cash,transfer,qris,ewallet'));
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $logoUrl = business_logo_url();
    @endphp

    <h1 class="text-3xl font-bold text-gray-900 mb-1">Pengaturan Bisnis</h1>
    <p class="text-gray-500 mb-6">Semua aturan di sini bisa diubah kapan saja tanpa mengubah kode program.</p>

    @if($errors->any())
        <div class="mb-4 bg-danger-50 text-danger-600 rounded-xl px-4 py-3 text-sm max-w-3xl">
            <p class="font-medium mb-1">Pengaturan belum tersimpan:</p>
            <ul class="list-disc ml-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6 max-w-3xl">
        @csrf @method('PUT')

        {{-- Profil Bisnis --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-semibold mb-4">Profil Bisnis</h2>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700">Nama Laundry <span class="text-danger-500">*</span></label>
                    <input type="text" name="business_name" value="{{ $val('business_name', 'Laundry Desa') }}" required class="w-full border-gray-200 rounded-xl mt-1">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Alamat</label>
                    <textarea name="business_address" rows="2" class="w-full border-gray-200 rounded-xl mt-1">{{ $val('business_address') }}</textarea>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Nomor WhatsApp</label>
                    <input type="text" name="business_whatsapp" value="{{ $val('business_whatsapp') }}" placeholder="08xxxxxxxxxx" class="w-full border-gray-200 rounded-xl mt-1">
                    <p class="text-xs text-gray-400 mt-1">Ditulis dengan awalan 08 atau 62, otomatis disimpan sebagai 62.</p>
                </div>

                <div x-data="{ preview: null }">
                    <label class="text-sm font-medium text-gray-700">Logo</label>
                    <div class="flex items-center gap-4 mt-2">
                        <div class="w-20 h-20 rounded-xl border bg-gray-50 flex items-center justify-center overflow-hidden">
                            <template x-if="preview"><img :src="preview" class="max-w-full max-h-full object-contain"></template>
                            <template x-if="!preview">
                                @if($logoUrl)
                                    <img src="{{ $logoUrl }}" class="max-w-full max-h-full object-contain">
                                @else
                                    <span class="text-xs text-gray-400 text-center px-1">Belum ada logo</span>
                                @endif
                            </template>
                        </div>
                        <div class="space-y-2">
                            <label class="cursor-pointer inline-block">
                                <span class="inline-flex items-center border border-gray-200 rounded-xl px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Pilih Logo</span>
                                <input type="file" name="business_logo" accept="image/png,image/jpeg" class="hidden"
                                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                            </label>
                            @if(setting('business_logo'))
                                <label class="flex items-center gap-2 text-xs text-gray-500">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300">
                                    Hapus logo yang sekarang
                                </label>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">PNG atau JPG, maksimal 1 MB. Disarankan PNG berlatar transparan, kira-kira 400 × 400 piksel. Tampil di menu samping, halaman login, nota, dan halaman QR pelanggan.</p>
                </div>
            </div>
        </div>

        {{-- Jam Operasional --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-semibold mb-1">Jam Operasional</h2>
            <p class="text-xs text-gray-400 mb-4">Tampil di nota dan halaman QR pelanggan. Tidak membatasi kasir mencatat transaksi.</p>
            <div class="grid grid-cols-2 gap-4 max-w-sm">
                <div>
                    <label class="text-sm font-medium text-gray-700">Buka</label>
                    <input type="time" name="operating_open" value="{{ $val('operating_open') }}" class="w-full border-gray-200 rounded-xl mt-1">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Tutup</label>
                    <input type="time" name="operating_close" value="{{ $val('operating_close') }}" class="w-full border-gray-200 rounded-xl mt-1">
                </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-4">
                @foreach($dayNames as $n => $name)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="operating_days[]" value="{{ $n }}" class="sr-only peer" {{ in_array((string) $n, $days, true) ? 'checked' : '' }}>
                        <span class="inline-block px-3 py-1.5 rounded-lg border text-sm text-gray-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand-200">{{ $name }}</span>
                    </label>
                @endforeach
            </div>
            <div class="mt-4 max-w-xs">
                <label class="text-sm font-medium text-gray-700">Estimasi Pengerjaan Umum (hari)</label>
                <input type="number" name="work_duration_days" min="1" max="60" value="{{ $val('work_duration_days', 2) }}" required class="w-full border-gray-200 rounded-xl mt-1">
                <p class="text-xs text-gray-400 mt-1">Dipakai kalau layanan tidak punya estimasi sendiri.</p>
            </div>
        </div>

        {{-- Antar-Jemput --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-semibold mb-4">Antar-Jemput dan Ongkir</h2>
            <div class="space-y-3">
                <x-toggle name="pickup_enabled" label="Layanan Jemput" hint="Tampilkan pilihan Jemput di Transaksi Baru" :checked="$on('pickup_enabled', '1')" />
                <x-toggle name="delivery_enabled" label="Layanan Antar" hint="Tampilkan pilihan Antar di Transaksi Baru" :checked="$on('delivery_enabled', '1')" />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Biaya Jemput (Rp)</label>
                        <input type="number" name="pickup_fee" min="0" value="{{ $val('pickup_fee', 0) }}" required class="w-full border-gray-200 rounded-xl mt-1">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Biaya Antar (Rp)</label>
                        <input type="number" name="delivery_fee" min="0" value="{{ $val('delivery_fee', 0) }}" required class="w-full border-gray-200 rounded-xl mt-1">
                    </div>
                </div>

                <x-toggle name="free_ongkir_enabled" label="Gratis Ongkir Otomatis" hint="Berlaku kalau berat laundry memenuhi minimum" :checked="$on('free_ongkir_enabled', '1')" />

                <div class="max-w-xs">
                    <label class="text-sm font-medium text-gray-700">Minimal Berat Gratis Ongkir (kg)</label>
                    <input type="number" step="0.1" name="free_ongkir_min_weight" min="0" value="{{ $val('free_ongkir_min_weight', 5) }}" required class="w-full border-gray-200 rounded-xl mt-1">
                </div>
            </div>
        </div>

        {{-- Nomor Transaksi --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6"
             x-data="{
                 prefix: '{{ strtoupper($val('invoice_prefix', 'LD')) }}',
                 fmt: '{{ $val('invoice_date_format', 'ymd') }}',
                 digits: {{ (int) $val('invoice_digits', 3) }},
                 get example() {
                     const d = new Date();
                     const yy = String(d.getFullYear()).slice(-2);
                     const mm = String(d.getMonth() + 1).padStart(2, '0');
                     const dd = String(d.getDate()).padStart(2, '0');
                     const date = { ymd: yy + mm + dd, ym: yy + mm, y: yy, none: '' }[this.fmt];
                     return [(this.prefix || 'LD').toUpperCase(), date, '1'.padStart(Number(this.digits), '0')].filter(Boolean).join('-');
                 }
             }">
            <h2 class="font-semibold mb-1">Format Nomor Transaksi</h2>
            <p class="text-xs text-gray-400 mb-4">Berlaku untuk transaksi berikutnya. Nomor yang sudah ada tidak berubah.</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700">Awalan</label>
                    <input type="text" name="invoice_prefix" maxlength="6" x-model="prefix" required class="w-full border-gray-200 rounded-xl mt-1 uppercase">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Tanggal</label>
                    <select name="invoice_date_format" x-model="fmt" class="w-full border-gray-200 rounded-xl mt-1">
                        <option value="ymd">Tahun-bulan-hari (urutan mulai 1 tiap hari)</option>
                        <option value="ym">Tahun-bulan (urutan mulai 1 tiap bulan)</option>
                        <option value="y">Tahun saja (urutan mulai 1 tiap tahun)</option>
                        <option value="none">Tanpa tanggal (urutan terus berlanjut)</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Jumlah angka urutan</label>
                    <select name="invoice_digits" x-model="digits" class="w-full border-gray-200 rounded-xl mt-1">
                        <option value="3">3 angka (001)</option>
                        <option value="4">4 angka (0001)</option>
                        <option value="5">5 angka (00001)</option>
                    </select>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-3">Contoh nomor pertama: <span class="font-mono font-semibold text-gray-900" x-text="example"></span></p>
        </div>

        {{-- Pembayaran --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-semibold mb-1">Metode Pembayaran</h2>
            <p class="text-xs text-gray-400 mb-4">Hanya metode yang dicentang yang muncul di kasir.</p>
            <div class="flex flex-wrap gap-2">
                @foreach(payment_method_labels() as $key => $label)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="payment_methods[]" value="{{ $key }}" class="sr-only peer" {{ in_array($key, $methods, true) ? 'checked' : '' }}>
                        <span class="inline-block px-4 py-2 rounded-xl border text-sm text-gray-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand-200">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <div class="mt-4">
                <label class="text-sm font-medium text-gray-700">Info Pembayaran Non-Tunai (opsional)</label>
                <textarea name="payment_info" rows="2" placeholder="Contoh: Transfer BRI 1234-01-000000-50-1 a.n. Laundry Desa"
                          class="w-full border-gray-200 rounded-xl mt-1">{{ $val('payment_info') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Tampil di nota dan di bawah pilihan metode bayar saat kasir memilih selain Tunai.</p>
            </div>
        </div>

        {{-- Promo --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-semibold mb-4">Promo</h2>
            <x-toggle name="promo_stacking_enabled" label="Promo dapat digabungkan"
                      hint="Maksimal 2 promo per transaksi, hanya promo yang diberi centang 'Boleh digabung'. Contoh: Gratis Ongkir + Diskon Hari Raya."
                      :checked="$on('promo_stacking_enabled', '0')" />
        </div>

         {{-- Hak akses kasir --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-semibold mb-4">Hak Akses Kasir</h2>
            <x-toggle name="cashier_can_cancel" label="Kasir boleh membatalkan transaksi"
                      hint="Hanya untuk transaksi yang belum ada pembayarannya dan dibuat dalam 2 jam terakhir. Alasan wajib diisi, dan pembatalan tercatat atas nama kasir di Log Aktivitas."
                      :checked="$on('cashier_can_cancel', '0')" />
        </div>

        {{-- Member --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <h2 class="font-semibold mb-4">Kriteria dan Benefit Member</h2>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Direkomendasikan jadi member setelah (kali transaksi)</label>
                        <input type="number" name="member_min_transactions" min="1" value="{{ $val('member_min_transactions', 5) }}" required class="w-full border-gray-200 rounded-xl mt-1">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">ATAU total belanja minimal (Rp)</label>
                        <input type="number" name="member_min_spent" min="0" value="{{ $val('member_min_spent', 200000) }}" required class="w-full border-gray-200 rounded-xl mt-1">
                    </div>
                </div>
                <div class="max-w-xs">
                    <label class="text-sm font-medium text-gray-700">Diskon otomatis member (%)</label>
                    <input type="number" step="0.1" name="member_discount_percent" min="0" max="100" value="{{ $val('member_discount_percent', 5) }}" required class="w-full border-gray-200 rounded-xl mt-1">
                    <p class="text-xs text-gray-400 mt-1">Diberikan tiap transaksi member, kecuali ada promo yang terpakai.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button class="bg-brand-600 text-white px-6 py-3 rounded-xl text-sm font-medium">Simpan Semua Pengaturan</button>
        </div>
    </form>
</x-layouts.app-layout>