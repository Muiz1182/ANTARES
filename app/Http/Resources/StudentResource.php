<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'name' => $this->name,
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'classroom_id' => $this->classroom_id,
            'classroom_name' => $this->whenLoaded('classroom', fn (): ?string => $this->classroom?->name),
            'photo_url' => $this->photoUrl(),
            'parent_name' => $this->parent_name,
            'parent_phone' => $this->parent_phone,
            'is_active' => (bool) $this->is_active,
            'active_card' => $this->whenLoaded('activeNfcCard', fn () => $this->activeNfcCard
                ? (new NfcCardResource($this->activeNfcCard))->resolve($request)
                : null),
        ];
    }
}
