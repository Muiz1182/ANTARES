<?php

namespace App\Http\Resources;

use App\Models\NfcCard;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NfcCard
 */
class NfcCardResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->whenLoaded('cardable');
        $studentData = null;

        if ($owner instanceof Student) {
            $studentData = [
                'id' => $owner->id,
                'nis' => $owner->nis,
                'name' => $owner->name,
                'classroom_name' => $owner->relationLoaded('classroom') ? $owner->classroom?->name : null,
                'is_active' => (bool) $owner->is_active,
            ];
        }

        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'issued_at' => $this->issued_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'issued_at_formatted' => $this->issued_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'deactivated_at' => $this->deactivated_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'deactivated_at_formatted' => $this->deactivated_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'replaced_by_card_id' => $this->replaced_by_card_id,
            'replaced_by_uid' => $this->whenLoaded('replacedByCard', fn (): ?string => $this->replacedByCard?->uid),
            'notes' => $this->notes,
            'student' => $studentData,
        ];
    }
}
