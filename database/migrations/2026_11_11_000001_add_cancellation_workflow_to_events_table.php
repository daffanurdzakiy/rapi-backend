<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('status')->default('Pending')->change();
            $table->text('cancellation_reason')->nullable();
            $table->text('cancellation_decision_reason')->nullable();
            $table->foreignId('cancellation_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('events')->whereIn('status', ['Menunggu Pembatalan', 'Dibatalkan'])->exists()) {
            throw new RuntimeException('Selesaikan atau arsipkan permintaan pembatalan sebelum rollback migrasi ini agar status agenda tidak hilang.');
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancellation_reviewed_by');
            $table->dropColumn(['cancellation_reason', 'cancellation_decision_reason']);
            $table->enum('status', ['Pending', 'Disetujui', 'Ditolak', 'Dipindahkan'])
                ->default('Pending')
                ->change();
        });
    }
};
