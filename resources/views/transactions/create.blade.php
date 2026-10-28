<x-layouts.app-layout>
    <x-slot name="header"><span class="font-semibold text-gray-900">Transaksi Baru</span></x-slot>

    <div x-data="transactionForm({
            services: @js($services),
            packages: @js($packages),
            promos: @js($promos),
            prefill: @js($prefill),
            pickupRequestId: @js($pickupRequestId),
            stackingEnabled: {{ setting('promo_stacking_enabled') ? 'true' : 'false' }},
            memberDiscountPercent: {{ (float) setting('member_discount_percent', 0) }},
            defaultDuration: {{ (int) setting('work_duration_days', 2) }},
            defaultPaymentMethod: '{{ array_key_first(payment_methods()) }}',
            pickupEnabled: {{ setting('pickup_enabled') ? 'true' : 'false' }},
            deliveryEnabled: {{ setting('delivery_enabled') ? 'true' : 'false' }}
        })">

        <div class="flex items-center justify-between gap-3 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Kasir Transaksi Baru</h1>
                <p class="text-sm text-gray-500">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
            <button type="button" @click="resetForm()" class="border bg-white rounded-xl px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Reset Form
            </button>
        </div>

        @if($prefill)
            <div class="bg-brand-50 text-brand-700 rounded-xl px-4 py-3 text-sm mb-5">
                {{ $prefillNote }}
                @if($prefillSkipped > 0)
                    <span class="text-danger-600">{{ $prefillSkipped }} item tidak ikut karena layanan atau paketnya sudah tidak tersedia.</span>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">

            {{-- ============ KIRI ============ --}}
            <div class="space-y-5">

                {{-- Pelanggan --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-4 space-y-3">
                    <div class="flex gap-3">
                        <div class="relative flex-1">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </span>
                            <input type="text" x-ref="customerInput" x-model="customerQuery" placeholder="Cari nama atau No. HP pelanggan..."
                                   @input.debounce.400ms="searchCustomer($event.target.value)"
                                   class="w-full pl-11 border-gray-200 rounded-xl focus:ring-brand-500 focus:border-brand-500">

                            <div x-show="customerResults.length > 0" x-cloak
                                 class="absolute top-full mt-1 left-0 right-0 bg-white border rounded-xl shadow-lg z-10 max-h-72 overflow-y-auto">
                                <template x-for="c in customerResults" :key="c.id">
                                    <button type="button" @click="selectCustomer(c)"
                                            class="w-full text-left px-4 py-2.5 hover:bg-gray-50 border-b last:border-0 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold flex-shrink-0"
                                                  :class="c.is_member ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'"
                                                  x-text="c.name.charAt(0).toUpperCase()"></span>
                                            <div class="min-w-0">
                                                <p class="font-medium text-sm truncate" x-text="c.name"></p>
                                                <p class="text-xs text-gray-400" x-text="c.phone"></p>
                                            </div>
                                        </div>
                                        <span x-show="c.is_member" class="text-[11px] font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full whitespace-nowrap">MEMBER</span>
                                        <span x-show="!c.is_member && c.is_eligible_for_member" class="text-[11px] bg-brand-50 text-brand-700 px-2 py-0.5 rounded-full whitespace-nowrap">Layak member</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <button type="button" @click="openQuickAdd()"
                                class="bg-brand-600 text-white rounded-xl px-4 text-sm font-medium whitespace-nowrap hover:bg-brand-700">
                            + Pelanggan Baru
                        </button>
                    </div>

                    <template x-if="selectedCustomer">
                        <div class="rounded-xl p-3 flex items-start justify-between gap-3"
                             :class="selectedCustomer.is_member ? 'bg-amber-50 border border-amber-200' : 'bg-brand-50'">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="w-10 h-10 rounded-full flex items-center justify-center font-semibold flex-shrink-0"
                                      :class="selectedCustomer.is_member ? 'bg-amber-200 text-amber-800' : 'bg-brand-100 text-brand-700'"
                                      x-text="selectedCustomer.name.charAt(0).toUpperCase()"></span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-semibold text-gray-900" x-text="selectedCustomer.name"></p>
                                        <span x-show="selectedCustomer.is_member" class="text-[11px] font-semibold bg-amber-500 text-white px-2 py-0.5 rounded">MEMBER AKTIF</span>
                                    </div>
                                    <p class="text-xs text-gray-600" x-text="selectedCustomer.phone || 'Tanpa nomor HP'"></p>
                                    <p class="text-xs text-gray-500 truncate" x-show="selectedCustomer.address" x-text="selectedCustomer.address"></p>
                                    <p class="text-xs text-amber-800 mt-1" x-show="selectedCustomer.is_member">
                                        <span x-text="memberDiscountApplied ? 'Diskon otomatis ' + memberDiscountPercent + '% diterapkan' : 'Diskon otomatis ' + memberDiscountPercent + '% (tidak berlaku bersama promo)'"></span>
                                    </p>
                                    <p class="text-xs text-brand-700 mt-1" x-show="!selectedCustomer.is_member && selectedCustomer.is_eligible_for_member">
                                        Sudah memenuhi syarat member. Pemilik bisa menjadikannya member di halaman Pelanggan.
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="clearCustomer()" class="border bg-white rounded-lg px-3 py-1.5 text-xs text-gray-700 whitespace-nowrap">Ganti</button>
                        </div>
                    </template>

                    <p x-show="!selectedCustomer" class="text-xs text-gray-400">Tidak dipilih = Pelanggan Umum</p>
                </div>

                {{-- Layanan --}}
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="tab = 'kiloan'"
                                :class="tab === 'kiloan' ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300'"
                                class="border rounded-xl px-4 py-2.5 text-sm font-medium flex items-center gap-2">
                            Laundry Kiloan
                            <span class="text-xs rounded-md px-1.5 py-0.5" :class="tab === 'kiloan' ? 'bg-white/20' : 'bg-gray-100 text-gray-500'"
                                  x-text="services.filter(s => s.type === 'kiloan').length"></span>
                        </button>
                        <button type="button" @click="tab = 'satuan'"
                                :class="tab === 'satuan' ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300'"
                                class="border rounded-xl px-4 py-2.5 text-sm font-medium flex items-center gap-2">
                            Laundry Satuan
                            <span class="text-xs rounded-md px-1.5 py-0.5" :class="tab === 'satuan' ? 'bg-white/20' : 'bg-gray-100 text-gray-500'"
                                  x-text="services.filter(s => s.type === 'satuan').length"></span>
                        </button>
                        <button type="button" x-show="packages.length > 0" x-cloak @click="tab = 'paket'"
                                :class="tab === 'paket' ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300'"
                                class="border rounded-xl px-4 py-2.5 text-sm font-medium flex items-center gap-2">
                            Paket
                            <span class="text-xs rounded-md px-1.5 py-0.5" :class="tab === 'paket' ? 'bg-white/20' : 'bg-gray-100 text-gray-500'"
                                  x-text="packages.length"></span>
                        </button>
                    </div>

                    <div x-show="tab !== 'paket'" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 pt-2">
                        <template x-for="s in services.filter(s => s.type === tab)" :key="s.id">
                            <button type="button" @click="addItem(s)"
                                    class="relative text-left rounded-2xl border p-4 transition hover:border-brand-400"
                                    :class="inCart(s) ? 'border-brand-600 ring-2 ring-brand-600/20 bg-brand-50' : 'border-gray-200 bg-white'">
                                <span x-show="inCart(s)" x-cloak
                                      class="absolute -top-2.5 left-4 text-[10px] font-semibold bg-brand-600 text-white rounded-full px-2 py-0.5"
                                      x-text="'DIPILIH (' + cartQtyLabel(s).toUpperCase() + ')'"></span>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold text-gray-900 leading-snug" x-text="s.name"></p>
                                    <p class="text-right whitespace-nowrap">
                                        <span class="font-bold text-brand-700" x-text="'Rp ' + Number(s.price).toLocaleString('id-ID')"></span><span class="text-xs text-gray-500" x-text="s.type === 'kiloan' ? '/kg' : '/pcs'"></span>
                                    </p>
                                </div>
                                <p class="text-xs text-gray-500 mt-3" x-text="'Estimasi: ' + estimate(s)"></p>
                            </button>
                        </template>
                    </div>

                    <div x-show="tab === 'paket'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 pt-2">
                        <template x-for="p in packages" :key="p.id">
                            <button type="button" @click="addPackage(p)"
                                    class="relative text-left rounded-2xl border p-4 transition hover:border-brand-400"
                                    :class="packageInCart(p) ? 'border-brand-600 ring-2 ring-brand-600/20 bg-brand-50' : 'border-gray-200 bg-white'">
                                <span x-show="packageInCart(p)" x-cloak
                                      class="absolute -top-2.5 left-4 text-[10px] font-semibold bg-brand-600 text-white rounded-full px-2 py-0.5">DIPILIH</span>
                                <p class="font-semibold text-gray-900 leading-snug" x-text="p.name"></p>
                                <p class="text-xs text-gray-500 mt-1" x-text="p.weight_kg + ' kg · ' + p.service_name"></p>
                                <p class="text-lg font-bold text-brand-700 mt-2" x-text="'Rp ' + Number(p.package_price).toLocaleString('id-ID')"></p>
                                <p class="text-xs text-success-600" x-show="p.normal_price > p.package_price"
                                   x-text="'Hemat Rp ' + (p.normal_price - p.package_price).toLocaleString('id-ID')"></p>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Promo --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h2 class="font-semibold text-gray-900">Promo tersedia</h2>
                        <p class="text-xs text-gray-400" x-show="stackingEnabled" x-cloak>Promo bertanda "Bisa digabung" boleh dipilih bersamaan (maksimal 2)</p>
                    </div>

                    @if($promos->isEmpty())
                        <p class="text-sm text-gray-500">
                            @if($totalPromo === 0)
                                Belum ada promo.
                                @role('pemilik')
                                    Buat promo lewat menu <a href="{{ route('promos.create') }}" class="text-brand-600 font-medium">Promo</a>.
                                @endrole
                            @else
                                Tidak ada promo yang aktif hari ini.
                            @endif
                        </p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <template x-for="promo in promos" :key="promo.id">
                                <button type="button" @click="selectPromo(promo.id)"
                                        class="text-left rounded-xl border p-3 transition"
                                        :class="selectedPromoIds.includes(promo.id) ? 'border-brand-600 bg-brand-50' : 'border-gray-200 hover:border-brand-300'">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-semibold text-gray-900 leading-snug" x-text="promo.name"></p>
                                        <span class="text-[11px] font-semibold rounded-md px-2 py-0.5 whitespace-nowrap"
                                              :class="selectedPromoIds.includes(promo.id) ? 'bg-brand-600 text-white' : (promoShortfall(promo) ? 'bg-gray-100 text-gray-500' : 'bg-brand-50 text-brand-700')"
                                              x-text="selectedPromoIds.includes(promo.id) ? 'Dipakai' : (promoShortfall(promo) ? promoShortfall(promo) : 'Gunakan')"></span>
                                    </div>
                                    <p class="text-xs text-gray-600 mt-1">
                                        <template x-if="promo.type === 'gratis_ongkir'"><span>Gratis ongkir</span></template>
                                        <template x-if="promo.type !== 'gratis_ongkir' && promo.value_type === 'persen'"><span x-text="Number(promo.value) + '% off'"></span></template>
                                        <template x-if="promo.type !== 'gratis_ongkir' && promo.value_type !== 'persen'"><span x-text="'Potongan Rp ' + Number(promo.value ?? 0).toLocaleString('id-ID')"></span></template>
                                        <template x-if="promo.type === 'member'"><span class="text-amber-700"> · khusus member</span></template>
                                        <template x-if="stackingEnabled && promo.stackable"><span class="text-brand-700"> · bisa digabung</span></template>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1" x-show="syarat(promo)" x-text="'Syarat: ' + syarat(promo)"></p>
                                    <p class="text-xs text-danger-600 mt-0.5" x-show="promoShortfall(promo) && cart.length > 0"
                                       x-text="'Saat ini: ' + fmt(cartWeightKg) + ' kg · Rp ' + Math.round(subtotal).toLocaleString('id-ID')"></p>
                                </button>
                            </template>
                        </div>

                        <template x-for="id in selectedPromoIds" :key="id">
                            <p class="text-xs mt-2" :class="promoResults[id]?.applied ? 'text-success-600' : 'text-danger-600'">
                                <span class="font-medium" x-text="promos.find(p => p.id === id)?.name"></span>:
                                <span x-text="promoResults[id] ? (promoResults[id].applied ? 'diterapkan' : promoResults[id].reason) : 'menghitung...'"></span>
                            </p>
                        </template>
                    @endif
                </div>
            </div>

            {{-- ============ KANAN ============ --}}
            <div x-ref="summary" class="bg-white rounded-2xl border border-gray-100 p-5 space-y-4 lg:sticky lg:top-20">

                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">Ringkasan Pesanan</h2>
                    <span class="text-xs bg-gray-100 text-gray-600 rounded-lg px-2.5 py-1">Kasir: {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</span>
                </div>

                {{-- Input berat --}}
                <div class="rounded-2xl bg-brand-50 border border-brand-100 p-3">
                    <p class="text-[11px] font-semibold tracking-widest text-brand-700 uppercase"
                       x-text="activeItem ? (activeItem.type === 'kiloan' ? 'Input berat' : (activeItem.type === 'paket' ? 'Jumlah paket' : 'Jumlah potong')) : 'Input berat'"></p>

                    <template x-if="activeItem">
                        <div>
                            <p class="text-sm text-gray-700 mt-1 truncate" x-text="activeItem.name"></p>

                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" @click="bump(-step)" aria-label="Kurangi"
                                        class="w-12 h-12 rounded-xl border bg-white text-xl text-gray-700 hover:bg-gray-50">&minus;</button>

                                <div class="flex-1 flex items-center justify-center gap-1.5 bg-white rounded-xl border h-12 px-2">
                                    <input type="number" x-ref="weightInput" inputmode="decimal" min="0"
                                           :step="activeItem.type === 'kiloan' ? 0.1 : 1"
                                           x-model.number="cart[activeIndex].qty"
                                           @input="recalculate()" @change="normalizeQty(cart[activeIndex]); recalculate()"
                                           class="w-full border-0 p-0 text-center text-2xl font-bold font-mono text-gray-900 focus:ring-0 bg-transparent">
                                    <span class="text-sm font-semibold text-gray-500" x-text="unitLabel"></span>
                                </div>

                                <button type="button" @click="bump(step)" aria-label="Tambah"
                                        class="w-12 h-12 rounded-xl border bg-white text-xl text-gray-700 hover:bg-gray-50">+</button>
                            </div>

                            <div class="flex flex-wrap gap-2 mt-2">
                                <template x-for="q in quickSteps" :key="q">
                                    <button type="button" @click="bump(q)"
                                            class="border bg-white rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:border-brand-400"
                                            x-text="'+' + fmt(q)"></button>
                                </template>
                                <button type="button" @click="setQty(activeItem, 0)"
                                        class="border border-danger-200 bg-white rounded-lg px-2.5 py-1.5 text-xs font-medium text-danger-600">Nol</button>
                            </div>
                        </div>
                    </template>

                    <p x-show="!activeItem" class="text-sm text-gray-500 mt-2">Pilih layanan di sebelah kiri, lalu isi beratnya di sini.</p>
                </div>

                {{-- Daftar item --}}
                <div>
                    <p x-show="cart.length === 0" class="text-sm text-gray-400">Belum ada layanan dipilih.</p>

                    <template x-for="(item, idx) in cart" :key="idx">
                        <div @click="selectLine(idx)"
                             class="flex items-start justify-between gap-2 py-2.5 border-b border-gray-100 cursor-pointer"
                             :class="idx === activeIndex ? 'bg-brand-50/60 -mx-2 px-2 rounded-lg' : ''">
                            <div class="min-w-0">
                                <p class="font-medium text-sm text-gray-900" x-text="item.name"></p>
                                <p class="text-xs text-gray-500" x-text="fmtQty(item) + ' × Rp ' + item.price.toLocaleString('id-ID')"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold font-mono tabular-nums" x-text="'Rp ' + lineTotal(item).toLocaleString('id-ID')"></span>
                                <button type="button" @click.stop="removeItem(idx)" aria-label="Hapus" class="text-gray-400 hover:text-danger-600">&times;</button>
                            </div>
                        </div>
                    </template>

                    <p x-show="cartError" x-text="cartError" class="text-xs text-danger-600 mt-2"></p>
                </div>

                {{-- Jemput dan antar --}}
                <div x-show="pickupEnabled || deliveryEnabled" x-cloak>
                    <p class="text-xs font-medium text-gray-600 mb-1.5">Layanan antar-jemput</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" x-show="pickupEnabled"
                                @click="if (pickupRequestId === null) { usePickup = !usePickup; recalculate(); }"
                                :class="usePickup ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300'"
                                class="border rounded-xl px-3 py-2.5 text-sm font-medium">
                            Jemput <span class="text-xs opacity-80">+Rp {{ number_format((float) setting('pickup_fee', 0), 0, ',', '.') }}</span>
                        </button>
                        <button type="button" x-show="deliveryEnabled"
                                @click="useDelivery = !useDelivery; recalculate()"
                                :class="useDelivery ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300'"
                                class="border rounded-xl px-3 py-2.5 text-sm font-medium">
                            Antar <span class="text-xs opacity-80">+Rp {{ number_format((float) setting('delivery_fee', 0), 0, ',', '.') }}</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">Tidak dipilih = pelanggan mengantar dan mengambil sendiri.
                        <span x-show="pickupRequestId !== null" x-cloak class="text-brand-700">Jemput terkunci karena dari permintaan pelanggan.</span></p>
                </div>

                {{-- Rincian --}}
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span class="font-mono tabular-nums" x-text="'Rp ' + subtotal.toLocaleString('id-ID')"></span></div>
                    <div class="flex justify-between text-danger-600">
                        <span>Diskon <span class="text-xs" x-show="memberDiscountApplied">(Member)</span></span>
                        <span class="font-mono tabular-nums" x-text="'- Rp ' + discount.toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Ongkir <span class="text-xs text-success-600" x-show="freeOngkirApplied">(gratis)</span></span>
                        <span class="font-mono tabular-nums" x-text="'Rp ' + deliveryFee.toLocaleString('id-ID')"></span>
                    </div>
                </div>

                <div class="flex items-end justify-between gap-3 pt-3 border-t border-gray-200">
                    <div>
                        <p class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Total tagihan</p>
                        <p class="text-xs text-gray-400" x-show="previewing" x-cloak>Menghitung ulang...</p>
                    </div>
                    <p class="text-3xl font-bold font-mono tabular-nums text-brand-700" x-text="'Rp ' + total.toLocaleString('id-ID')"></p>
                </div>

                {{-- Pembayaran --}}
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-1.5">Metode pembayaran</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(payment_methods() as $key => $label)
                            <button type="button" @click="paymentMethod = '{{ $key }}'"
                                    :class="paymentMethod === '{{ $key }}' ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 hover:border-brand-300'"
                                    class="border rounded-xl px-3 py-2.5 text-sm font-medium">{{ $label }}</button>
                        @endforeach
                    </div>
                    @if(setting('payment_info'))
                        <p x-show="paymentMethod !== 'cash'" x-cloak class="text-xs text-gray-500 whitespace-pre-line mt-2">{{ setting('payment_info') }}</p>
                    @endif
                </div>

                <div class="rounded-2xl bg-gray-50 border border-gray-100 p-3 space-y-2.5">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm text-gray-600">Uang diterima (Rp)</label>
                        <input type="number" min="0" x-model.number="paidAmount"
                               class="w-36 text-right font-mono font-semibold border-gray-200 rounded-xl text-sm">
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <template x-for="(v, i) in cashSuggestions" :key="v">
                            <button type="button" @click="paidAmount = v"
                                    class="border bg-white rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:border-brand-400"
                                    x-text="i === 0 ? 'Uang Pas' : 'Rp ' + v.toLocaleString('id-ID')"></button>
                        </template>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                        <span class="text-[11px] font-semibold tracking-widest text-gray-500 uppercase">Kembalian</span>
                        <span class="text-xl font-bold font-mono tabular-nums text-success-600" x-text="'Rp ' + change.toLocaleString('id-ID')"></span>
                    </div>
                    <p x-show="shortfall > 0" x-cloak class="text-xs text-warning-600"
                       x-text="'Kurang Rp ' + shortfall.toLocaleString('id-ID') + ', akan tercatat sebagai DP.'"></p>
                </div>

                <div class="space-y-2">
                    <button type="button" @click="submit(false)" :disabled="submitting"
                            class="w-full bg-brand-700 text-white rounded-xl py-3.5 font-semibold hover:bg-brand-800 disabled:opacity-50">
                        <span x-show="!submitting">Simpan &amp; Bayar</span>
                        <span x-show="submitting" x-cloak>Menyimpan...</span>
                    </button>
                    <button type="button" @click="submit(true)" :disabled="submitting"
                            class="w-full border bg-white text-gray-700 rounded-xl py-2.5 text-sm font-medium hover:bg-gray-50 disabled:opacity-50">
                        Simpan sebagai Tagihan (Belum Lunas)
                    </button>
                </div>
            </div>
        </div>

        {{-- Jarak di HP supaya bilah di bawah tidak menutupi isi halaman --}}
        <div class="lg:hidden h-44"></div>

        {{-- Bilah HP: berat, total, dan tombol simpan selalu terlihat --}}
        <div class="lg:hidden fixed inset-x-0 bottom-16 z-30 bg-white border-t px-4 py-3 space-y-2">
            <template x-if="activeItem">
                <div class="flex items-center gap-2">
                    <p class="flex-1 text-sm font-medium text-gray-800 truncate" x-text="activeItem.name"></p>
                    <button type="button" @click="bump(-step)" class="w-9 h-9 rounded-lg border text-lg">&minus;</button>
                    <span class="w-14 text-center font-mono font-bold" x-text="fmt(activeItem.qty || 0)"></span>
                    <button type="button" @click="bump(step)" class="w-9 h-9 rounded-lg border text-lg">+</button>
                </div>
            </template>
            <div class="flex items-center gap-3">
                <button type="button" @click="$refs.summary.scrollIntoView({ behavior: 'smooth' })" class="flex-1 text-left">
                    <p class="text-xs text-gray-500"><span x-text="cart.length"></span> item · ketuk untuk rincian</p>
                    <p class="text-lg font-bold font-mono text-brand-700" x-text="'Rp ' + total.toLocaleString('id-ID')"></p>
                </button>
                <button type="button" @click="submit(false)" :disabled="submitting || cart.length === 0"
                        class="bg-brand-700 text-white rounded-xl px-5 py-3 text-sm font-semibold disabled:opacity-50">
                    Simpan &amp; Bayar
                </button>
            </div>
        </div>

        {{-- Modal tambah pelanggan --}}
        <div x-show="showQuickAddModal" x-cloak
             class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
             @keydown.escape.window="showQuickAddModal = false">
            <div @click.outside="showQuickAddModal = false" class="bg-white rounded-2xl w-full max-w-md p-6">
                <h3 class="font-semibold text-lg mb-4">Tambah Pelanggan Baru</h3>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama <span class="text-danger-500">*</span></label>
                        <input type="text" x-model="newCustomer.name" placeholder="Nama pelanggan" class="w-full border-gray-200 rounded-xl mt-1">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nomor HP</label>
                        <input type="text" x-model="newCustomer.phone" placeholder="08xxxxxxxxxx" class="w-full border-gray-200 rounded-xl mt-1">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Alamat</label>
                        <textarea x-model="newCustomer.address" rows="2" placeholder="Opsional" class="w-full border-gray-200 rounded-xl mt-1"></textarea>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" @click="showQuickAddModal = false" class="flex-1 border rounded-xl py-2.5 text-sm">Batal</button>
                    <button type="button" @click="saveNewCustomer()" :disabled="savingCustomer"
                            class="flex-1 bg-brand-600 text-white rounded-xl py-2.5 text-sm font-medium disabled:opacity-50">
                        <span x-show="!savingCustomer">Simpan &amp; Pilih</span>
                        <span x-show="savingCustomer" x-cloak>Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app-layout>