<?php

namespace App\Http\Resources;

use App\Models\AttendanceRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceRule
 */
class AttendanceRuleResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => (int) $this->day_of_week,
            'day_label' => (string) __('ui.weekdays.'.$this->day_of_week),
            'is_school_day' => (bool) $this->is_school_day,
            'check_in_start' => substr((string) $this->check_in_start, 0, 5),
            'late_after' => substr((string) $this->late_after, 0, 5),
            'check_out_start' => substr((string) $this->check_out_start, 0, 5),
            'absent_cutoff' => substr((string) $this->absent_cutoff, 0, 5),
            'debounce_seconds' => (int) $this->debounce_seconds,
        ];
    }
}
