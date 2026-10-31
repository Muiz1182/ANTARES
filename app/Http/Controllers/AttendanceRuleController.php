<?php

namespace App\Http\Controllers;

use App\Actions\AttendanceRule\UpdateAttendanceRulesAction;
use App\Http\Requests\AttendanceRule\UpdateAttendanceRulesRequest;
use App\Http\Resources\AttendanceRuleResource;
use App\Models\AttendanceRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRuleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', AttendanceRule::class);

        $rules = AttendanceRule::query()
            ->orderBy('day_of_week')
            ->get();

        return Inertia::render('AttendanceRules/Index', [
            'rules' => AttendanceRuleResource::collection($rules)->resolve(),
        ]);
    }

    public function update(
        UpdateAttendanceRulesRequest $request,
        UpdateAttendanceRulesAction $action,
    ): RedirectResponse {
        /** @var list<array<string, mixed>> $rules */
        $rules = $request->validated('rules');

        $action->execute($rules);

        return redirect()
            ->route('attendance-rules.index')
            ->with('success', (string) __('ui.messages.attendance_rules_saved'));
    }
}
