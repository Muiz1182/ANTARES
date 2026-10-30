<?php

namespace App\Http\Controllers\Api;

use App\Actions\Attendance\RecordScanAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scan\StoreScanRequest;
use App\Http\Resources\ScanResultResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class ScanApiController extends Controller
{
    public function store(StoreScanRequest $request, RecordScanAction $action): JsonResponse
    {
        /** @var Device|null $device */
        $device = $request->attributes->get('authenticated_device');

        $validated = $request->validated();

        $scanResult = $action->execute(
            rawUid: (string) $validated['uid'],
            device: $device,
            scannedAt: isset($validated['scanned_at']) ? (string) $validated['scanned_at'] : null,
            idempotencyKey: isset($validated['idempotency_key']) ? (string) $validated['idempotency_key'] : null,
        );

        return (new ScanResultResource($scanResult))
            ->response($request)
            ->setStatusCode(200);
    }
}

