<?php

namespace App\Actions\Holiday;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

class SaveHolidayAction
{
    /**
     * @param  array{date: string, name: string, description?: string|null}  $data
     */
    public function execute(array $data, ?Holiday $holiday = null): Holiday
    {
        $payload = [
            'date' => Carbon::parse($data['date'])->toDateString(),
            'name' => trim($data['name']),
            'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null,
        ];

        if ($holiday) {
            $holiday->update($payload);

            return $holiday->refresh();
        }

        return Holiday::create($payload);
    }
}
