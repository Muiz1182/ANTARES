<?php

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Enums\RoleEnum;
use App\Jobs\GenerateAttendanceExportJob;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\Report\AttendanceReportService;
use Database\Seeders\AttendanceRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchoolSettingSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        AttendanceRuleSeeder::class,
        SchoolSettingSeeder::class,
    ]);
});

test('monthly class recap totals match daily data and export to Excel and PDF open validly', function (): void {
    Storage::fake('local');

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::AdminTu->value);

    $classroom = Classroom::factory()->create(['name' => '8A', 'level' => 8]);
    $stOne = Student::factory()->create([
        'nis' => '260801',
        'name' => 'Andi Saputra',
        'classroom_id' => $classroom->id,
        'is_active' => true,
    ]);
    $stTwo = Student::factory()->create([
        'nis' => '260802',
        'name' => 'Bunga Citra',
        'classroom_id' => $classroom->id,
        'is_active' => true,
    ]);

    // Create daily records in July 2026
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stOne->id,
        'date' => '2026-07-20',
        'status' => AttendanceStatusEnum::Present,
        'source' => AttendanceSourceEnum::Nfc,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stOne->id,
        'date' => '2026-07-21',
        'status' => AttendanceStatusEnum::Late,
        'source' => AttendanceSourceEnum::Nfc,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stOne->id,
        'date' => '2026-07-22',
        'status' => AttendanceStatusEnum::Sick,
        'source' => AttendanceSourceEnum::Manual,
    ]);

    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stTwo->id,
        'date' => '2026-07-20',
        'status' => AttendanceStatusEnum::Absent,
        'source' => AttendanceSourceEnum::System,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stTwo->id,
        'date' => '2026-07-21',
        'status' => AttendanceStatusEnum::Permission,
        'source' => AttendanceSourceEnum::Manual,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stTwo->id,
        'date' => '2026-07-22',
        'status' => AttendanceStatusEnum::Dispensation,
        'source' => AttendanceSourceEnum::Manual,
    ]);

    $matrix = app(AttendanceReportService::class)->getMonthlyClassMatrix($classroom, '2026-07');

    expect($matrix['class_totals'])->toEqual([
        'H' => 1,
        'T' => 1,
        'I' => 1,
        'S' => 1,
        'A' => 1,
        'D' => 1,
    ]);

    $rowOne = collect($matrix['rows'])->firstWhere('student_id', $stOne->id);
    expect($rowOne['cells']['2026-07-20'])->toBe('H')
        ->and($rowOne['cells']['2026-07-21'])->toBe('T')
        ->and($rowOne['cells']['2026-07-22'])->toBe('S')
        ->and($rowOne['totals']['H'])->toBe(1)
        ->and($rowOne['totals']['T'])->toBe(1)
        ->and($rowOne['totals']['S'])->toBe(1);

    // Verify Reports page renders
    $this->actingAs($admin)
        ->get(route('reports.index', ['classroom_id' => $classroom->id, 'month' => '2026-07']))
        ->assertOk();

    // Verify Excel export download
    $excelRes = $this->actingAs($admin)
        ->get(route('reports.export.excel', ['classroom_id' => $classroom->id, 'month' => '2026-07']));

    $excelRes->assertOk();
    $excelFilePath = $excelRes->baseResponse->getFile()->getPathname();
    expect(is_file($excelFilePath))->toBeTrue()
        ->and(filesize($excelFilePath))->toBeGreaterThan(100);

    // Verify queued Excel export job
    $job = new GenerateAttendanceExportJob($classroom->id, '2026-07', 'exports/rekap-8a-2026-07.xlsx');
    app()->call([$job, 'handle']);
    Storage::disk('local')->assertExists('exports/rekap-8a-2026-07.xlsx');

    // Verify PDF export download starts with %PDF-1.4 and contains student & school info
    $pdfRes = $this->actingAs($admin)
        ->get(route('reports.export.pdf', ['classroom_id' => $classroom->id, 'month' => '2026-07']));

    $pdfRes->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $pdfContent = $pdfRes->getContent();
    expect(str_starts_with($pdfContent, '%PDF-1.4'))->toBeTrue()
        ->and($pdfContent)->toContain('Andi Saputra')
        ->and($pdfContent)->toContain('Bunga Citra');
});

test('wali_kelas can only view and export their own homeroom classes while kepala_sekolah has read-only access', function (): void {
    $waliOwn = User::factory()->create();
    $waliOwn->assignRole(RoleEnum::WaliKelas->value);

    $waliOther = User::factory()->create();
    $waliOther->assignRole(RoleEnum::WaliKelas->value);

    $kepsek = User::factory()->create();
    $kepsek->assignRole(RoleEnum::KepalaSekolah->value);

    $piket = User::factory()->create();
    $piket->assignRole(RoleEnum::Piket->value);

    $ownClass = Classroom::factory()->create([
        'name' => '7A',
        'homeroom_teacher_id' => $waliOwn->id,
    ]);
    $otherClass = Classroom::factory()->create([
        'name' => '7B',
        'homeroom_teacher_id' => $waliOther->id,
    ]);

    // Wali kelas can view and export own class
    $this->actingAs($waliOwn)
        ->get(route('reports.index', ['classroom_id' => $ownClass->id, 'month' => '2026-07']))
        ->assertOk();

    $this->actingAs($waliOwn)
        ->get(route('reports.export.excel', ['classroom_id' => $ownClass->id, 'month' => '2026-07']))
        ->assertOk();

    $this->actingAs($waliOwn)
        ->get(route('reports.export.pdf', ['classroom_id' => $ownClass->id, 'month' => '2026-07']))
        ->assertOk();

    // Wali kelas gets 403 for other teacher's class
    $this->actingAs($waliOwn)
        ->get(route('reports.index', ['classroom_id' => $otherClass->id, 'month' => '2026-07']))
        ->assertForbidden();

    $this->actingAs($waliOwn)
        ->get(route('reports.export.excel', ['classroom_id' => $otherClass->id, 'month' => '2026-07']))
        ->assertForbidden();

    $this->actingAs($waliOwn)
        ->get(route('reports.export.pdf', ['classroom_id' => $otherClass->id, 'month' => '2026-07']))
        ->assertForbidden();

    // Kepala sekolah can view and export any class
    $this->actingAs($kepsek)
        ->get(route('reports.index', ['classroom_id' => $otherClass->id, 'month' => '2026-07']))
        ->assertOk();

    $this->actingAs($kepsek)
        ->get(route('reports.export.pdf', ['classroom_id' => $otherClass->id, 'month' => '2026-07']))
        ->assertOk();

    // Piket is forbidden from monthly reports
    $this->actingAs($piket)
        ->get(route('reports.index'))
        ->assertForbidden();
});

