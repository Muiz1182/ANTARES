<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfc_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('uid', 32)->unique();
            $table->morphs('cardable');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('issued_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('replaced_by_card_id')
                ->nullable()
                ->constrained('nfc_cards')
                ->nullOnDelete();
            $table->foreignId('issued_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cardable_type', 'cardable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_cards');
    }
};
