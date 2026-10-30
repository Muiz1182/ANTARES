<?php

namespace App\Actions\NfcCard;

use App\Enums\NfcCardStatusEnum;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use App\Support\NfcUidNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplaceNfcCardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $issuer = null): NfcCard
    {
        return DB::transaction(function () use ($data, $issuer): NfcCard {
            $normalizedUid = NfcUidNormalizer::normalize((string) $data['new_uid']);

            $student = Student::query()
                ->lockForUpdate()
                ->findOrFail((int) $data['student_id']);

            if (NfcCard::query()->where('uid', $normalizedUid)->exists()) {
                throw ValidationException::withMessages([
                    'new_uid' => __('ui.messages.card_uid_taken'),
                ]);
            }

            $previousStatus = NfcCardStatusEnum::tryFrom((string) ($data['previous_status'] ?? 'inactive'))
                ?? NfcCardStatusEnum::Inactive;

            $activeCards = $student->nfcCards()
                ->where('status', NfcCardStatusEnum::Active->value)
                ->lockForUpdate()
                ->get();

            $now = now('Asia/Jakarta');

            $newCard = NfcCard::query()->create([
                'uid' => $normalizedUid,
                'cardable_type' => Student::class,
                'cardable_id' => $student->id,
                'status' => NfcCardStatusEnum::Active,
                'issued_at' => $now,
                'issued_by' => $issuer?->id,
                'notes' => isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            ]);

            foreach ($activeCards as $oldCard) {
                $oldCard->update([
                    'status' => $previousStatus,
                    'deactivated_at' => $now,
                    'replaced_by_card_id' => $newCard->id,
                ]);
            }

            return $newCard;
        });
    }
}

