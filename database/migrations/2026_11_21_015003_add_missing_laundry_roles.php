
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pemilik', 'kasir', 'pegawai'] as $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Tidak menghapus role yang sudah ada.
    }
};
