<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('status')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('transactions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('transactions', 'cancel_reason')) {
                $table->string('cancel_reason', 255)->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};