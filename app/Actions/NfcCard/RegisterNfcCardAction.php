<?php

namespace App\Actions\NfcCard;

use App\Enums\NfcCardStatusEnum;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use App\Support\NfcUidNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterNfcCardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $issuer = null): NfcCard
    {
        return DB::transaction(function () use ($data, $issuer): NfcCard {
            $normalizedUid = NfcUidNormalizer::normalize((string) $data['uid']);

            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail((int) $data['student_id']);

            if ($student->activeNfcCard()->exists()) {
                throw ValidationException::withMessages([
                    'student_id' => __('ui.messages.student_already_has_active_card'),
                ]);
            }

            if (NfcCard::query()->where('uid', $normalizedUid)->exists()) {
                throw ValidationException::withMessages([
                    'uid' => __('ui.messages.card_uid_taken'),
                ]);
            }

            return NfcCard::query()->create([
                'uid' => $normalizedUid,
                'cardable_type' => Student::class,
                'cardable_id' => $student->id,
                'status' => NfcCardStatusEnum::Active,
                'issued_at' => now('Asia/Jakarta'),
                'issued_by' => $issuer?->id,
                'notes' => isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            ]);
        });
    }
}

