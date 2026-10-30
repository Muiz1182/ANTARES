<?php

namespace App\Actions\NfcCard;

use App\Models\NfcCard;
use Illuminate\Support\Facades\DB;

class UndoBulkCardAssignmentAction
{
    public function execute(NfcCard $nfcCard): string
    {
        return DB::transaction(function () use ($nfcCard): string {
            $uid = $nfcCard->uid;
            $nfcCard->delete();

            return $uid;
        });
    }
}

