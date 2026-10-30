<?php

namespace App\Models;

use App\Enums\NfcCardStatusEnum;
use Database\Factories\NfcCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NfcCard extends Model
{
    /** @use HasFactory<NfcCardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'cardable_type',
        'cardable_id',
        'status',
        'issued_at',
        'deactivated_at',
        'replaced_by_card_id',
        'issued_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cardable_id' => 'integer',
            'status' => NfcCardStatusEnum::class,
            'issued_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'replaced_by_card_id' => 'integer',
            'issued_by' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function cardable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replacedByCard(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_card_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return HasMany<AttendanceLog, $this>
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', NfcCardStatusEnum::Active->value);
    }
}

