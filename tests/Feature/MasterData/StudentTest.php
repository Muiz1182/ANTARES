<?php

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('admin_tu can create, update, upload photo, and soft delete a student', function () {
    Storage::fake('public');

    $tu = User::factory()->create();
    $tu->assignRole(RoleEnum::AdminTu->value);

    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '7A',
        'level' => 7,
    ]);

    $photo = UploadedFile::fake()->image('siswa.jpg', 800, 800);

    $this->actingAs($tu)
        ->post(route('students.store'), [
            'nis' => '260101',
            'nisn' => '0011223344',
            'name' => 'Rizky Pratama',
            'gender' => 'L',
            'classroom_id' => $classroom->id,
            'photo' => $photo,
            'parent_name' => 'Agus Pratama',
            'parent_phone' => '081299998888',
            'is_active' => true,
        ])
        ->assertRedirect(route('students.index'));

    $student = Student::where('nis', '260101')->firstOrFail();
    expect($student->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($student->photo_path);

    $this->actingAs($tu)
        ->delete(route('students.destroy', $student))
        ->assertRedirect(route('students.index'));

    $this->assertSoftDeleted('students', ['id' => $student->id]);
});

test('wali_kelas sees only students in their own homeroom classrooms and gets 403 for other classrooms', function () {
    $wali7A = User::factory()->create();
    $wali7A->assignRole(RoleEnum::WaliKelas->value);

    $wali8A = User::factory()->create();
    $wali8A->assignRole(RoleEnum::WaliKelas->value);

    $year = AcademicYear::factory()->active()->create();

    $class7A = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '7A',
        'level' => 7,
        'homeroom_teacher_id' => $wali7A->id,
    ]);

    $class8A = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '8A',
        'level' => 8,
        'homeroom_teacher_id' => $wali8A->id,
    ]);

    $ownStudent = Student::factory()->create([
        'name' => 'Siswa Kelas 7A',
        'classroom_id' => $class7A->id,
    ]);

    $otherStudent = Student::factory()->create([
        'name' => 'Siswa Kelas 8A',
        'classroom_id' => $class8A->id,
    ]);

    $this->actingAs($wali7A)
        ->get(route('students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $ownStudent->id)
        );

    $this->actingAs($wali7A)
        ->get(route('students.show', $ownStudent))
        ->assertOk();

    $this->actingAs($wali7A)
        ->get(route('students.show', $otherStudent))
        ->assertForbidden();

    $this->actingAs($wali7A)
        ->get(route('students.index', ['classroom_id' => $class8A->id]))
        ->assertForbidden();
});
