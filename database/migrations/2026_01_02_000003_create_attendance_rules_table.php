<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->boolean('is_school_day')->default(true);
            $table->time('check_in_start')->default('06:00:00');
            $table->time('late_after')->default('07:00:00');
            $table->time('check_out_start')->default('14:00:00');
            $table->time('absent_cutoff')->default('10:00:00');
            $table->unsignedSmallInteger('debounce_seconds')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_rules');
    }
};

