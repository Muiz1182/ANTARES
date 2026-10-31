<?php

namespace App\Actions\NfcCard;

use App\Enums\NfcCardStatusEnum;
use App\Models\NfcCard;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateNfcCardStatusAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(NfcCard $nfcCard, array $data): NfcCard
    {
        return DB::transaction(function () use ($nfcCard, $data): NfcCard {
            $newStatus = NfcCardStatusEnum::from((string) $data['status']);

            if ($newStatus === NfcCardStatusEnum::Active && $nfcCard->status !== NfcCardStatusEnum::Active) {
                $otherActiveExists = NfcCard::query()
                    ->where('cardable_type', $nfcCard->cardable_type)
                    ->where('cardable_id', $nfcCard->cardable_id)
                    ->where('status', NfcCardStatusEnum::Active->value)
                    ->where('id', '!=', $nfcCard->id)
                    ->exists();

                if ($otherActiveExists && $nfcCard->cardable_type === Student::class) {
                    throw ValidationException::withMessages([
                        'status' => __('ui.messages.student_already_has_active_card'),
                    ]);
                }
            }

            $deactivatedAt = $newStatus === NfcCardStatusEnum::Active
                ? null
                : ($nfcCard->deactivated_at ?? now('Asia/Jakarta'));

            $nfcCard->update([
                'status' => $newStatus,
                'deactivated_at' => $deactivatedAt,
                'notes' => array_key_exists('notes', $data) && $data['notes'] !== ''
                    ? (string) $data['notes']
                    : $nfcCard->notes,
            ]);

            return $nfcCard->fresh();
        });
    }
}
