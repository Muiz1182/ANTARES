<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AttendanceRuleController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClassAttendanceController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\NfcCardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanStationController;
use App\Http\Controllers\SchoolSettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/live-state', [DashboardController::class, 'liveState'])->name('dashboard.live-state');

    Route::get('/scan-station', [ScanStationController::class, 'index'])->name('scan-station.index');

    Route::get('/cards', [NfcCardController::class, 'index'])->name('cards.index');
    Route::get('/cards/bulk', [NfcCardController::class, 'bulk'])->name('cards.bulk');
    Route::post('/cards', [NfcCardController::class, 'store'])->name('cards.store');
    Route::post('/cards/replace', [NfcCardController::class, 'replace'])->name('cards.replace');
    Route::patch('/cards/{nfc_card}/status', [NfcCardController::class, 'updateStatus'])->name('cards.update-status');
    Route::delete('/cards/{nfc_card}/undo', [NfcCardController::class, 'undoBulk'])->name('cards.undo');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::post('/devices/{device}/regenerate-token', [DeviceController::class, 'regenerateToken'])->name('devices.regenerate-token');

    Route::get('/attendance-rules', [AttendanceRuleController::class, 'index'])->name('attendance-rules.index');
    Route::put('/attendance-rules', [AttendanceRuleController::class, 'update'])->name('attendance-rules.update');

    Route::get('/attendances/today', [ClassAttendanceController::class, 'today'])->name('attendances.today');
    Route::post('/attendances/excuse', [ClassAttendanceController::class, 'storeExcuse'])->name('attendances.excuse');
    Route::post('/attendances/correct', [ClassAttendanceController::class, 'correct'])->name('attendances.correct');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');

    Route::get('/settings', [SchoolSettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SchoolSettingController::class, 'update'])->name('settings.update');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::resource('academic-years', AcademicYearController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('classrooms', ClassroomController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('students/import', [StudentImportController::class, 'show'])->name('students.import.show');
    Route::get('students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::post('students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
    Route::post('students/import/commit', [StudentImportController::class, 'commit'])->name('students.import.commit');

    Route::resource('students', StudentController::class);

    Route::resource('holidays', HolidayController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('users', UserController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
