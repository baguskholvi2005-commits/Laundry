<div class="flex flex-wrap items-center gap-2">
    <form method="POST" action="{{ route('inventory.move') }}">
        @csrf
        <input type="hidden" name="item_id" value="{{ $item->id }}">
        <input type="hidden" name="type" value="keluar">
        <input type="hidden" name="qty" value="1">
        <input type="hidden" name="notes" value="Pemakaian (tombol cepat)">
        <button @disabled($item->current_stock < 1)
                class="border bg-white rounded-lg px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-brand-400 disabled:opacity-40 disabled:cursor-not-allowed">
            Pakai 1 {{ $item->unit }}
        </button>
    </form>

    <button type="button" @click="open({{ $item->id }}, 'masuk')"
            class="bg-brand-50 text-brand-700 rounded-lg px-3 py-1.5 text-xs font-semibold hover:bg-brand-100">Restock</button>

    <a href="{{ route('inventory.edit', $item) }}" class="text-brand-600 text-xs px-1">Edit</a>

    <form method="POST" action="{{ route('inventory.destroy', $item) }}"
          onsubmit="return confirm(@js('Hapus ' . $item->name . '? Riwayat stoknya ikut terhapus.'))">
        @csrf @method('DELETE')
        <button class="text-xs text-gray-400 hover:text-danger-600 px-1">Hapus</button>
    </form>
</div>