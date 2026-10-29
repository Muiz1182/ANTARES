<?php

namespace App\Http\Controllers;

use App\Actions\Holiday\DeleteHolidayAction;
use App\Actions\Holiday\SaveHolidayAction;
use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Holiday::class);

        $search = trim((string) $request->query('search', ''));
        $year = $request->query('year');

        $holidays = Holiday::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($year !== null && $year !== '', fn ($q) => $q->whereYear('date', (int) $year))
            ->orderBy('date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Holiday $holiday) => (new HolidayResource($holiday))->resolve($request));

        return Inertia::render('Holidays/Index', [
            'holidays' => $holidays,
            'filters' => [
                'search' => $search,
                'year' => $year ? (string) $year : '',
            ],
        ]);
    }

    public function store(StoreHolidayRequest $request, SaveHolidayAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return back()->with('success', __('ui.messages.holiday_saved'));
    }

    public function update(
        UpdateHolidayRequest $request,
        Holiday $holiday,
        SaveHolidayAction $action,
    ): RedirectResponse {
        $action->execute($request->validated(), $holiday);

        return back()->with('success', __('ui.messages.holiday_saved'));
    }

    public function destroy(Holiday $holiday, DeleteHolidayAction $action): RedirectResponse
    {
        Gate::authorize('delete', $holiday);

        $action->execute($holiday);

        return back()->with('success', __('ui.messages.holiday_deleted'));
    }
}
