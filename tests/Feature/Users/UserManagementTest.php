<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(RoleEnum::SuperAdmin->value);
});

test('super_admin can create, update role, and delete another user but cannot delete self', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('users.store'), [
            'name' => 'Guru Wali Baru',
            'email' => 'guru.baru@sekolah.sch.id',
            'nip' => '199001012020011009',
            'phone' => '081234560000',
            'role' => RoleEnum::WaliKelas->value,
            'password' => 'password123',
            'is_active' => true,
        ])
        ->assertRedirect();

    $user = User::where('email', 'guru.baru@sekolah.sch.id')->firstOrFail();
    expect($user->hasRole(RoleEnum::WaliKelas->value))->toBeTrue();

    $this->actingAs($this->superAdmin)
        ->put(route('users.update', $user), [
            'name' => 'Guru Wali Diperbarui',
            'email' => 'guru.baru@sekolah.sch.id',
            'nip' => '199001012020011009',
            'phone' => '081234560000',
            'role' => RoleEnum::AdminTu->value,
            'password' => '',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($user->fresh()->hasRole(RoleEnum::AdminTu->value))->toBeTrue()
        ->and($user->fresh()->hasRole(RoleEnum::WaliKelas->value))->toBeFalse();

    $this->actingAs($this->superAdmin)
        ->delete(route('users.destroy', $this->superAdmin))
        ->assertForbidden();

    $this->actingAs($this->superAdmin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect();

    expect(User::find($user->id))->toBeNull();
});
