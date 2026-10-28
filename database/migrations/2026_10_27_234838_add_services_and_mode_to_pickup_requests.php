<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pickup_requests', 'service_mode')) {
            Schema::table('pickup_requests', function (Blueprint $table) {
                // Permintaan yang sudah ada otomatis dianggap "jemput saja"
                $table->enum('service_mode', ['jemput', 'jemput_antar'])->default('jemput')->after('customer_id');
            });
        }

        if (! Schema::hasTable('pickup_request_items')) {
            Schema::create('pickup_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pickup_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
                $table->string('service_name');
                $table->enum('unit_type', ['kg', 'pcs']);
                $table->decimal('qty', 8, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_request_items');

        Schema::table('pickup_requests', function (Blueprint $table) {
            $table->dropColumn('service_mode');
        });
    }
};