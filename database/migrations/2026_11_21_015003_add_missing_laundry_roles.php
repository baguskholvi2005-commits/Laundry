
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pemilik', 'kasir', 'pegawai'] as $role) {
            DB::table('roles')->firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
    }

    public function down(): void
    {
        // Tidak menghapus role agar data pengguna tetap aman.
    }
};
