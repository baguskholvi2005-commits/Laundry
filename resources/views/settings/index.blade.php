<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Pengaturan</span></x-slot>

    @php

        $val = fn ($key, $default = '') => old($key, $settings[$key] ?? $default);
        $on = fn ($key, $default = '0') => (string) old($key, $settings[$key] ?? $default) === '1';

        $days = array_map('strval', (array) old('operating_days', explode(',', $settings['operating_days'] ?? '1,2,3,4,5,6,7')));
        $methods = (array) old('payment_methods', explode(',', $settings['payment_methods'] ?? 'cash,transfer,qris,ewallet'));
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $methodHints = [
            'cash' => 'Uang tunai diterima langsung oleh kasir.',
            'transfer' => 'Pelanggan transfer ke rekening toko.',
            'qris' => 'Pelanggan memindai kode QRIS toko.',
            'ewallet' => 'OVO, GoPay, DANA, dan sejenisnya.',
        ];
        $logoUrl = business_logo_url();

        $tabs = [
            'profil' => '1. Profil Toko & Kontak',
            'tarif' => '2. Tarif & Antar-Jemput',
            'nota' => '3. Nota & Nomor Transaksi',
            'bayar' => '4. Pembayaran',
            'member' => '5. Member & Hak Akses',
        ];

        // Kalau ada isian yang salah, buka tab tempat kesalahan itu
        $fieldTab = fn (string $field) => match (true) {
            Str::startsWith($field, ['business_', 'operating_']) => 'profil',
            Str::startsWith($field, ['pickup_', 'delivery_', 'free_ongkir', 'work_duration']) => 'tarif',
            Str::startsWith($field, ['invoice_', 'nota_']) => 'nota',
            Str::startsWith($field, ['payment_']) => 'bayar',
            default => 'member',
        };

        $startTab = $errors->any()
            ? $fieldTab((string) collect($errors->keys())->first())
            : (array_key_exists((string) session('tab'), $tabs) ? session('tab') : 'profil');

        $card = 'bg-white rounded-2xl border border-gray-100 p-6';
        $input = 'w-full border-gray-200 rounded-xl mt-1 bg-gray-50 focus:bg-white';
    @endphp

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs text-gray-400">Sistem <span class="mx-1">/</span> Pengaturan usaha dan operasional toko</p>
            <h1 class="text-3xl font-bold text-gray-900">Pengaturan Toko &amp; POS</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('activity-logs.index', ['group' => 'akses']) }}"
               class="border bg-white rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Riwayat Perubahan</a>
            <button type="submit" form="settings-form"
                    class="bg-brand-600 text-white rounded-xl px-5 py-2.5 text-sm font-semibold hover:bg-brand-700">Simpan Perubahan</button>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 bg-danger-50 text-danger-600 rounded-xl px-4 py-3 text-sm">
            <p class="font-medium mb-1">Pengaturan belum tersimpan:</p>
            <ul class="list-disc ml-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form id="settings-form" method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" novalidate
          x-data="{ tab: '{{ $startTab }}' }">
        @csrf @method('PUT')
        <input type="hidden" name="active_tab" :value="tab">

        {{-- Tab --}}
        <div class="flex gap-2 overflow-x-auto bg-white rounded-2xl border border-gray-100 p-2 mb-5">
            @foreach($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                        class="rounded-xl px-4 py-2.5 text-sm font-medium whitespace-nowrap">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ============ 1. PROFIL ============ --}}
        <div x-show="tab === 'profil'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Identitas outlet</h2>
                <p class="text-xs text-gray-400 mb-4">Tampil di kop nota, halaman login, dan halaman status pelanggan.</p>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama laundry <span class="text-danger-500">*</span></label>
                        <input type="text" name="business_name" value="{{ $val('business_name', 'Laundry Desa') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Slogan di kop nota</label>
                        <input type="text" name="business_tagline" value="{{ $val('business_tagline') }}" maxlength="120"
                               placeholder="Contoh: Cucian bersih, rapi, wangi" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nomor WhatsApp toko</label>
                        <input type="text" name="business_whatsapp" value="{{ $val('business_whatsapp') }}" placeholder="08xxxxxxxxxx" class="{{ $input }}">
                        <p class="text-xs text-gray-400 mt-1">Ditulis dengan awalan 08 atau 62, otomatis disimpan sebagai 62.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Alamat lengkap</label>
                        <textarea name="business_address" rows="3" placeholder="Jalan, RT/RW, patokan" class="{{ $input }}">{{ $val('business_address') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="{{ $card }}" x-data="{ preview: null }">
                    <h2 class="font-semibold text-gray-900 mb-4">Logo</h2>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-xl border bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
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
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Hapus logo yang sekarang
                                </label>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">PNG atau JPG, maksimal 1 MB. Disarankan PNG berlatar transparan, kira-kira 400 × 400 piksel.</p>
                </div>

                <div class="{{ $card }}">
                    <h2 class="font-semibold text-gray-900">Jam operasional</h2>
                    <p class="text-xs text-gray-400 mb-4">Tampil di nota dan halaman pelanggan. Juga menentukan hari yang bisa dipilih untuk permintaan jemput. Tidak membatasi kasir mencatat transaksi.</p>
                    <div class="grid grid-cols-2 gap-4 max-w-sm">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Buka</label>
                            <input type="time" name="operating_open" value="{{ $val('operating_open') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Tutup</label>
                            <input type="time" name="operating_close" value="{{ $val('operating_close') }}" class="{{ $input }}">
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
                </div>
            </div>
        </div>

        {{-- ============ 2. TARIF DAN ANTAR-JEMPUT ============ --}}
        <div x-show="tab === 'tarif'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Antar-jemput dan ongkir</h2>
                <p class="text-xs text-gray-400 mb-4">Berlaku di kasir dan di permintaan jemput dari akun pelanggan.</p>

                <div class="space-y-3">
                    <x-toggle name="pickup_enabled" label="Layanan jemput" hint="Tampilkan pilihan Jemput di kasir dan menu Jemput di akun pelanggan" :checked="$on('pickup_enabled', '1')" />
                    <x-toggle name="delivery_enabled" label="Layanan antar" hint="Tampilkan pilihan Antar di kasir dan pilihan Jemput + antar di akun pelanggan" :checked="$on('delivery_enabled', '1')" />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Biaya jemput (Rp)</label>
                            <input type="number" name="pickup_fee" min="0" value="{{ $val('pickup_fee', 0) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Biaya antar (Rp)</label>
                            <input type="number" name="delivery_fee" min="0" value="{{ $val('delivery_fee', 0) }}" class="{{ $input }}">
                        </div>
                    </div>

                    <x-toggle name="free_ongkir_enabled" label="Gratis ongkir otomatis" hint="Berlaku kalau berat laundry memenuhi minimum di bawah" :checked="$on('free_ongkir_enabled', '1')" />

                    <div class="max-w-xs">
                        <label class="text-sm font-medium text-gray-700">Minimal berat gratis ongkir (kg)</label>
                        <input type="number" step="0.1" name="free_ongkir_min_weight" min="0" value="{{ $val('free_ongkir_min_weight', 5) }}" class="{{ $input }}">
                    </div>
                </div>
            </div>

            <div class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Estimasi pengerjaan</h2>
                <p class="text-xs text-gray-400 mb-4">Dipakai kalau sebuah layanan tidak punya estimasi sendiri.</p>

                <div class="max-w-xs">
                    <label class="text-sm font-medium text-gray-700">Estimasi umum (hari)</label>
                    <input type="number" name="work_duration_days" min="1" max="60" value="{{ $val('work_duration_days', 2) }}" class="{{ $input }}">
                </div>

                <p class="text-sm text-gray-500 mt-4">
                    Tiap layanan bisa punya estimasi sendiri lewat
                    <a href="{{ route('services.index') }}" class="text-brand-600 font-medium">menu Layanan</a>.
                    Kalau satu transaksi berisi beberapa layanan, dipakai estimasi yang paling lama.
                </p>
            </div>
        </div>

        {{-- ============ 3. NOTA DAN NOMOR TRANSAKSI ============ --}}
        <div x-show="tab === 'nota'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="{{ $card }}"
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
                <h2 class="font-semibold text-gray-900">Format nomor transaksi</h2>
                <p class="text-xs text-gray-400 mb-4">Berlaku untuk transaksi berikutnya. Nomor yang sudah ada tidak berubah.</p>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Awalan</label>
                        <input type="text" name="invoice_prefix" maxlength="6" x-model="prefix" class="{{ $input }} uppercase">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Tanggal</label>
                        <select name="invoice_date_format" x-model="fmt" class="{{ $input }}">
                            <option value="ymd">Tahun-bulan-hari (urutan mulai 1 tiap hari)</option>
                            <option value="ym">Tahun-bulan (urutan mulai 1 tiap bulan)</option>
                            <option value="y">Tahun saja (urutan mulai 1 tiap tahun)</option>
                            <option value="none">Tanpa tanggal (urutan terus berlanjut)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Jumlah angka urutan</label>
                        <select name="invoice_digits" x-model="digits" class="{{ $input }}">
                            <option value="3">3 angka (001)</option>
                            <option value="4">4 angka (0001)</option>
                            <option value="5">5 angka (00001)</option>
                        </select>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-4">Contoh nomor pertama: <span class="font-mono font-semibold text-gray-900" x-text="example"></span></p>
            </div>

            <div class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Tampilan nota</h2>
                <p class="text-xs text-gray-400 mb-4">Slogan di kop nota diisi di tab Profil.</p>

                <div class="space-y-4">
                    <x-toggle name="nota_show_qr" label="Cetak QR tracking di nota" hint="Pelanggan memindai kode untuk melihat status cucian tanpa memasang aplikasi" :checked="$on('nota_show_qr', '1')" />

                    <div>
                        <label class="text-sm font-medium text-gray-700">Catatan kaki nota</label>
                        <textarea name="nota_footer" rows="3" maxlength="300"
                                  placeholder="Kosong = &quot;Terima kasih telah menggunakan jasa kami. Simpan nota ini sebagai bukti transaksi.&quot;"
                                  class="{{ $input }}">{{ $val('nota_footer') }}</textarea>
                    </div>

                    <a href="{{ route('settings.sampleNota') }}" target="_blank"
                       class="block text-center bg-brand-50 text-brand-700 rounded-xl py-3 text-sm font-medium hover:bg-brand-100">Test Cetak Nota (Contoh)</a>
                    <p class="text-xs text-gray-400">Contoh memakai pengaturan yang sudah tersimpan, jadi simpan dulu sebelum mencoba. Tidak ada transaksi yang dibuat.</p>
                </div>
            </div>
        </div>

        {{-- ============ 4. PEMBAYARAN ============ --}}
        <div x-show="tab === 'bayar'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Metode pembayaran kasir</h2>
                <p class="text-xs text-gray-400 mb-4">Hanya metode yang menyala yang muncul di kasir dan di form pembayaran. Minimal satu.</p>

                <div class="space-y-3">
                    @foreach(payment_method_labels() as $key => $label)
                        <label class="flex items-center justify-between gap-4 rounded-xl bg-gray-50 p-3 cursor-pointer">
                            <span>
                                <span class="block text-sm font-medium text-gray-800">{{ $label }}</span>
                                <span class="block text-xs text-gray-400">{{ $methodHints[$key] ?? '' }}</span>
                            </span>
                            <span class="relative inline-flex items-center flex-shrink-0">
                                <input type="checkbox" name="payment_methods[]" value="{{ $key }}" class="sr-only peer" {{ in_array($key, $methods, true) ? 'checked' : '' }}>
                                <span class="w-11 h-6 bg-gray-200 peer-checked:bg-brand-600 rounded-full transition"></span>
                                <span class="absolute w-4 h-4 bg-white rounded-full left-1 top-1 peer-checked:translate-x-5 transition"></span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="{{ $card }}" x-data="{ copied: false }">
                <h2 class="font-semibold text-gray-900">Info pembayaran non-tunai</h2>
                <p class="text-xs text-gray-400 mb-4">Nomor rekening, nama pemilik, atau keterangan QRIS. Tampil di kasir saat memilih selain Tunai, dan tercetak di nota.</p>

                <textarea name="payment_info" rows="5" x-ref="info" maxlength="500"
                          placeholder="Contoh: Transfer BRI 1234-01-000000-50-1 a.n. Laundry Desa"
                          class="{{ $input }}">{{ $val('payment_info') }}</textarea>

                <button type="button"
                        @click="navigator.clipboard?.writeText($refs.info.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})"
                        class="mt-3 border bg-white rounded-xl px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <span x-show="!copied">Salin teks</span><span x-show="copied" x-cloak>Tersalin</span>
                </button>
            </div>
        </div>

        {{-- ============ 5. MEMBER DAN HAK AKSES ============ --}}
        <div x-show="tab === 'member'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="{{ $card }}">
                <h2 class="font-semibold text-gray-900">Kriteria dan benefit member</h2>
                <p class="text-xs text-gray-400 mb-4">Pelanggan yang memenuhi salah satu kriteria direkomendasikan jadi member. Pemilik yang memutuskan.</p>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Setelah (kali transaksi)</label>
                            <input type="number" name="member_min_transactions" min="1" value="{{ $val('member_min_transactions', 5) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">ATAU total belanja (Rp)</label>
                            <input type="number" name="member_min_spent" min="0" value="{{ $val('member_min_spent', 200000) }}" class="{{ $input }}">
                        </div>
                    </div>
                    <div class="max-w-xs">
                        <label class="text-sm font-medium text-gray-700">Diskon otomatis member (%)</label>
                        <input type="number" step="0.1" name="member_discount_percent" min="0" max="100" value="{{ $val('member_discount_percent', 5) }}" class="{{ $input }}">
                        <p class="text-xs text-gray-400 mt-1">Diberikan tiap transaksi member, kecuali ada promo yang terpakai.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="{{ $card }}">
                    <h2 class="font-semibold text-gray-900 mb-4">Promo</h2>
                    <x-toggle name="promo_stacking_enabled" label="Promo dapat digabungkan"
                              hint="Maksimal 2 promo per transaksi, hanya promo yang diberi centang 'Boleh digabung'. Contoh: Gratis Ongkir + Diskon Hari Raya."
                              :checked="$on('promo_stacking_enabled', '0')" />
                </div>

                <div class="{{ $card }}">
                    <h2 class="font-semibold text-gray-900 mb-4">Hak akses kasir</h2>
                    <x-toggle name="cashier_can_cancel" label="Kasir boleh membatalkan transaksi"
                              hint="Hanya untuk transaksi yang belum ada pembayarannya dan dibuat dalam 2 jam terakhir. Alasan wajib diisi, dan pembatalan tercatat atas nama kasir di Log Aktivitas."
                              :checked="$on('cashier_can_cancel', '0')" />
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <button class="bg-brand-600 text-white px-6 py-3 rounded-xl text-sm font-semibold hover:bg-brand-700">Simpan Semua Pengaturan</button>
        </div>
    </form>
</x-layouts.app-layout>