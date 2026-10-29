<?php

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->adminTu = User::factory()->create();
    $this->adminTu->assignRole(RoleEnum::AdminTu->value);
});

test('only one academic year can be active at any time', function () {
    $firstYear = AcademicYear::factory()->active()->create([
        'name' => '2025/2026',
    ]);

    expect($firstYear->fresh()->is_active)->toBeTrue();

    $this->actingAs($this->adminTu)
        ->post(route('academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-07-13',
            'end_date' => '2027-06-19',
            'is_active' => true,
        ])
        ->assertRedirect();

    $secondYear = AcademicYear::where('name', '2026/2027')->firstOrFail();

    expect($secondYear->is_active)->toBeTrue()
        ->and($firstYear->fresh()->is_active)->toBeFalse()
        ->and(AcademicYear::where('is_active', true)->count())->toBe(1);

    $this->actingAs($this->adminTu)
        ->put(route('academic-years.update', $firstYear), [
            'name' => '2025/2026',
            'start_date' => $firstYear->start_date->format('Y-m-d'),
            'end_date' => $firstYear->end_date->format('Y-m-d'),
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($firstYear->fresh()->is_active)->toBeTrue()
        ->and($secondYear->fresh()->is_active)->toBeFalse()
        ->and(AcademicYear::where('is_active', true)->count())->toBe(1);
});

test('active academic year cannot be deleted', function () {
    $activeYear = AcademicYear::factory()->active()->create();

    $this->actingAs($this->adminTu)
        ->delete(route('academic-years.destroy', $activeYear))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AcademicYear::find($activeYear->id))->not->toBeNull();
});
