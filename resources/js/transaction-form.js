export default function transactionForm(config) {
    return {
        services: config.services,
        packages: config.packages ?? [],
        promos: config.promos ?? [],
        stackingEnabled: !!config.stackingEnabled,
        memberDiscountPercent: Number(config.memberDiscountPercent ?? 0),
        defaultDuration: Number(config.defaultDuration ?? 2),
        pickupRequestId: config.pickupRequestId ?? null,
        tab: 'kiloan',
        cart: [],
        activeIndex: null,
        customerQuery: '',
        customerResults: [],
        selectedCustomer: null,
        selectedPromoIds: [],
        promoResults: {},
        usePickup: false,
        useDelivery: false,
        pickupEnabled: config.pickupEnabled,
        deliveryEnabled: config.deliveryEnabled,
        paidAmount: 0,
        paymentMethod: config.defaultPaymentMethod ?? 'cash',
        subtotal: 0,
        discount: 0,
        deliveryFee: 0,
        total: 0,
        freeOngkirApplied: false,
        memberDiscountApplied: false,
        cartError: null,
        submitting: false,
        previewing: false,
        previewSeq: 0,

        showQuickAddModal: false,
        newCustomer: { name: '', phone: '', address: '' },
        savingCustomer: false,

        init() {
            // Isi awal dari "Salin ke transaksi baru" atau dari permintaan jemput pelanggan
            if (config.prefill) {
                this.cart = config.prefill.items ?? [];
                this.selectedCustomer = config.prefill.customer ?? null;
                if (config.prefill.use_pickup && this.pickupEnabled) {
                    this.usePickup = true;
                }
                if (config.prefill.use_delivery && this.deliveryEnabled) {
                    this.useDelivery = true;
                }
            }

            // Baris yang beratnya masih kosong langsung jadi baris aktif
            const empty = this.cart.findIndex(i => !(Number(i.qty) > 0));
            this.activeIndex = empty >= 0 ? empty : (this.cart.length ? 0 : null);

            this.recalculate();
        },

        // ---------- Baris aktif dan tombol +/- ----------
        get activeItem() {
            return this.activeIndex !== null ? (this.cart[this.activeIndex] ?? null) : null;
        },

        get step() {
            return this.activeItem?.type === 'kiloan' ? 0.5 : 1;
        },

        get quickSteps() {
            return this.activeItem?.type === 'kiloan' ? [0.1, 0.5, 1] : [1, 2, 5];
        },

        get unitLabel() {
            const t = this.activeItem?.type;
            return t === 'kiloan' ? 'Kg' : (t === 'paket' ? 'Paket' : 'Pcs');
        },

        fmt(n) {
            return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        },

        fmtQty(item) {
            const q = Number(item.qty) || 0;
            if (q <= 0) return item.type === 'kiloan' ? 'berat belum diisi' : 'jumlah belum diisi';
            if (item.type === 'kiloan') return this.fmt(q) + ' kg';
            if (item.type === 'paket') return q + ' paket (' + this.fmt((Number(item.weight_kg) || 0) * q) + ' kg)';
            return this.fmt(q) + ' pcs';
        },

        lineTotal(item) {
            return (Number(item.qty) || 0) * item.price;
        },

        setQty(item, value) {
            let v = Number(value) || 0;

            if (item.type === 'kiloan') {
                v = Math.max(0, Math.round(v * 10) / 10);
            } else {
                v = Math.max(item.type === 'paket' ? 1 : 0, Math.round(v));
            }

            item.qty = v;
            this.recalculate();
        },

        bump(delta) {
            const item = this.activeItem;
            if (!item) return;
            this.setQty(item, (Number(item.qty) || 0) + delta);
        },

        selectLine(idx) {
            this.activeIndex = idx;
            this.focusWeight();
        },

        // Fokus ke kolom berat hanya di layar lebar, supaya HP tidak melompat ke bawah
        focusWeight() {
            if (!window.matchMedia('(min-width: 1024px)').matches) return;

            this.$nextTick(() => {
                const el = this.$refs.weightInput;
                if (el) { el.focus(); el.select(); }
            });
        },

        // ---------- Keranjang ----------
        inCart(service) {
            return this.cart.some(i => i.service_id === service.id && !i.package_id);
        },

        cartQtyLabel(service) {
            const i = this.cart.find(x => x.service_id === service.id && !x.package_id);
            if (!i) return '';
            const q = Number(i.qty) || 0;
            if (q <= 0) return 'isi berat';
            return this.fmt(q) + (service.type === 'kiloan' ? ' kg' : ' pcs');
        },

        packageInCart(pkg) {
            return this.cart.some(i => i.package_id === pkg.id);
        },

        estimate(service) {
            const days = Number(service.duration_days) || this.defaultDuration;
            const d = new Date();
            d.setDate(d.getDate() + days);
            const label = days === 1 ? 'Besok' : (days === 2 ? 'Lusa' : days + ' hari');
            return label + ', ' + d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
        },

        addItem(service) {
            const existing = this.cart.findIndex(i => i.service_id === service.id && !i.package_id);

            if (existing >= 0) {
                this.activeIndex = existing;
                // Layanan satuan: klik lagi menambah 1. Kiloan: hanya menjadikannya baris aktif
                if (service.type === 'satuan') this.bump(1);
                this.focusWeight();
                return;
            }

            this.cart.push({
                service_id: service.id,
                package_id: null,
                name: service.name,
                type: service.type,
                price: parseFloat(service.price),
                qty: service.type === 'kiloan' ? 0 : 1,
            });

            this.activeIndex = this.cart.length - 1;
            this.recalculate();
            this.focusWeight();
        },

        addPackage(pkg) {
            const existing = this.cart.findIndex(i => i.package_id === pkg.id);

            if (existing >= 0) {
                this.activeIndex = existing;
                this.bump(1);
                return;
            }

            this.cart.push({
                service_id: null,
                package_id: pkg.id,
                name: 'Paket ' + pkg.name,
                type: 'paket',
                price: Number(pkg.package_price),
                weight_kg: pkg.weight_kg,
                qty: 1,
            });

            this.activeIndex = this.cart.length - 1;
            this.recalculate();
        },

        removeItem(index) {
            this.cart.splice(index, 1);

            if (this.cart.length === 0) {
                this.activeIndex = null;
            } else if (this.activeIndex === index) {
                this.activeIndex = this.cart.length - 1;
            } else if (this.activeIndex > index) {
                this.activeIndex--;
            }

            this.recalculate();
        },

        // Jumlah paket harus bilangan bulat minimal 1
        normalizeQty(item) {
            if (item.type === 'paket') {
                item.qty = Math.max(1, Math.round(Number(item.qty) || 1));
            }
        },

        resetForm() {
            if (this.cart.length > 0 && !confirm('Kosongkan seluruh isi transaksi ini?')) return;

            this.cart = [];
            this.activeIndex = null;
            this.selectedPromoIds = [];
            this.promoResults = {};
            this.paidAmount = 0;
            this.usePickup = this.pickupRequestId !== null;
            this.useDelivery = false;
            if (this.pickupRequestId === null) this.selectedCustomer = null;
            this.recalculate();
        },

        // ---------- Promo ----------
        // Pilih atau lepas promo. Promo yang boleh digabung bisa dipilih bersamaan (maksimal 2)
        selectPromo(promoId) {
            if (this.selectedPromoIds.includes(promoId)) {
                this.selectedPromoIds = this.selectedPromoIds.filter(id => id !== promoId);
            } else {
                const promo = this.promos.find(p => p.id === promoId);

                if (!this.stackingEnabled || !promo?.stackable) {
                    this.selectedPromoIds = [promoId];
                } else {
                    const keep = this.selectedPromoIds.filter(id => this.promos.find(p => p.id === id)?.stackable);
                    this.selectedPromoIds = [...keep, promoId].slice(-2);
                }
            }

            this.recalculate();
        },

        // Hanya promo yang benar-benar berlaku yang dikirim saat menyimpan
        get appliedPromoIds() {
            return this.selectedPromoIds.filter(id => this.promoResults[id]?.applied);
        },

        // Teks syarat promo, misalnya "Min. Rp 50.000 · Min. 5 kg"
        syarat(promo) {
            const parts = [];
            if (Number(promo.min_transaction) > 0) {
                parts.push('Min. Rp ' + Number(promo.min_transaction).toLocaleString('id-ID'));
            }
            if (Number(promo.min_weight) > 0) {
                parts.push('Min. ' + Number(promo.min_weight) + ' kg');
            }
            return parts.join(' · ');
        },

        get cartWeightKg() {
            return this.cart.reduce((sum, i) => {
                if (i.type === 'kiloan') return sum + (Number(i.qty) || 0);
                if (i.type === 'paket') return sum + (Number(i.weight_kg) || 0) * (Number(i.qty) || 0);
                return sum;
            }, 0);
        },

        // Petunjuk saja. Yang memutuskan promo berlaku atau tidak tetap server
        promoShortfall(promo) {
            const parts = [];
            const minW = Number(promo.min_weight) || 0;
            const minT = Number(promo.min_transaction) || 0;

            if (minW > 0 && this.cartWeightKg + 1e-9 < minW) {
                parts.push('Kurang ' + this.fmt(minW - this.cartWeightKg) + ' kg');
            }
            if (minT > 0 && this.subtotal + 1e-9 < minT) {
                parts.push('Kurang Rp ' + Math.ceil(minT - this.subtotal).toLocaleString('id-ID'));
            }

            return parts.join(' · ');
        },

        // ---------- Hitung ulang di server ----------
        async recalculate() {
            this.subtotal = this.cart.reduce((sum, i) => sum + (Number(i.qty) * i.price), 0);

            if (this.cart.length === 0) {
                this.previewSeq++;
                this.discount = 0;
                this.deliveryFee = 0;
                this.total = 0;
                this.freeOngkirApplied = false;
                this.memberDiscountApplied = false;
                this.promoResults = {};
                this.cartError = null;
                this.previewing = false;
                return;
            }

            // Hanya jawaban terbaru yang dipakai kalau kasir mengetik cepat
            const seq = ++this.previewSeq;
            this.previewing = true;

            try {
                const res = await fetch('/transactions/preview', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        items: this.cart.map(i => ({
                            service_id: i.service_id,
                            package_id: i.package_id ?? null,
                            qty: Number(i.qty) || 0,
                        })),
                        promo_ids: this.selectedPromoIds,
                        use_pickup: this.usePickup,
                        use_delivery: this.useDelivery,
                        customer_id: this.selectedCustomer?.id ?? null,
                    }),
                });

                if (seq !== this.previewSeq) return;

                if (res.ok) {
                    const data = await res.json();
                    this.subtotal = data.subtotal;
                    this.discount = data.discount;
                    this.deliveryFee = data.delivery_fee;
                    this.total = data.total;
                    this.freeOngkirApplied = data.free_ongkir_applied;
                    this.memberDiscountApplied = data.member_discount_applied;
                    this.promoResults = Object.fromEntries((data.promo_results ?? []).map(r => [r.id, r]));
                    this.cartError = null;
                } else if (res.status === 422) {
                    const err = await res.json();
                    this.cartError = Object.values(err.errors ?? {})[0]?.[0] ?? null;
                }
            } catch (err) {
                // Kalau preview gagal (misalnya offline), angka lama tetap tampil. Server tetap menghitung ulang saat disimpan
            } finally {
                if (seq === this.previewSeq) this.previewing = false;
            }
        },

        // ---------- Pelanggan ----------
        async searchCustomer(q) {
            this.customerQuery = q;
            if (!q) { this.customerResults = []; return; }
            const res = await fetch(`/customers/search?q=${encodeURIComponent(q)}`);
            this.customerResults = await res.json();
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerResults = [];
            this.customerQuery = '';
            this.recalculate();
        },

        clearCustomer() {
            this.selectedCustomer = null;
            this.recalculate();
            this.$nextTick(() => this.$refs.customerInput?.focus());
        },

        openQuickAdd() {
            this.newCustomer = { name: this.customerQuery, phone: '', address: '' };
            this.showQuickAddModal = true;
            this.customerResults = [];
        },

        async saveNewCustomer() {
            if (!this.newCustomer.name) {
                alert('Nama pelanggan wajib diisi.');
                return;
            }
            this.savingCustomer = true;
            try {
                const res = await fetch('/customers', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.newCustomer),
                });

                if (!res.ok) throw new Error('Gagal menyimpan pelanggan');

                const customer = await res.json();
                this.selectCustomer(customer);
                this.showQuickAddModal = false;
                this.newCustomer = { name: '', phone: '', address: '' };
            } catch (err) {
                alert('Gagal menyimpan pelanggan, coba lagi.');
            } finally {
                this.savingCustomer = false;
            }
        },

        // ---------- Pembayaran ----------
        get change() {
            return Math.max(0, (Number(this.paidAmount) || 0) - this.total);
        },

        get shortfall() {
            const paid = Number(this.paidAmount) || 0;
            return paid > 0 && paid < this.total ? this.total - paid : 0;
        },

        // Uang pas, lalu pecahan terdekat di atas total
        get cashSuggestions() {
            const t = Math.ceil(this.total);
            if (t <= 0) return [];

            const out = [t];
            [5000, 10000, 20000, 50000, 100000].forEach(step => {
                const v = Math.ceil(t / step) * step;
                if (!out.includes(v)) out.push(v);
            });

            return out.slice(0, 4);
        },

        async submit(asDebt = false) {
            if (this.cart.length === 0) {
                alert('Pilih minimal satu layanan.');
                return;
            }

            if (this.cart.some(i => !(Number(i.qty) > 0))) {
                alert('Berat atau jumlah laundry wajib diisi.');
                return;
            }

            if (asDebt && !this.selectedCustomer &&
                !confirm('Transaksi ini tidak punya pelanggan terdaftar, jadi tagihannya sulit ditagih. Tetap simpan sebagai tagihan?')) {
                return;
            }

            this.submitting = true;

            const payload = {
                customer_id: this.selectedCustomer?.id ?? null,
                items: this.cart.map(i => ({
                    service_id: i.service_id,
                    package_id: i.package_id ?? null,
                    qty: Number(i.qty),
                })),
                promo_ids: this.appliedPromoIds,
                use_pickup: this.usePickup,
                use_delivery: this.useDelivery,
                pickup_request_id: this.pickupRequestId,
                payment_method: this.paymentMethod,
                paid_amount: asDebt ? 0 : (Number(this.paidAmount) || 0),
            };

            try {
                const res = await fetch('/transactions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                // 422 = ditolak validasi server (misalnya promo, paket, atau permintaan jemput sudah tidak berlaku)
                if (res.status === 422) {
                    const err = await res.json();
                    const first = Object.values(err.errors ?? {})[0]?.[0];
                    alert((first ?? err.message ?? 'Data transaksi tidak valid.') + '\n\nTotal akan dihitung ulang. Periksa lagi sebelum menyimpan.');
                    this.submitting = false;
                    await this.recalculate();
                    return;
                }

                if (!res.ok) throw new Error('Gagal menyimpan transaksi');

                const data = await res.json();
                window.location.href = `/transactions/${data.id}`;
            } catch (err) {
                alert('Terjadi kesalahan, coba lagi.');
                this.submitting = false;
            }
        },
    };
}