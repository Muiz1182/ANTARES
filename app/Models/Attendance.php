<?php

namespace App\Models;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'attendable_type',
        'attendable_id',
        'date',
        'check_in_at',
        'check_out_at',
        'status',
        'source',
        'device_id',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendable_id' => 'integer',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => AttendanceStatusEnum::class,
            'source' => AttendanceSourceEnum::class,
            'device_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

