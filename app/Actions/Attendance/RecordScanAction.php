<?php

namespace App\Actions\Attendance;

use App\Models\Device;
use App\Services\Attendance\ScanService;
use Carbon\CarbonInterface;

class RecordScanAction
{
    public function __construct(
        private readonly ScanService $scanService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        string $rawUid,
        ?Device $device = null,
        CarbonInterface|string|null $scannedAt = null,
        ?string $idempotencyKey = null,
    ): array {
        return $this->scanService->processTap(
            rawUid: $rawUid,
            device: $device,
            scannedAt: $scannedAt,
            idempotencyKey: $idempotencyKey,
        );
    }
}

