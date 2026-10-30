<?php

use App\Enums\RoleEnum;
use App\Enums\ScanResultEnum;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AttendanceRuleSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed([
        RolePermissionSeeder::class,
        AttendanceRuleSeeder::class,
    ]);
});

test('POST /api/scan rejects missing, invalid, or deactivated device tokens with 401', function () {
    $this->postJson('/api/scan', ['uid' => '04A1B2C3'])
        ->assertStatus(401);

    $this->withToken('invalid-token')->postJson('/api/scan', ['uid' => '04A1B2C3'])
        ->assertStatus(401);

    Device::factory()->withPlainToken('revoked-token')->create([
        'is_active' => false,
    ]);

    $this->withToken('revoked-token')->postJson('/api/scan', ['uid' => '04A1B2C3'])
        ->assertStatus(401);
});

test('POST /api/scan validates payload and replays idempotency_key without duplicate side effects', function () {
    $token = 'valid-station-token';
    Device::factory()->withPlainToken($token)->create();

    $year = AcademicYear::factory()->create(['is_active' => true]);
    $classroom = Classroom::factory()->create(['academic_year_id' => $year->id]);
    $student = Student::factory()->create(['classroom_id' => $classroom->id, 'is_active' => true]);

    NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
    ]);

    $this->withToken($token)->postJson('/api/scan', [])
        ->assertStatus(422);

    $idempotencyKey = '6f1b3c1e-8b0a-4a2e-9f55-0d3f2c1a7e10';

    $first = $this->withToken($token)->postJson('/api/scan', [
        'uid' => '0077705923',
        'scanned_at' => '2026-11-02T06:50:00+07:00',
        'idempotency_key' => $idempotencyKey,
    ]);

    $first->assertOk()
        ->assertJsonPath('result', ScanResultEnum::SuccessIn->value);

    $replay = $this->withToken($token)->postJson('/api/scan', [
        'uid' => '0077705923',
        'scanned_at' => '2026-11-02T06:50:00+07:00',
        'idempotency_key' => $idempotencyKey,
    ]);

    $replay->assertOk()
        ->assertJsonPath('result', ScanResultEnum::SuccessIn->value);

    expect(Attendance::count())->toBe(1)
        ->and(AttendanceLog::count())->toBe(1);
});

test('POST /api/scan enforces rate limiting with HTTP 429', function () {
    config()->set('services.scan.rate_limit_per_minute', 3);

    $token = 'rate-limited-station-token';
    Device::factory()->withPlainToken($token)->create();

    for ($i = 0; $i < 3; $i++) {
        $this->withToken($token)->postJson('/api/scan', [
            'uid' => '04A1B2C3',
        ])->assertOk();
    }

    $this->withToken($token)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
    ])->assertStatus(429);
});

test('Scan Station page is accessible to piket, admin_tu, and super_admin but returns 403 for wali_kelas and kepala_sekolah', function () {
    $piket = User::factory()->create();
    $piket->assignRole(RoleEnum::Piket->value);

    $adminTu = User::factory()->create();
    $adminTu->assignRole(RoleEnum::AdminTu->value);

    $waliKelas = User::factory()->create();
    $waliKelas->assignRole(RoleEnum::WaliKelas->value);

    $kepsek = User::factory()->create();
    $kepsek->assignRole(RoleEnum::KepalaSekolah->value);

    $this->actingAs($piket)->get('/scan-station')->assertOk();
    $this->actingAs($adminTu)->get('/scan-station')->assertOk();
    $this->actingAs($waliKelas)->get('/scan-station')->assertForbidden();
    $this->actingAs($kepsek)->get('/scan-station')->assertForbidden();
});

