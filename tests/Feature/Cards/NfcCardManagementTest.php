<?php

use App\Enums\NfcCardStatusEnum;
use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->adminTu = User::factory()->create();
    $this->adminTu->assignRole(RoleEnum::AdminTu->value);

    $year = AcademicYear::factory()->create(['is_active' => true]);
    $this->classroom = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'name' => '7A',
    ]);
});

test('admin_tu can register an NFC card with raw reader input and cannot link one UID to two owners or two active cards to one student', function () {
    $student1 = Student::factory()->create(['classroom_id' => $this->classroom->id]);
    $student2 = Student::factory()->create(['classroom_id' => $this->classroom->id]);

    $this->actingAs($this->adminTu)
        ->post(route('cards.store'), [
            'student_id' => $student1->id,
            'uid' => "0077705923\r\n",
            'notes' => 'Kartu pertama',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $card = NfcCard::first();
    expect($card)->not->toBeNull()
        ->and($card->uid)->toBe('04A1B2C3')
        ->and($card->status)->toBe(NfcCardStatusEnum::Active)
        ->and($card->cardable_id)->toBe($student1->id);

    $this->actingAs($this->adminTu)
        ->post(route('cards.store'), [
            'student_id' => $student2->id,
            'uid' => '04:a1:b2:c3',
        ])
        ->assertSessionHasErrors('uid');

    $this->actingAs($this->adminTu)
        ->post(route('cards.store'), [
            'student_id' => $student1->id,
            'uid' => '3283263748',
        ])
        ->assertSessionHasErrors('student_id');
});

test('replacing a card deactivates the old card, links replaced_by_card_id, and preserves card history', function () {
    $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);

    $oldCard = NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
        'status' => NfcCardStatusEnum::Active,
    ]);

    $this->actingAs($this->adminTu)
        ->post(route('cards.replace'), [
            'student_id' => $student->id,
            'new_uid' => '0004512345',
            'previous_status' => NfcCardStatusEnum::Lost->value,
            'notes' => 'Kartu hilang di lapangan olahraga',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $oldCard->refresh();
    $newCard = NfcCard::where('uid', '0044DA59')->first();

    expect($newCard)->not->toBeNull()
        ->and($newCard->status)->toBe(NfcCardStatusEnum::Active)
        ->and($oldCard->status)->toBe(NfcCardStatusEnum::Lost)
        ->and($oldCard->deactivated_at)->not->toBeNull()
        ->and($oldCard->replaced_by_card_id)->toBe($newCard->id)
        ->and($student->nfcCards()->count())->toBe(2);
});

test('admin_tu can access bulk registration and undo the last assigned card', function () {
    $student = Student::factory()->create(['classroom_id' => $this->classroom->id]);

    $this->actingAs($this->adminTu)
        ->get(route('cards.bulk', ['classroom_id' => $this->classroom->id]))
        ->assertOk();

    $card = NfcCard::factory()->create([
        'uid' => '04A1B2C3',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
    ]);

    $this->actingAs($this->adminTu)
        ->delete(route('cards.undo', $card->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(NfcCard::find($card->id))->toBeNull();
});

test('non-authorized roles cannot access card registration routes', function () {
    $piket = User::factory()->create();
    $piket->assignRole(RoleEnum::Piket->value);

    $wali = User::factory()->create();
    $wali->assignRole(RoleEnum::WaliKelas->value);

    $this->actingAs($piket)->get(route('cards.index'))->assertForbidden();
    $this->actingAs($wali)->get(route('cards.bulk'))->assertForbidden();
});

