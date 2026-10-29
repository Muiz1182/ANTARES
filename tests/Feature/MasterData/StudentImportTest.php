<?php

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->adminTu = User::factory()->create();
    $this->adminTu->assignRole(RoleEnum::AdminTu->value);

    $this->year = AcademicYear::factory()->active()->create(['name' => '2026/2027']);
    $this->class7A = Classroom::factory()->create([
        'academic_year_id' => $this->year->id,
        'name' => '7A',
        'level' => 7,
    ]);
});

test('admin_tu can download import templates in xlsx and csv formats', function () {
    $this->actingAs($this->adminTu)
        ->get(route('students.import.template', ['format' => 'xlsx']))
        ->assertOk();

    $this->actingAs($this->adminTu)
        ->get(route('students.import.template', ['format' => 'csv']))
        ->assertOk();
});

test('importing a 700-row file completes without timeout, reports per-row errors, and creates no partial duplicates', function () {
    Student::factory()->create([
        'nis' => '269999',
        'nisn' => '0099999999',
        'classroom_id' => $this->class7A->id,
    ]);

    $lines = ['nis,nisn,name,gender,classroom,parent_name,parent_phone'];

    for ($i = 1; $i <= 700; $i++) {
        $nis = sprintf('26%04d', $i);
        $nisn = sprintf('0026%06d', $i);
        $gender = $i % 2 === 0 ? 'P' : 'L';
        $lines[] = "{$nis},{$nisn},Siswa Ke {$i},{$gender},7A,Wali {$i},08120000{$i}";
    }

    $lines[] = '260001,0088888881,Duplikat Dalam Berkas,L,7A,Wali Dup,08121111';
    $lines[] = '269999,0088888882,Duplikat Database,P,7A,Wali DB,08122222';
    $lines[] = '267500,0088888883,Kelas Tidak Valid,L,9Z,Wali Kelas Salah,08123333';

    $csvContent = implode("\n", $lines);
    $file = UploadedFile::fake()->createWithContent('siswa_700.csv', $csvContent);

    $previewToken = null;

    $this->actingAs($this->adminTu)
        ->post(route('students.import.preview'), ['file' => $file])
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$previewToken) {
            $page->where('preview.total_rows', 703)
                ->where('preview.valid_count', 700)
                ->where('preview.error_count', 3)
                ->has('preview.errors', 3);

            $previewToken = $page->toArray()['props']['preview']['import_token'];
        });

    expect($previewToken)->toBeString()->not->toBeEmpty()
        ->and(Student::count())->toBe(1);

    $this->actingAs($this->adminTu)
        ->post(route('students.import.commit'), [
            'import_token' => $previewToken,
        ])
        ->assertRedirect(route('students.index'))
        ->assertSessionHas('success');

    expect(Student::count())->toBe(701)
        ->and(Student::where('nis', '260001')->count())->toBe(1)
        ->and(Student::where('nis', '260700')->count())->toBe(1)
        ->and(Student::where('nis', '267500')->exists())->toBeFalse();
});
