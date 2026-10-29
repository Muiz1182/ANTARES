<?php

namespace App\Http\Controllers;

use App\Actions\AcademicYear\DeleteAcademicYearAction;
use App\Actions\AcademicYear\SaveAcademicYearAction;
use App\Http\Requests\AcademicYear\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYear\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AcademicYear::class);

        $search = trim((string) $request->query('search', ''));

        $academicYears = AcademicYear::query()
            ->withCount('classrooms')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (AcademicYear $year) => (new AcademicYearResource($year))->resolve($request));

        return Inertia::render('AcademicYears/Index', [
            'academicYears' => $academicYears,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(StoreAcademicYearRequest $request, SaveAcademicYearAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return back()->with('success', __('ui.messages.academic_year_saved'));
    }

    public function update(
        UpdateAcademicYearRequest $request,
        AcademicYear $academicYear,
        SaveAcademicYearAction $action,
    ): RedirectResponse {
        $action->execute($request->validated(), $academicYear);

        return back()->with('success', __('ui.messages.academic_year_saved'));
    }

    public function destroy(AcademicYear $academicYear, DeleteAcademicYearAction $action): RedirectResponse
    {
        Gate::authorize('delete', $academicYear);

        try {
            $action->execute($academicYear);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('ui.messages.academic_year_deleted'));
    }
}
