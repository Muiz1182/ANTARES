<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfc_card_id')
                ->nullable()
                ->constrained('nfc_cards')
                ->nullOnDelete();
            $table->nullableMorphs('attendable');
            $table->string('uid_raw', 100);
            $table->string('uid_normalized', 32)->nullable()->index();
            $table->foreignId('device_id')
                ->nullable()
                ->constrained('devices')
                ->nullOnDelete();
            $table->timestamp('scanned_at')->index();
            $table->string('result', 40)->index();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['nfc_card_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
