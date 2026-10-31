<?php

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Enums\NfcCardStatusEnum;
use App\Enums\ScanResultEnum;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\NfcCard;
use App\Models\Student;
use Database\Seeders\AttendanceRuleSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed([
        RolePermissionSeeder::class,
        AttendanceRuleSeeder::class,
    ]);

    $this->deviceToken = 'test-station-secret-token-001';
    $this->device = Device::factory()->withPlainToken($this->deviceToken)->create([
        'name' => 'Pos Gerbang Uji',
        'is_active' => true,
    ]);

    $year = AcademicYear::factory()->create(['is_active' => true]);
    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '7A',
        'level' => 7,
    ]);
});

test('1. unknown_card: returns unknown_card and logs tap when UID is unregistered or malformed', function () {
    $response = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '0077705923',
        'scanned_at' => '2026-11-02T06:45:00+07:00',
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::UnknownCard->value)
        ->assertJsonPath('student', null)
        ->assertJsonPath('attendance', null);

    expect(AttendanceLog::count())->toBe(1)
        ->and(AttendanceLog::first()->result)->toBe(ScanResultEnum::UnknownCard)
        ->and(AttendanceLog::first()->uid_normalized)->toBe('04A1B2C3')
        ->and(Attendance::count())->toBe(0);
});

test('2. inactive_card and lost_card: rejects deactivated and lost cards while returning owner identity for operator warning', function () {
    $student = Student::factory()->create([
        'classroom_id' => $this->classroom->id,
        'name' => 'Andi Pratama',
        'is_active' => true,
    ]);

    NfcCard::factory()->inactive()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
    ]);

    NfcCard::factory()->lost()->create([
        'uid' => 'C3B2A104',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
    ]);

    $inactiveRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '0077705923',
        'scanned_at' => '2026-11-02T06:50:00+07:00',
    ]);

    $inactiveRes->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::InactiveCard->value)
        ->assertJsonPath('student.name', 'Andi Pratama');

    $lostRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '3283263748',
        'scanned_at' => '2026-11-02T06:51:00+07:00',
    ]);

    $lostRes->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::LostCard->value)
        ->assertJsonPath('student.name', 'Andi Pratama');

    expect(Attendance::count())->toBe(0)
        ->and(AttendanceLog::count())->toBe(2);
});

test('3. student_inactive: rejects tap when owner student is inactive or soft-deleted', function () {
    $student = Student::factory()->create([
        'classroom_id' => $this->classroom->id,
        'is_active' => false,
    ]);

    NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
        'status' => NfcCardStatusEnum::Active,
    ]);

    $res = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
        'scanned_at' => '2026-11-02T06:45:00+07:00',
    ]);

    $res->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::StudentInactive->value);

    expect(Attendance::count())->toBe(0);
});

test('4. no_school_day: rejects tap on holidays and non-school weekend days', function () {
    $student = Student::factory()->create([
        'classroom_id' => $this->classroom->id,
        'is_active' => true,
    ]);

    NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
    ]);

    Holiday::factory()->create([
        'date' => '2026-11-03',
        'name' => 'Libur Khusus Sekolah',
    ]);

    $holidayRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
        'scanned_at' => '2026-11-03T06:45:00+07:00',
    ]);

    $holidayRes->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::NoSchoolDay->value);

    $sundayRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
        'scanned_at' => '2026-11-01T06:45:00+07:00',
    ]);

    $sundayRes->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::NoSchoolDay->value);

    expect(Attendance::count())->toBe(0)
        ->and(AttendanceLog::count())->toBe(2);
});

test('5 & 6. success_in (present & late), duplicate inside debounce window, and already_checked_in after debounce window', function () {
    $studentPresent = Student::factory()->create([
        'classroom_id' => $this->classroom->id,
        'name' => 'Citra Dewi',
        'is_active' => true,
    ]);
    $studentLate = Student::factory()->create([
        'classroom_id' => $this->classroom->id,
        'name' => 'Dimas Anggara',
        'is_active' => true,
    ]);

    NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $studentPresent->id,
    ]);

    NfcCard::factory()->create([
        'uid' => 'C3B2A104',
        'cardable_type' => Student::class,
        'cardable_id' => $studentLate->id,
    ]);

    $firstTap = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '0077705923',
        'scanned_at' => '2026-11-02T07:00:00+07:00',
    ]);

    $firstTap->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result', ScanResultEnum::SuccessIn->value)
        ->assertJsonPath('student.name', 'Citra Dewi')
        ->assertJsonPath('attendance.status', AttendanceStatusEnum::Present->value);

    $duplicateTap = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '0077705923',
        'scanned_at' => '2026-11-02T07:00:15+07:00',
    ]);

    $duplicateTap->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::Duplicate->value);

    $alreadyInTap = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '0077705923',
        'scanned_at' => '2026-11-02T07:01:00+07:00',
    ]);

    $alreadyInTap->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::AlreadyCheckedIn->value);

    $lateTap = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '3283263748',
        'scanned_at' => '2026-11-02T07:00:01+07:00',
    ]);

    $lateTap->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result', ScanResultEnum::SuccessIn->value)
        ->assertJsonPath('attendance.status', AttendanceStatusEnum::Late->value);

    expect(Attendance::count())->toBe(2)
        ->and(AttendanceLog::count())->toBe(4);
});

test('6b. success_in overwrites an auto-absent row while blocked_excused protects permission/sick/dispensation rows', function () {
    $studentAbsent = Student::factory()->create(['classroom_id' => $this->classroom->id, 'is_active' => true]);
    $studentSick = Student::factory()->create(['classroom_id' => $this->classroom->id, 'is_active' => true]);

    NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $studentAbsent->id,
    ]);

    NfcCard::factory()->create([
        'uid' => 'C3B2A104',
        'cardable_type' => Student::class,
        'cardable_id' => $studentSick->id,
    ]);

    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $studentAbsent->id,
        'date' => '2026-11-02',
        'check_in_at' => null,
        'status' => AttendanceStatusEnum::Absent,
        'source' => AttendanceSourceEnum::System,
    ]);

    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $studentSick->id,
        'date' => '2026-11-02',
        'check_in_at' => null,
        'status' => AttendanceStatusEnum::Sick,
        'source' => AttendanceSourceEnum::Manual,
    ]);

    $convertRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
        'scanned_at' => '2026-11-02T10:15:00+07:00',
    ]);

    $convertRes->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result', ScanResultEnum::SuccessIn->value)
        ->assertJsonPath('attendance.status', AttendanceStatusEnum::Late->value);

    $blockedRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => 'C3B2A104',
        'scanned_at' => '2026-11-02T08:00:00+07:00',
    ]);

    $blockedRes->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::BlockedExcused->value)
        ->assertJsonPath('attendance.status', AttendanceStatusEnum::Sick->value);
});

test('7. check-out window: handles success_out, already_checked_out, and no_checkin without creating absent row', function () {
    $studentCheckedIn = Student::factory()->create(['classroom_id' => $this->classroom->id, 'is_active' => true]);
    $studentNoCheckin = Student::factory()->create(['classroom_id' => $this->classroom->id, 'is_active' => true]);

    NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $studentCheckedIn->id,
    ]);

    NfcCard::factory()->create([
        'uid' => 'C3B2A104',
        'cardable_type' => Student::class,
        'cardable_id' => $studentNoCheckin->id,
    ]);

    $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
        'scanned_at' => '2026-11-02T06:40:00+07:00',
    ])->assertOk()->assertJsonPath('result', ScanResultEnum::SuccessIn->value);

    $outRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
        'scanned_at' => '2026-11-02T14:05:00+07:00',
    ]);

    $outRes->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result', ScanResultEnum::SuccessOut->value);

    expect($outRes->json('attendance.check_out_at'))->not->toBeNull();

    $alreadyOutRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => '04A1B2C3',
        'scanned_at' => '2026-11-02T14:06:00+07:00',
    ]);

    $alreadyOutRes->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::AlreadyCheckedOut->value);

    $noCheckinRes = $this->withToken($this->deviceToken)->postJson('/api/scan', [
        'uid' => 'C3B2A104',
        'scanned_at' => '2026-11-02T14:10:00+07:00',
    ]);

    $noCheckinRes->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('result', ScanResultEnum::NoCheckin->value)
        ->assertJsonPath('attendance', null);

    expect(Attendance::where('attendable_id', $studentNoCheckin->id)->exists())->toBeFalse();
});
