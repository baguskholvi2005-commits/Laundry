
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('service_packages', 'service_id')) {
            Schema::table('service_packages', function (Blueprint $table) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->constrained('services')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('transaction_items', 'package_id')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->foreignId('package_id')
                    ->nullable()
                    ->constrained('service_packages')
                    ->nullOnDelete();
            });
        }

        // Penyesuaian ENUM hanya diperlukan untuk MySQL.
        // SQLite tidak memerlukan perubahan karena ENUM
        // tidak didukung secara native oleh SQLite.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE transaction_items
                MODIFY unit_type ENUM('kg', 'pcs', 'paket') NOT NULL
            ");
        }
    }

    public function down(): void
    {
        // Kembalikan satuan paket sebelum menghapus dukungan paket.
        if (Schema::hasColumn('transaction_items', 'unit_type')) {
            DB::table('transaction_items')
                ->where('unit_type', 'paket')
                ->update(['unit_type' => 'pcs']);
        }

        // Penyesuaian ENUM hanya untuk MySQL.
        if (
            DB::getDriverName() === 'mysql' &&
            Schema::hasColumn('transaction_items', 'unit_type')
        ) {
            DB::statement("
                ALTER TABLE transaction_items
                MODIFY unit_type ENUM('kg', 'pcs') NOT NULL
            ");
        }

        if (Schema::hasColumn('transaction_items', 'package_id')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('package_id');
            });
        }

        if (Schema::hasColumn('service_packages', 'service_id')) {
            Schema::table('service_packages', function (Blueprint $table) {
                $table->dropConstrainedForeignId('service_id');
            });
        }
    }
};
