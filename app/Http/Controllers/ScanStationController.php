<?php

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Http\Resources\AttendanceLogResource;
use App\Http\Resources\DeviceResource;
use App\Models\AttendanceLog;
use App\Models\Device;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScanStationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can(PermissionEnum::ScanOperate->value) ?? false, 403);

        $recentLogs = AttendanceLog::query()
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $devices = Device::query()
            ->active()
            ->orderBy('name')
            ->get();

        return Inertia::render('ScanStation/Index', [
            'recentScans' => AttendanceLogResource::collection($recentLogs)->resolve($request),
            'devices' => DeviceResource::collection($devices)->resolve($request),
            'defaultDevToken' => app()->environment(['local', 'testing'])
                ? DatabaseSeeder::DEFAULT_DEV_DEVICE_TOKEN
                : null,
        ]);
    }
}

