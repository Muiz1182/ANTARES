<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\CorrectAttendanceAction;
use App\Actions\Attendance\StoreExcusedAttendanceAction;
use App\Enums\AttendanceStatusEnum;
use App\Http\Requests\Attendance\CorrectAttendanceRequest;
use App\Http\Requests\Attendance\StoreExcusedAttendanceRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\ClassTodayStudentAttendanceResource;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClassAttendanceController extends Controller
{
    public function today(Request $request): Response
    {
        Gate::authorize('viewAny', Attendance::class);

        /** @var User $user */
        $user = $request->user();

        $dateInput = $request->string('date')->trim()->toString();
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput) === 1
            ? $dateInput
            : CarbonImmutable::now('Asia/Jakarta')->toDateString();

        $classrooms = Classroom::query()
            ->visibleTo($user)
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $requestedClassroomId = $request->integer('classroom_id');
        $selectedClassroom = $requestedClassroomId > 0
            ? $classrooms->firstWhere('id', $requestedClassroomId)
            : $classrooms->first();

        $search = $request->string('search')->trim()->toString();
        $statusFilter = $request->string('status')->trim()->toString();

        $students = collect();
        $summary = [
            'total_students' => 0,
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'absent' => 0,
            'unrecorded' => 0,
        ];

        if ($selectedClassroom !== null) {
            $allClassStudents = Student::query()
                ->where('classroom_id', $selectedClassroom->id)
                ->where('is_active', true)
                ->with([
                    'classroom',
                    'attendances' => static function ($query) use ($date): void {
                        $query->where('date', $date);
                    },
                ])
                ->orderBy('name')
                ->get();

            foreach ($allClassStudents as $student) {
                $summary['total_students']++;
                /** @var Attendance|null $att */
                $att = $student->attendances->first();
                if ($att === null) {
                    $summary['unrecorded']++;

                    continue;
                }

                $statusValue = $att->status instanceof AttendanceStatusEnum
                    ? $att->status->value
                    : (string) $att->status;

                match ($statusValue) {
                    AttendanceStatusEnum::Present->value => $summary['present']++,
                    AttendanceStatusEnum::Late->value => $summary['late']++,
                    AttendanceStatusEnum::Permission->value,
                    AttendanceStatusEnum::Sick->value,
                    AttendanceStatusEnum::Dispensation->value => $summary['excused']++,
                    AttendanceStatusEnum::Absent->value => $summary['absent']++,
                    default => $summary['unrecorded']++,
                };
            }

            $students = $allClassStudents->filter(static function (Student $student) use ($search, $statusFilter): bool {
                if ($search !== '') {
                    $needle = mb_strtolower($search);
                    $matchesName = str_contains(mb_strtolower($student->name), $needle);
                    $matchesNis = str_contains(mb_strtolower($student->nis), $needle);
                    if (! $matchesName && ! $matchesNis) {
                        return false;
                    }
                }

                if ($statusFilter !== '') {
                    /** @var Attendance|null $att */
                    $att = $student->attendances->first();
                    if ($statusFilter === 'unrecorded') {
                        return $att === null;
                    }
                    if ($att === null) {
                        return false;
                    }
                    $statusValue = $att->status instanceof AttendanceStatusEnum
                        ? $att->status->value
                        : (string) $att->status;

                    return $statusValue === $statusFilter;
                }

                return true;
            })->values();
        }

        return Inertia::render('Attendances/Today', [
            'classrooms' => ClassroomResource::collection($classrooms)->resolve(),
            'selectedClassroomId' => $selectedClassroom?->id,
            'students' => ClassTodayStudentAttendanceResource::collection($students)->resolve(),
            'summary' => $summary,
            'filters' => [
                'classroom_id' => $selectedClassroom?->id,
                'date' => $date,
                'search' => $search,
                'status' => $statusFilter,
            ],
            'statusOptions' => collect(AttendanceStatusEnum::cases())->map(fn (AttendanceStatusEnum $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ])->values()->all(),
            'excuseStatusOptions' => [
                ['value' => AttendanceStatusEnum::Permission->value, 'label' => AttendanceStatusEnum::Permission->label()],
                ['value' => AttendanceStatusEnum::Sick->value, 'label' => AttendanceStatusEnum::Sick->label()],
                ['value' => AttendanceStatusEnum::Dispensation->value, 'label' => AttendanceStatusEnum::Dispensation->label()],
            ],
        ]);
    }

    public function storeExcuse(
        StoreExcusedAttendanceRequest $request,
        StoreExcusedAttendanceAction $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $action->execute(
            data: $request->validated(),
            actor: $user,
            attachment: $request->file('attachment'),
        );

        return redirect()
            ->back()
            ->with('success', (string) __('ui.flash.attendance_excused'));
    }

    public function correct(
        CorrectAttendanceRequest $request,
        CorrectAttendanceAction $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $action->execute(
            data: $request->validated(),
            actor: $user,
        );

        return redirect()
            ->back()
            ->with('success', (string) __('ui.flash.attendance_corrected'));
    }
}
