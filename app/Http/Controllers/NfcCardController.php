<?php

namespace App\Http\Controllers;

use App\Actions\NfcCard\RegisterNfcCardAction;
use App\Actions\NfcCard\ReplaceNfcCardAction;
use App\Actions\NfcCard\UndoBulkCardAssignmentAction;
use App\Actions\NfcCard\UpdateNfcCardStatusAction;
use App\Enums\NfcCardStatusEnum;
use App\Http\Requests\NfcCard\ReplaceNfcCardRequest;
use App\Http\Requests\NfcCard\StoreNfcCardRequest;
use App\Http\Requests\NfcCard\UpdateNfcCardStatusRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\NfcCardResource;
use App\Http\Resources\StudentResource;
use App\Models\Classroom;
use App\Models\NfcCard;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NfcCardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', NfcCard::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $classroomId = $request->filled('classroom_id') ? (int) $request->query('classroom_id') : null;

        $cards = NfcCard::query()
            ->with([
                'cardable' => static function ($morphTo): void {
                    $morphTo->morphWith([
                        Student::class => ['classroom'],
                    ]);
                },
                'replacedByCard',
            ])
            ->when($status !== '' && in_array($status, NfcCardStatusEnum::values(), true), static function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($classroomId !== null, static function (Builder $query) use ($classroomId): void {
                $query->whereHasMorph('cardable', [Student::class], static function (Builder $studentQuery) use ($classroomId): void {
                    $studentQuery->where('classroom_id', $classroomId);
                });
            })
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $subQuery) use ($search): void {
                    $subQuery->where('uid', 'like', '%'.strtoupper($search).'%')
                        ->orWhereHasMorph('cardable', [Student::class], static function (Builder $studentQuery) use ($search): void {
                            $studentQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('nis', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (NfcCard $card) => (new NfcCardResource($card))->resolve($request));

        $students = Student::query()
            ->with(['classroom', 'activeNfcCard'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $classrooms = Classroom::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return Inertia::render('Cards/Index', [
            'cards' => $cards,
            'students' => StudentResource::collection($students)->resolve($request),
            'classrooms' => ClassroomResource::collection($classrooms)->resolve($request),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'classroom_id' => $classroomId,
            ],
        ]);
    }

    public function bulk(Request $request): Response
    {
        Gate::authorize('create', NfcCard::class);

        $classrooms = Classroom::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $selectedClassroomId = $request->filled('classroom_id')
            ? (int) $request->query('classroom_id')
            : $classrooms->first()?->id;

        $students = $selectedClassroomId !== null
            ? Student::query()
                ->with(['classroom', 'activeNfcCard'])
                ->where('classroom_id', $selectedClassroomId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Cards/Bulk', [
            'classrooms' => ClassroomResource::collection($classrooms)->resolve($request),
            'selectedClassroomId' => $selectedClassroomId,
            'students' => StudentResource::collection($students)->resolve($request),
        ]);
    }

    public function store(StoreNfcCardRequest $request, RegisterNfcCardAction $action): RedirectResponse
    {
        $card = $action->execute($request->validated(), $request->user());
        /** @var Student|null $student */
        $student = $card->cardable;

        return back()
            ->with('success', __('ui.messages.card_registered', [
                'uid' => $card->uid,
                'student' => $student?->name ?? '-',
            ]))
            ->with('last_assigned_card_id', $card->id);
    }

    public function replace(ReplaceNfcCardRequest $request, ReplaceNfcCardAction $action): RedirectResponse
    {
        $card = $action->execute($request->validated(), $request->user());
        /** @var Student|null $student */
        $student = $card->cardable;

        return back()
            ->with('success', __('ui.messages.card_replaced', [
                'uid' => $card->uid,
                'student' => $student?->name ?? '-',
            ]));
    }

    public function updateStatus(
        UpdateNfcCardStatusRequest $request,
        NfcCard $nfcCard,
        UpdateNfcCardStatusAction $action,
    ): RedirectResponse {
        $updated = $action->execute($nfcCard, $request->validated());

        return back()
            ->with('success', __('ui.messages.card_status_updated', [
                'uid' => $updated->uid,
            ]));
    }

    public function undoBulk(NfcCard $nfcCard, UndoBulkCardAssignmentAction $action): RedirectResponse
    {
        Gate::authorize('delete', $nfcCard);

        $uid = $action->execute($nfcCard);

        return back()
            ->with('success', __('ui.messages.card_undo_completed', [
                'uid' => $uid,
            ]));
    }
}
