<?php

use App\Enums\AttendanceStatusEnum;
use App\Enums\NfcCardStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\ScanResultEnum;
use App\Models\AttendanceRule;
use App\Models\Device;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ScanService;
use Carbon\CarbonImmutable;
use Database\Seeders\AttendanceRuleSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        AttendanceRuleSeeder::class,
    ]);
});

test('admin_tu can view and update attendance rules for all 7 days', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::AdminTu->value);

    $this->actingAs($admin)
        ->get(route('attendance-rules.index'))
        ->assertOk();

    $payload = [
        'rules' => AttendanceRule::query()
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (AttendanceRule $rule): array => [
                'day_of_week' => $rule->day_of_week,
                'is_school_day' => $rule->day_of_week === 1 ? true : $rule->is_school_day,
                'check_in_start' => $rule->day_of_week === 1 ? '06:00' : substr($rule->check_in_start, 0, 5),
                'late_after' => $rule->day_of_week === 1 ? '07:20' : substr($rule->late_after, 0, 5),
                'check_out_start' => $rule->day_of_week === 1 ? '14:30' : substr($rule->check_out_start, 0, 5),
                'absent_cutoff' => $rule->day_of_week === 1 ? '09:30' : substr($rule->absent_cutoff, 0, 5),
                'debounce_seconds' => $rule->day_of_week === 1 ? 25 : $rule->debounce_seconds,
            ])
            ->values()
            ->all(),
    ];

    $this->actingAs($admin)
        ->put(route('attendance-rules.update'), $payload)
        ->assertRedirect(route('attendance-rules.index'))
        ->assertSessionHasNoErrors();

    $mondayRule = AttendanceRule::query()->where('day_of_week', 1)->firstOrFail();
    expect(substr($mondayRule->late_after, 0, 5))->toBe('07:20')
        ->and(substr($mondayRule->check_out_start, 0, 5))->toBe('14:30')
        ->and($mondayRule->debounce_seconds)->toBe(25);
});

test('wali_kelas and piket are forbidden from managing attendance rules', function (): void {
    $wali = User::factory()->create();
    $wali->assignRole(RoleEnum::WaliKelas->value);

    $piket = User::factory()->create();
    $piket->assignRole(RoleEnum::Piket->value);

    $this->actingAs($wali)
        ->get(route('attendance-rules.index'))
        ->assertForbidden();

    $this->actingAs($piket)
        ->put(route('attendance-rules.update'), ['rules' => []])
        ->assertForbidden();
});

test('attendance rules validation rejects illogical time ordering', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::AdminTu->value);

    $rules = AttendanceRule::query()
        ->orderBy('day_of_week')
        ->get()
        ->map(fn (AttendanceRule $rule): array => [
            'day_of_week' => $rule->day_of_week,
            'is_school_day' => $rule->is_school_day,
            'check_in_start' => $rule->day_of_week === 1 ? '07:30' : substr($rule->check_in_start, 0, 5),
            'late_after' => $rule->day_of_week === 1 ? '07:15' : substr($rule->late_after, 0, 5),
            'check_out_start' => substr($rule->check_out_start, 0, 5),
            'absent_cutoff' => substr($rule->absent_cutoff, 0, 5),
            'debounce_seconds' => $rule->debounce_seconds,
        ])
        ->values()
        ->all();

    $this->actingAs($admin)
        ->put(route('attendance-rules.update'), ['rules' => $rules])
        ->assertSessionHasErrors(['rules.0.late_after']);
});

test('editing late_after for Monday immediately changes whether a 07:10 Monday scan is present or late', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::AdminTu->value);

    $device = Device::factory()->create(['is_active' => true]);
    $studentOne = Student::factory()->create(['is_active' => true]);
    $studentTwo = Student::factory()->create(['is_active' => true]);

    NfcCard::factory()->create([
        'uid' => 'AABBCC01',
        'cardable_type' => Student::class,
        'cardable_id' => $studentOne->id,
        'status' => NfcCardStatusEnum::Active,
    ]);

    NfcCard::factory()->create([
        'uid' => 'AABBCC02',
        'cardable_type' => Student::class,
        'cardable_id' => $studentTwo->id,
        'status' => NfcCardStatusEnum::Active,
    ]);

    // Update Monday late_after to 07:05 -> a scan at 07:10 must be late
    $rulesWithEarlyLateCutoff = AttendanceRule::query()
        ->orderBy('day_of_week')
        ->get()
        ->map(fn (AttendanceRule $rule): array => [
            'day_of_week' => $rule->day_of_week,
            'is_school_day' => $rule->is_school_day,
            'check_in_start' => substr($rule->check_in_start, 0, 5),
            'late_after' => $rule->day_of_week === 1 ? '07:05' : substr($rule->late_after, 0, 5),
            'check_out_start' => substr($rule->check_out_start, 0, 5),
            'absent_cutoff' => substr($rule->absent_cutoff, 0, 5),
            'debounce_seconds' => $rule->debounce_seconds,
        ])
        ->values()
        ->all();

    $this->actingAs($admin)
        ->put(route('attendance-rules.update'), ['rules' => $rulesWithEarlyLateCutoff])
        ->assertRedirect();

    $scanService = app(ScanService::class);
    $mondayAtSevenTen = CarbonImmutable::create(2026, 7, 20, 7, 10, 0, 'Asia/Jakarta');

    $firstScan = $scanService->processTap('AABBCC01', $device, $mondayAtSevenTen);
    expect($firstScan['result'])->toBe(ScanResultEnum::SuccessIn->value)
        ->and($firstScan['attendance']['status'])->toBe(AttendanceStatusEnum::Late->value);

    // Update Monday late_after to 07:20 -> a scan at 07:10 must be present
    $rulesWithLaterCutoff = AttendanceRule::query()
        ->orderBy('day_of_week')
        ->get()
        ->map(fn (AttendanceRule $rule): array => [
            'day_of_week' => $rule->day_of_week,
            'is_school_day' => $rule->is_school_day,
            'check_in_start' => substr($rule->check_in_start, 0, 5),
            'late_after' => $rule->day_of_week === 1 ? '07:20' : substr($rule->late_after, 0, 5),
            'check_out_start' => substr($rule->check_out_start, 0, 5),
            'absent_cutoff' => substr($rule->absent_cutoff, 0, 5),
            'debounce_seconds' => $rule->debounce_seconds,
        ])
        ->values()
        ->all();

    $this->actingAs($admin)
        ->put(route('attendance-rules.update'), ['rules' => $rulesWithLaterCutoff])
        ->assertRedirect();

    $secondScan = $scanService->processTap('AABBCC02', $device, $mondayAtSevenTen);
    expect($secondScan['result'])->toBe(ScanResultEnum::SuccessIn->value)
        ->and($secondScan['attendance']['status'])->toBe(AttendanceStatusEnum::Present->value);
});
