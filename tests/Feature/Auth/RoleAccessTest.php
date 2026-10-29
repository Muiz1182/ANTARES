<?php

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('super_admin can access users and all master data routes', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.can.manage_users', true)
            ->where('auth.user.can.manage_master', true)
        );

    $this->actingAs($admin)->get(route('academic-years.index'))->assertOk();
    $this->actingAs($admin)->get(route('classrooms.index'))->assertOk();
    $this->actingAs($admin)->get(route('students.index'))->assertOk();
    $this->actingAs($admin)->get(route('holidays.index'))->assertOk();
});

test('admin_tu can manage master data but cannot access user management', function () {
    $tu = User::factory()->create();
    $tu->assignRole(RoleEnum::AdminTu->value);

    $this->actingAs($tu)
        ->get(route('students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.can.manage_users', false)
            ->where('auth.user.can.manage_master', true)
        );

    $this->actingAs($tu)->get(route('users.index'))->assertForbidden();
    $this->actingAs($tu)->post(route('users.store'), [])->assertForbidden();
});

test('kepala_sekolah can log in and only read master data', function () {
    $kepsek = User::factory()->create();
    $kepsek->assignRole(RoleEnum::KepalaSekolah->value);

    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->create(['academic_year_id' => $year->id]);
    $student = Student::factory()->create(['classroom_id' => $classroom->id]);
    $holiday = Holiday::factory()->create();

    $this->actingAs($kepsek)->get(route('dashboard'))->assertOk();
    $this->actingAs($kepsek)->get(route('academic-years.index'))->assertOk();
    $this->actingAs($kepsek)->get(route('classrooms.index'))->assertOk();
    $this->actingAs($kepsek)->get(route('students.index'))->assertOk();
    $this->actingAs($kepsek)->get(route('students.show', $student))->assertOk();
    $this->actingAs($kepsek)->get(route('holidays.index'))->assertOk();

    $this->actingAs($kepsek)->get(route('users.index'))->assertForbidden();
    $this->actingAs($kepsek)->get(route('students.create'))->assertForbidden();
    $this->actingAs($kepsek)->get(route('students.import.show'))->assertForbidden();

    $this->actingAs($kepsek)->post(route('academic-years.store'), [
        'name' => '2028/2029',
        'start_date' => '2028-07-10',
        'end_date' => '2029-06-20',
        'is_active' => false,
    ])->assertForbidden();

    $this->actingAs($kepsek)->post(route('classrooms.store'), [
        'academic_year_id' => $year->id,
        'name' => '7Z',
        'level' => 7,
    ])->assertForbidden();

    $this->actingAs($kepsek)->delete(route('students.destroy', $student))->assertForbidden();
    $this->actingAs($kepsek)->delete(route('holidays.destroy', $holiday))->assertForbidden();
});

test('wali_kelas and piket only see permitted routes and menu abilities', function () {
    $wali = User::factory()->create();
    $wali->assignRole(RoleEnum::WaliKelas->value);

    $piket = User::factory()->create();
    $piket->assignRole(RoleEnum::Piket->value);

    $this->actingAs($wali)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.can.manage_users', false)
            ->where('auth.user.can.manage_master', false)
            ->where('auth.user.can.view_classrooms', true)
            ->where('auth.user.can.view_students', true)
            ->where('auth.user.can.view_academic_years', false)
            ->where('auth.user.can.view_holidays', false)
        );

    $this->actingAs($wali)->get(route('academic-years.index'))->assertForbidden();
    $this->actingAs($wali)->get(route('holidays.index'))->assertForbidden();
    $this->actingAs($wali)->get(route('users.index'))->assertForbidden();
    $this->actingAs($wali)->get(route('students.import.show'))->assertForbidden();

    $this->actingAs($piket)->get(route('students.index'))->assertOk();
    $this->actingAs($piket)->get(route('classrooms.index'))->assertForbidden();
    $this->actingAs($piket)->get(route('academic-years.index'))->assertForbidden();
    $this->actingAs($piket)->get(route('users.index'))->assertForbidden();
});
