<?php

namespace App\Http\Middleware;

use App\Http\Resources\AuthUserResource;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? (new AuthUserResource($user))->resolve($request) : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'plain_device_token' => fn () => $request->session()->get('plain_device_token'),
                'plain_device_name' => fn () => $request->session()->get('plain_device_name'),
                'last_assigned_card_id' => fn () => $request->session()->get('last_assigned_card_id'),
            ],
            'ui' => fn () => trans('ui'),
        ];
    }
}
