<?php

namespace App\Http\Controllers;

use App\Actions\Student\DeleteStudentAction;
use App\Actions\Student\SaveStudentAction;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\StudentResource;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Student::class);

        /** @var User $user */
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $classroomId = $request->query('classroom_id');
        $status = $request->query('status');

        if ($classroomId !== null && $classroomId !== '') {
            $requestedClassroom = Classroom::find((int) $classroomId);
            if ($requestedClassroom) {
                Gate::authorize('view', $requestedClassroom);
            }
        }

        $students = Student::query()
            ->visibleTo($user)
            ->with('classroom')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($classroomId !== null && $classroomId !== '', fn ($q) => $q->where('classroom_id', (int) $classroomId))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Student $student) => (new StudentResource($student))->resolve($request));

        $classrooms = Classroom::query()
            ->visibleTo($user)
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(fn (Classroom $classroom) => (new ClassroomResource($classroom))->resolve($request))
            ->values();

        return Inertia::render('Students/Index', [
            'students' => $students,
            'classrooms' => $classrooms,
            'filters' => [
                'search' => $search,
                'classroom_id' => $classroomId ? (string) $classroomId : '',
                'status' => $status ? (string) $status : '',
            ],
        ]);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load('classroom');

        return Inertia::render('Students/Show', [
            'student' => (new StudentResource($student))->resolve($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Student::class);

        $classrooms = Classroom::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(fn (Classroom $classroom) => (new ClassroomResource($classroom))->resolve($request))
            ->values();

        return Inertia::render('Students/Form', [
            'student' => null,
            'classrooms' => $classrooms,
        ]);
    }

    public function store(StoreStudentRequest $request, SaveStudentAction $action): RedirectResponse
    {
        $action->execute($request->validated(), $request->file('photo'));

        return redirect()->route('students.index')->with('success', __('ui.messages.student_saved'));
    }

    public function edit(Request $request, Student $student): Response
    {
        Gate::authorize('update', $student);

        $classrooms = Classroom::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(fn (Classroom $classroom) => (new ClassroomResource($classroom))->resolve($request))
            ->values();

        return Inertia::render('Students/Form', [
            'student' => (new StudentResource($student->load('classroom')))->resolve($request),
            'classrooms' => $classrooms,
        ]);
    }

    public function update(
        UpdateStudentRequest $request,
        Student $student,
        SaveStudentAction $action,
    ): RedirectResponse {
        $action->execute($request->validated(), $request->file('photo'), $student);

        return redirect()->route('students.index')->with('success', __('ui.messages.student_saved'));
    }

    public function destroy(Student $student, DeleteStudentAction $action): RedirectResponse
    {
        Gate::authorize('delete', $student);

        $action->execute($student);

        return redirect()->route('students.index')->with('success', __('ui.messages.student_deleted'));
    }
}
