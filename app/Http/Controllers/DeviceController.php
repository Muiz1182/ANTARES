<?php

namespace App\Http\Controllers;

use App\Actions\Device\CreateDeviceAction;
use App\Actions\Device\RegenerateDeviceTokenAction;
use App\Actions\Device\UpdateDeviceAction;
use App\Http\Requests\Device\StoreDeviceRequest;
use App\Http\Requests\Device\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Device::class);

        $devices = Device::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Devices/Index', [
            'devices' => DeviceResource::collection($devices)->resolve($request),
        ]);
    }

    public function store(StoreDeviceRequest $request, CreateDeviceAction $action): RedirectResponse
    {
        $result = $action->execute($request->validated());

        return redirect()
            ->route('devices.index')
            ->with('success', __('ui.messages.device_created'))
            ->with('plain_device_token', $result['plain_token'])
            ->with('plain_device_name', $result['device']->name);
    }

    public function update(UpdateDeviceRequest $request, Device $device, UpdateDeviceAction $action): RedirectResponse
    {
        $action->execute($device, $request->validated());

        return redirect()
            ->route('devices.index')
            ->with('success', __('ui.messages.device_updated'));
    }

    public function regenerateToken(Device $device, RegenerateDeviceTokenAction $action): RedirectResponse
    {
        Gate::authorize('update', $device);

        $result = $action->execute($device);

        return redirect()
            ->route('devices.index')
            ->with('success', __('ui.messages.device_token_regenerated'))
            ->with('plain_device_token', $result['plain_token'])
            ->with('plain_device_name', $result['device']->name);
    }
}

