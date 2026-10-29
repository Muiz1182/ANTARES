<?php

namespace App\Actions\Holiday;

use App\Models\Holiday;

class DeleteHolidayAction
{
    public function execute(Holiday $holiday): void
    {
        $holiday->delete();
    }
}
