<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('school_name', 150)->default('SMP Negeri 1 Nusantara');
            $table->string('school_address', 255)->nullable();
            $table->string('principal_name', 150)->nullable();
            $table->string('principal_nip', 40)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};

