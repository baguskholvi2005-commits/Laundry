<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pickup_requests', 'payment_method')) {
            Schema::table('pickup_requests', function (Blueprint $table) {
                // Permintaan lama otomatis dianggap tunai
                $table->enum('payment_method', ['cash', 'transfer', 'qris', 'ewallet'])->default('cash')->after('service_mode');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};