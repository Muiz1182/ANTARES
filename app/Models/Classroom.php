<?php

namespace App\Models;

use App\Enums\PermissionEnum;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'academic_year_id',
        'name',
        'level',
        'homeroom_teacher_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'academic_year_id' => 'integer',
            'level' => 'integer',
            'homeroom_teacher_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
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

        return $query->where('homeroom_teacher_id', $user->id);
    }
}
