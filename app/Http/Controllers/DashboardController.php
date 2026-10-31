<?php

namespace App\Http\Controllers;

use App\Http\Resources\AcademicYearResource;
use App\Http\Resources\ClassroomResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardMetricsService $dashboardMetricsService,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $activeYear = AcademicYear::query()->active()->first();
        $dateInput = $request->string('date')->trim()->toString();
        $classroomId = $request->integer('classroom_id');

        $classrooms = Classroom::query()
            ->visibleTo($user)
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $dashboard = $dashboardMetricsService->getDashboardData(
            user: $user,
            dateString: $dateInput !== '' ? $dateInput : null,
            filterClassroomId: $classroomId > 0 ? $classroomId : null,
            includeTrend: true,
        );

        return Inertia::render('Dashboard', [
            'activeAcademicYear' => $activeYear ? (new AcademicYearResource($activeYear))->resolve($request) : null,
            'classrooms' => ClassroomResource::collection($classrooms)->resolve($request),
            'dashboard' => $dashboard,
            'filters' => [
                'date' => $dashboard['date'],
                'classroom_id' => $classroomId > 0 ? $classroomId : null,
            ],
        ]);
    }

    public function liveState(
        Request $request,
        DashboardMetricsService $dashboardMetricsService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $dateInput = $request->string('date')->trim()->toString();
        $classroomId = $request->integer('classroom_id');

        $dashboard = $dashboardMetricsService->getDashboardData(
            user: $user,
            dateString: $dateInput !== '' ? $dateInput : null,
            filterClassroomId: $classroomId > 0 ? $classroomId : null,
            includeTrend: false,
        );

        return response()->json([
            'date' => $dashboard['date'],
            'generated_at' => $dashboard['generated_at'],
            'counts' => $dashboard['counts'],
            'class_breakdown' => $dashboard['class_breakdown'],
            'not_yet_arrived_students' => $dashboard['not_yet_arrived_students'],
            'recent_taps' => $dashboard['recent_taps'],
        ]);
    }
}
