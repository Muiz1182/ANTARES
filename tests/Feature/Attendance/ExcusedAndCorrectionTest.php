<?php

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Enums\NfcCardStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\ScanResultEnum;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ScanService;
use Carbon\CarbonImmutable;
use Database\Seeders\AttendanceRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        AttendanceRuleSeeder::class,
    ]);
});

test('wali_kelas can record multi-day excused attendance with attachment for their own homeroom student and cannot excuse another classroom student', function (): void {
    Storage::fake('public');

    $waliOwn = User::factory()->create();
    $waliOwn->assignRole(RoleEnum::WaliKelas->value);

    $waliOther = User::factory()->create();
    $waliOther->assignRole(RoleEnum::WaliKelas->value);

    $ownClassroom = Classroom::factory()->create([
        'homeroom_teacher_id' => $waliOwn->id,
    ]);
    $otherClassroom = Classroom::factory()->create([
        'homeroom_teacher_id' => $waliOther->id,
    ]);

    $ownStudent = Student::factory()->create([
        'classroom_id' => $ownClassroom->id,
        'is_active' => true,
    ]);
    $otherStudent = Student::factory()->create([
        'classroom_id' => $otherClassroom->id,
        'is_active' => true,
    ]);

    $this->actingAs($waliOwn)
        ->get(route('attendances.today', ['classroom_id' => $ownClassroom->id, 'date' => '2026-07-20']))
        ->assertOk();

    $file = UploadedFile::fake()->create('surat-dokter.pdf', 120, 'application/pdf');

    // Monday 2026-07-20 to Tuesday 2026-07-21 (2 school days)
    $this->actingAs($waliOwn)
        ->post(route('attendances.excuse'), [
            'student_id' => $ownStudent->id,
            'status' => AttendanceStatusEnum::Sick->value,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-21',
            'note' => 'Demam tinggi, surat dokter terlampir',
            'attachment' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $mondayAttendance = Attendance::query()
        ->where('attendable_type', Student::class)
        ->where('attendable_id', $ownStudent->id)
        ->where('date', '2026-07-20')
        ->firstOrFail();

    expect($mondayAttendance->status)->toBe(AttendanceStatusEnum::Sick)
        ->and($mondayAttendance->source)->toBe(AttendanceSourceEnum::Manual)
        ->and($mondayAttendance->created_by)->toBe($waliOwn->id)
        ->and($mondayAttendance->attachment_path)->not->toBeNull();

    Storage::disk('public')->assertExists($mondayAttendance->attachment_path);

    expect(
        Attendance::query()
            ->where('attendable_type', Student::class)
            ->where('attendable_id', $ownStudent->id)
            ->whereIn('date', ['2026-07-20', '2026-07-21'])
            ->count()
    )->toBe(2);

    expect(
        AuditLog::query()
            ->where('action', 'attendance.excused')
            ->where('user_id', $waliOwn->id)
            ->count()
    )->toBe(2);

    // Attempting to excuse a student from another classroom must return 403
    $this->actingAs($waliOwn)
        ->post(route('attendances.excuse'), [
            'student_id' => $otherStudent->id,
            'status' => AttendanceStatusEnum::Permission->value,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-20',
            'note' => 'Izin keluarga',
        ])
        ->assertForbidden();
});

test('tapping NFC card for a student who has an excused entry today returns blocked_excused and does not overwrite the row', function (): void {
    $piket = User::factory()->create();
    $piket->assignRole(RoleEnum::Piket->value);

    $device = Device::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['is_active' => true]);

    NfcCard::factory()->create([
        'uid' => 'DD112233',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
        'status' => NfcCardStatusEnum::Active,
    ]);

    $this->actingAs($piket)
        ->post(route('attendances.excuse'), [
            'student_id' => $student->id,
            'status' => AttendanceStatusEnum::Permission->value,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-20',
            'note' => 'Izin urusan keluarga',
        ])
        ->assertRedirect();

    $scanResult = app(ScanService::class)->processTap(
        'DD112233',
        $device,
        CarbonImmutable::create(2026, 7, 20, 7, 5, 0, 'Asia/Jakarta'),
    );

    expect($scanResult['ok'])->toBeFalse()
        ->and($scanResult['result'])->toBe(ScanResultEnum::BlockedExcused->value);

    $attendance = Attendance::query()
        ->where('attendable_type', Student::class)
        ->where('attendable_id', $student->id)
        ->where('date', '2026-07-20')
        ->firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatusEnum::Permission)
        ->and($attendance->check_in_at)->toBeNull();
});

test('attendance correction requires a mandatory reason and writes before and after snapshots to audit_logs', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::AdminTu->value);

    $wali = User::factory()->create();
    $wali->assignRole(RoleEnum::WaliKelas->value);

    $student = Student::factory()->create(['is_active' => true]);

    $attendance = Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $student->id,
        'date' => '2026-07-20',
        'status' => AttendanceStatusEnum::Late,
        'check_in_at' => CarbonImmutable::create(2026, 7, 20, 7, 25, 0, 'Asia/Jakarta'),
        'source' => AttendanceSourceEnum::Nfc,
    ]);

    // Missing reason -> validation error
    $this->actingAs($admin)
        ->post(route('attendances.correct'), [
            'student_id' => $student->id,
            'date' => '2026-07-20',
            'status' => AttendanceStatusEnum::Present->value,
            'check_in_time' => '07:05',
            'reason' => '',
        ])
        ->assertSessionHasErrors(['reason']);

    // Wali kelas cannot correct attendance -> 403
    $this->actingAs($wali)
        ->post(route('attendances.correct'), [
            'student_id' => $student->id,
            'date' => '2026-07-20',
            'status' => AttendanceStatusEnum::Present->value,
            'check_in_time' => '07:05',
            'reason' => 'Koreksi jam masuk karena antrean di gerbang',
        ])
        ->assertForbidden();

    // Admin TU submits valid correction with mandatory reason
    $this->actingAs($admin)
        ->post(route('attendances.correct'), [
            'student_id' => $student->id,
            'date' => '2026-07-20',
            'status' => AttendanceStatusEnum::Present->value,
            'check_in_time' => '07:05',
            'check_out_time' => '14:15',
            'note' => 'Antrean pos barat padat',
            'reason' => 'Siswa sudah berada di gerbang pukul 07:05 sesuai konfirmasi petugas piket',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $attendance->refresh();
    expect($attendance->status)->toBe(AttendanceStatusEnum::Present)
        ->and($attendance->check_in_at?->setTimezone('Asia/Jakarta')->format('H:i'))->toBe('07:05')
        ->and($attendance->check_out_at?->setTimezone('Asia/Jakarta')->format('H:i'))->toBe('14:15')
        ->and($attendance->source)->toBe(AttendanceSourceEnum::Manual);

    $auditLog = AuditLog::query()
        ->where('action', 'attendance.corrected')
        ->where('subject_type', Attendance::class)
        ->where('subject_id', $attendance->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->user_id)->toBe($admin->id)
        ->and($auditLog->reason)->toBe('Siswa sudah berada di gerbang pukul 07:05 sesuai konfirmasi petugas piket')
        ->and($auditLog->before['status'])->toBe(AttendanceStatusEnum::Late->value)
        ->and($auditLog->before['check_in_at'])->toBe('07:25:00')
        ->and($auditLog->after['status'])->toBe(AttendanceStatusEnum::Present->value)
        ->and($auditLog->after['check_in_at'])->toBe('07:05:00');
});

test('super_admin and admin_tu can view audit logs while wali_kelas, piket, and kepala_sekolah receive 403', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::AdminTu->value);

    $kepsek = User::factory()->create();
    $kepsek->assignRole(RoleEnum::KepalaSekolah->value);

    $wali = User::factory()->create();
    $wali->assignRole(RoleEnum::WaliKelas->value);

    $piket = User::factory()->create();
    $piket->assignRole(RoleEnum::Piket->value);

    $this->actingAs($superAdmin)
        ->get(route('audit-logs.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('audit-logs.index', ['action' => 'attendance.corrected']))
        ->assertOk();

    $this->actingAs($kepsek)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($wali)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($piket)
        ->get(route('audit-logs.index'))
        ->assertForbidden();
});
