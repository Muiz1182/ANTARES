<?php

use App\Actions\Attendance\MarkAutoAbsentAction;
use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Enums\NfcCardStatusEnum;
use App\Enums\ScanResultEnum;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\NfcCard;
use App\Models\Student;
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

test('attendance:mark-absent does not mark anyone before absent_cutoff', function (): void {
    Student::factory()->count(3)->create(['is_active' => true]);

    // Monday 2026-07-20 at 09:30 (before 10:00 absent_cutoff)
    $this->travelTo(CarbonImmutable::create(2026, 7, 20, 9, 30, 0, 'Asia/Jakarta'));

    $this->artisan('attendance:mark-absent')
        ->assertSuccessful();

    expect(Attendance::query()->count())->toBe(0);
});

test('attendance:mark-absent marks only unrecorded active students after cutoff and is idempotent on repeat runs', function (): void {
    $unrecordedActiveOne = Student::factory()->create(['is_active' => true]);
    $unrecordedActiveTwo = Student::factory()->create(['is_active' => true]);
    $inactiveStudent = Student::factory()->create(['is_active' => false]);
    $presentStudent = Student::factory()->create(['is_active' => true]);
    $sickStudent = Student::factory()->create(['is_active' => true]);

    $dateString = '2026-07-20';

    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $presentStudent->id,
        'date' => $dateString,
        'status' => AttendanceStatusEnum::Present,
        'source' => AttendanceSourceEnum::Nfc,
    ]);

    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $sickStudent->id,
        'date' => $dateString,
        'status' => AttendanceStatusEnum::Sick,
        'source' => AttendanceSourceEnum::Manual,
    ]);

    // Monday 2026-07-20 at 10:05 (after 10:00 absent_cutoff)
    $this->travelTo(CarbonImmutable::create(2026, 7, 20, 10, 5, 0, 'Asia/Jakarta'));

    $this->artisan('attendance:mark-absent')
        ->assertSuccessful();

    expect(Attendance::query()->where('date', $dateString)->count())->toBe(4);

    $autoAbsentRow = Attendance::query()
        ->where('attendable_type', Student::class)
        ->where('attendable_id', $unrecordedActiveOne->id)
        ->where('date', $dateString)
        ->firstOrFail();

    expect($autoAbsentRow->status)->toBe(AttendanceStatusEnum::Absent)
        ->and($autoAbsentRow->source)->toBe(AttendanceSourceEnum::System)
        ->and($autoAbsentRow->created_by)->toBeNull();

    expect(
        Attendance::query()
            ->where('attendable_type', Student::class)
            ->where('attendable_id', $inactiveStudent->id)
            ->exists()
    )->toBeFalse();

    // Run a second time on the same day -> idempotent (0 new rows, no errors)
    $markedSecondRun = app(MarkAutoAbsentAction::class)->execute(
        CarbonImmutable::create(2026, 7, 20, 10, 10, 0, 'Asia/Jakarta')
    );

    expect($markedSecondRun)->toBe(0)
        ->and(Attendance::query()->where('date', $dateString)->count())->toBe(4);
});

test('attendance:mark-absent skips holidays and non-school days', function (): void {
    Student::factory()->count(2)->create(['is_active' => true]);

    // Sunday 2026-07-19 at 11:00 (is_school_day = false)
    $sundayMarked = app(MarkAutoAbsentAction::class)->execute(
        CarbonImmutable::create(2026, 7, 19, 11, 0, 0, 'Asia/Jakarta')
    );
    expect($sundayMarked)->toBe(0);

    // Monday 2026-07-20 registered as a Holiday
    Holiday::factory()->create([
        'date' => '2026-07-20',
        'name' => 'Libur Nasional Test',
    ]);

    $holidayMarked = app(MarkAutoAbsentAction::class)->execute(
        CarbonImmutable::create(2026, 7, 20, 11, 0, 0, 'Asia/Jakarta')
    );
    expect($holidayMarked)->toBe(0)
        ->and(Attendance::query()->count())->toBe(0);
});

test('subsequent NFC check-in tap after auto-absent converts row to late and records an audit log', function (): void {
    $device = Device::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['is_active' => true]);

    NfcCard::factory()->create([
        'uid' => 'CC001122',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
        'status' => NfcCardStatusEnum::Active,
    ]);

    // Auto-absent runs at 10:05 on Monday 2026-07-20
    $marked = app(MarkAutoAbsentAction::class)->execute(
        CarbonImmutable::create(2026, 7, 20, 10, 5, 0, 'Asia/Jakarta')
    );
    expect($marked)->toBe(1);

    $attendance = Attendance::query()
        ->where('attendable_type', Student::class)
        ->where('attendable_id', $student->id)
        ->where('date', '2026-07-20')
        ->firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatusEnum::Absent);

    // Student arrives late and taps NFC card at 10:20 (before check_out_start 14:00)
    $scanResult = app(ScanService::class)->processTap(
        'CC001122',
        $device,
        CarbonImmutable::create(2026, 7, 20, 10, 20, 0, 'Asia/Jakarta'),
    );

    expect($scanResult['result'])->toBe(ScanResultEnum::SuccessIn->value)
        ->and($scanResult['attendance']['status'])->toBe(AttendanceStatusEnum::Late->value);

    $auditLog = AuditLog::query()
        ->where('action', 'attendance.auto_absent_overridden')
        ->where('subject_type', Attendance::class)
        ->where('subject_id', $attendance->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->before['status'])->toBe(AttendanceStatusEnum::Absent->value)
        ->and($auditLog->after['status'])->toBe(AttendanceStatusEnum::Late->value);
});
