<?php

namespace App\Models;

use App\Enums\GenderEnum;
use App\Enums\NfcCardStatusEnum;
use App\Enums\PermissionEnum;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nis',
        'nisn',
        'name',
        'gender',
        'classroom_id',
        'photo_path',
        'parent_name',
        'parent_phone',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classroom_id' => 'integer',
            'gender' => GenderEnum::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return MorphMany<NfcCard, $this>
     */
    public function nfcCards(): MorphMany
    {
        return $this->morphMany(NfcCard::class, 'cardable')->orderByDesc('issued_at');
    }

    /**
     * @return MorphOne<NfcCard, $this>
     */
    public function activeNfcCard(): MorphOne
    {
        return $this->morphOne(NfcCard::class, 'cardable')
            ->where('status', NfcCardStatusEnum::Active->value);
    }

    /**
     * @return MorphMany<Attendance, $this>
     */
    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendance::class, 'attendable');
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (
            $user->can(PermissionEnum::MasterManage->value)
            || $user->can(PermissionEnum::ReportsView->value)
            || $user->can(PermissionEnum::AttendanceViewAll->value)
        ) {
            return $query;
        }

        return $query->whereHas('classroom', static function (Builder $classroomQuery) use ($user): void {
            $classroomQuery->where('homeroom_teacher_id', $user->id);
        });
    }
}
