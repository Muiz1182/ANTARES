<?php

use App\Enums\RoleEnum;
use App\Models\Holiday;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->adminTu = User::factory()->create();
    $this->adminTu->assignRole(RoleEnum::AdminTu->value);
});

test('admin_tu can create, update, and delete holidays with unique date validation', function () {
    $this->actingAs($this->adminTu)
        ->post(route('holidays.store'), [
            'date' => '2026-08-17',
            'name' => 'HUT Kemerdekaan RI',
            'description' => 'Libur nasional',
        ])
        ->assertRedirect();

    $holiday = Holiday::whereDate('date', '2026-08-17')->firstOrFail();

    $this->actingAs($this->adminTu)
        ->post(route('holidays.store'), [
            'date' => '2026-08-17',
            'name' => 'Duplikat Tanggal',
            'description' => null,
        ])
        ->assertSessionHasErrors('date');

    $this->actingAs($this->adminTu)
        ->put(route('holidays.update', $holiday), [
            'date' => '2026-08-17',
            'name' => 'HUT RI Ke-81',
            'description' => 'Diperbarui',
        ])
        ->assertRedirect();

    expect($holiday->fresh()->name)->toBe('HUT RI Ke-81');

    $this->actingAs($this->adminTu)
        ->delete(route('holidays.destroy', $holiday))
        ->assertRedirect();

    expect(Holiday::find($holiday->id))->toBeNull();
});
