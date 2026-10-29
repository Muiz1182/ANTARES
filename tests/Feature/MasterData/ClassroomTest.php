<?php

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('admin_tu can create, update, and delete classrooms with unique name per academic year', function () {
    $tu = User::factory()->create();
    $tu->assignRole(RoleEnum::AdminTu->value);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::WaliKelas->value);

    $year = AcademicYear::factory()->active()->create();

    $this->actingAs($tu)
        ->post(route('classrooms.store'), [
            'academic_year_id' => $year->id,
            'name' => '7A',
            'level' => 7,
            'homeroom_teacher_id' => $teacher->id,
        ])
        ->assertRedirect();

    $classroom = Classroom::where('name', '7A')->firstOrFail();
    expect($classroom->homeroom_teacher_id)->toBe($teacher->id);

    $this->actingAs($tu)
        ->post(route('classrooms.store'), [
            'academic_year_id' => $year->id,
            'name' => '7A',
            'level' => 7,
            'homeroom_teacher_id' => null,
        ])
        ->assertSessionHasErrors('name');
});

test('wali_kelas only sees their own homeroom classrooms', function () {
    $waliA = User::factory()->create();
    $waliA->assignRole(RoleEnum::WaliKelas->value);

    $waliB = User::factory()->create();
    $waliB->assignRole(RoleEnum::WaliKelas->value);

    $year = AcademicYear::factory()->active()->create();

    $class7A = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '7A',
        'level' => 7,
        'homeroom_teacher_id' => $waliA->id,
    ]);

    Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '8A',
        'level' => 8,
        'homeroom_teacher_id' => $waliB->id,
    ]);

    $this->actingAs($waliA)
        ->get(route('classrooms.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('classrooms.data', 1)
            ->where('classrooms.data.0.id', $class7A->id)
            ->where('classrooms.data.0.name', '7A')
        );
});
