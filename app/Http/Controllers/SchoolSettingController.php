<?php

namespace App\Http\Controllers;

use App\Actions\Setting\UpdateSchoolSettingAction;
use App\Http\Requests\Setting\UpdateSchoolSettingRequest;
use App\Http\Resources\SchoolSettingResource;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchoolSettingController extends Controller
{
    public function edit(Request $request): Response
    {
        Gate::authorize('viewAny', SchoolSetting::class);

        $setting = SchoolSetting::current();

        return Inertia::render('Settings/Index', [
            'setting' => (new SchoolSettingResource($setting))->resolve($request),
        ]);
    }

    public function update(
        UpdateSchoolSettingRequest $request,
        UpdateSchoolSettingAction $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $action->execute(
            data: $request->validated(),
            actor: $user,
            logo: $request->file('logo'),
        );

        return redirect()
            ->route('settings.edit')
            ->with('success', (string) __('ui.messages.school_settings_saved'));
    }
}
