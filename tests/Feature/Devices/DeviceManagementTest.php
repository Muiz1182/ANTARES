<?php

use App\Enums\RoleEnum;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('super_admin can create device, receive plain token once, regenerate token, and revoke device while admin_tu gets 403', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

    $adminTu = User::factory()->create();
    $adminTu->assignRole(RoleEnum::AdminTu->value);

    $this->actingAs($adminTu)->get(route('devices.index'))->assertForbidden();

    $createResponse = $this->actingAs($superAdmin)->post(route('devices.store'), [
        'name' => 'Pos Gerbang Timur',
        'location' => 'Pintu Timur',
    ]);

    $createResponse->assertRedirect(route('devices.index'))
        ->assertSessionHas('plain_device_token');

    $plainToken = session('plain_device_token');
    $device = Device::where('name', 'Pos Gerbang Timur')->first();

    expect($device)->not->toBeNull()
        ->and($plainToken)->toBeString()
        ->and($device->token_hash)->toBe(Device::hashToken($plainToken));

    $regenResponse = $this->actingAs($superAdmin)->post(route('devices.regenerate-token', $device->id));
    $regenResponse->assertRedirect(route('devices.index'))
        ->assertSessionHas('plain_device_token');

    $newToken = session('plain_device_token');
    $device->refresh();
    expect($newToken)->not->toBe($plainToken)
        ->and($device->token_hash)->toBe(Device::hashToken($newToken));

    $this->actingAs($superAdmin)->put(route('devices.update', $device->id), [
        'name' => 'Pos Gerbang Timur',
        'location' => 'Pintu Timur',
        'is_active' => false,
    ])->assertRedirect(route('devices.index'));

    expect($device->fresh()->is_active)->toBeFalse();
});
