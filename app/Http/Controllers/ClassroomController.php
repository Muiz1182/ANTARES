<?php

namespace App\Http\Controllers;

use App\Actions\Classroom\DeleteClassroomAction;
use App\Actions\Classroom\SaveClassroomAction;
use App\Enums\RoleEnum;
use App\Http\Requests\Classroom\StoreClassroomRequest;
use App\Http\Requests\Classroom\UpdateClassroomRequest;
use App\Http\Resources\AcademicYearResource;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\UserResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Classroom::class);

        /** @var User $user */
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $level = $request->query('level');
        $academicYearId = $request->query('academic_year_id');

        $classrooms = Classroom::query()
            ->visibleTo($user)
            ->with(['academicYear', 'homeroomTeacher'])
            ->withCount('students')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($level !== null && $level !== '', fn ($q) => $q->where('level', (int) $level))
            ->when($academicYearId !== null && $academicYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $academicYearId))
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Classroom $classroom) => (new ClassroomResource($classroom))->resolve($request));

        $academicYears = AcademicYear::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (AcademicYear $year) => (new AcademicYearResource($year))->resolve($request))
            ->values();

        $teachers = User::query()
            ->active()
            ->role([RoleEnum::WaliKelas->value, RoleEnum::AdminTu->value])
            ->orderBy('name')
            ->get()
            ->map(fn (User $teacher) => (new UserResource($teacher))->resolve($request))
            ->values();

        return Inertia::render('Classrooms/Index', [
            'classrooms' => $classrooms,
            'academicYears' => $academicYears,
            'teachers' => $teachers,
            'filters' => [
                'search' => $search,
                'level' => $level ? (string) $level : '',
                'academic_year_id' => $academicYearId ? (string) $academicYearId : '',
            ],
        ]);
    }

    public function store(StoreClassroomRequest $request, SaveClassroomAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return back()->with('success', __('ui.messages.classroom_saved'));
    }

    public function update(
        UpdateClassroomRequest $request,
        Classroom $classroom,
        SaveClassroomAction $action,
    ): RedirectResponse {
        $action->execute($request->validated(), $classroom);

        return back()->with('success', __('ui.messages.classroom_saved'));
    }

    public function destroy(Classroom $classroom, DeleteClassroomAction $action): RedirectResponse
    {
        Gate::authorize('delete', $classroom);

        $action->execute($classroom);

        return back()->with('success', __('ui.messages.classroom_deleted'));
    }
}
