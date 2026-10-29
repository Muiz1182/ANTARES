<?php

namespace App\Http\Controllers;

use App\Http\Requests\Student\CommitStudentImportRequest;
use App\Http\Requests\Student\PreviewStudentImportRequest;
use App\Models\Student;
use App\Services\Student\StudentImportService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    public function show(): Response
    {
        Gate::authorize('import', Student::class);

        return Inertia::render('Students/Import', [
            'preview' => null,
        ]);
    }

    public function template(Request $request, StudentImportService $service): BinaryFileResponse
    {
        Gate::authorize('import', Student::class);

        $format = strtolower((string) $request->query('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $path = $service->generateTemplateFile($format);

        return response()
            ->download($path, "template_impor_siswa.{$format}")
            ->deleteFileAfterSend(true);
    }

    public function preview(PreviewStudentImportRequest $request, StudentImportService $service): Response
    {
        $preview = $service->preview(
            $request->file('file'),
            (int) $request->user()->id
        );

        return Inertia::render('Students/Import', [
            'preview' => $preview,
        ]);
    }

    public function commit(CommitStudentImportRequest $request, StudentImportService $service): RedirectResponse
    {
        try {
            $importedCount = $service->commit(
                (string) $request->validated('import_token'),
                (int) $request->user()->id
            );
        } catch (DomainException $e) {
            return redirect()->route('students.import.show')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('students.index')
            ->with('success', __('ui.messages.student_imported', ['count' => $importedCount]));
    }
}
