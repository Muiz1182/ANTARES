<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\AttendanceReportFilterRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\SchoolSettingResource;
use App\Models\Classroom;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\Report\AttendanceExcelExportService;
use App\Services\Report\AttendancePdfExportService;
use App\Services\Report\AttendanceReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(
        AttendanceReportFilterRequest $request,
        AttendanceReportService $reportService,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $classrooms = Classroom::query()
            ->visibleTo($user)
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $requestedClassroomId = $request->integer('classroom_id');
        $selectedClassroom = $requestedClassroomId > 0
            ? $classrooms->firstWhere('id', $requestedClassroomId)
            : $classrooms->first();

        $monthInput = $request->string('month')->trim()->toString();
        $month = preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1
            ? $monthInput
            : CarbonImmutable::now('Asia/Jakarta')->format('Y-m');

        $startOfMonth = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', 'Asia/Jakarta')->startOfDay();
        $endOfMonth = $startOfMonth->endOfMonth()->startOfDay();

        $dateFromInput = $request->string('date_from')->trim()->toString();
        $dateToInput = $request->string('date_to')->trim()->toString();

        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromInput) === 1
            ? $dateFromInput
            : $startOfMonth->toDateString();

        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToInput) === 1
            ? $dateToInput
            : $endOfMonth->toDateString();

        $matrix = $selectedClassroom !== null
            ? $reportService->getMonthlyClassMatrix($selectedClassroom, $month)
            : null;

        $rankings = $reportService->getTopLateAndAbsent(
            user: $user,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            classroomId: $selectedClassroom?->id,
        );

        $classStudents = $selectedClassroom !== null
            ? Student::query()
                ->where('classroom_id', $selectedClassroom->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'nis', 'name'])
            : collect();

        $requestedStudentId = $request->integer('student_id');
        /** @var Student|null $selectedStudent */
        $selectedStudent = $requestedStudentId > 0
            ? $classStudents->firstWhere('id', $requestedStudentId)
            : $classStudents->first();

        $studentHistory = $selectedStudent !== null
            ? $reportService->getStudentHistory($selectedStudent, $dateFrom, $dateTo)
            : [];

        $setting = SchoolSetting::current();

        return Inertia::render('Reports/Index', [
            'classrooms' => ClassroomResource::collection($classrooms)->resolve($request),
            'selectedClassroomId' => $selectedClassroom?->id,
            'matrix' => $matrix,
            'topLate' => $rankings['top_late'],
            'topAbsent' => $rankings['top_absent'],
            'classStudents' => $classStudents->map(fn (Student $s): array => [
                'id' => $s->id,
                'nis' => $s->nis,
                'name' => $s->name,
            ])->values()->all(),
            'selectedStudentId' => $selectedStudent?->id,
            'studentHistory' => $studentHistory,
            'schoolSetting' => (new SchoolSettingResource($setting))->resolve($request),
            'filters' => [
                'classroom_id' => $selectedClassroom?->id,
                'month' => $month,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'student_id' => $selectedStudent?->id,
            ],
        ]);
    }

    public function exportExcel(
        AttendanceReportFilterRequest $request,
        AttendanceExcelExportService $excelExportService,
    ): BinaryFileResponse {
        /** @var User $user */
        $user = $request->user();

        $classroom = $this->resolveTargetClassroom($user, $request->integer('classroom_id'));
        $monthInput = $request->string('month')->trim()->toString();
        $month = preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1
            ? $monthInput
            : CarbonImmutable::now('Asia/Jakarta')->format('Y-m');

        $path = $excelExportService->generateMonthlyClassXlsx($classroom, $month);
        $fileName = 'rekap_absensi_kelas_'.Str::slug($classroom->name).'_'.$month.'.xlsx';

        return response()
            ->download($path, $fileName)
            ->deleteFileAfterSend(true);
    }

    public function exportPdf(
        AttendanceReportFilterRequest $request,
        AttendancePdfExportService $pdfExportService,
    ): HttpResponse {
        /** @var User $user */
        $user = $request->user();

        $classroom = $this->resolveTargetClassroom($user, $request->integer('classroom_id'));
        $monthInput = $request->string('month')->trim()->toString();
        $month = preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1
            ? $monthInput
            : CarbonImmutable::now('Asia/Jakarta')->format('Y-m');

        $pdfBinary = $pdfExportService->generateMonthlyClassPdfContent($classroom, $month);
        $fileName = 'rekap_absensi_kelas_'.Str::slug($classroom->name).'_'.$month.'.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    private function resolveTargetClassroom(User $user, int $classroomId): Classroom
    {
        $classrooms = Classroom::query()
            ->visibleTo($user)
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $selected = $classroomId > 0
            ? $classrooms->firstWhere('id', $classroomId)
            : $classrooms->first();

        abort_if($selected === null, 403);

        return $selected;
    }
}
