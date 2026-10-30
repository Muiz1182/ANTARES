<?php

namespace App\Models;

use App\Enums\ScanResultEnum;
use Database\Factories\AttendanceLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceLog extends Model
{
    /** @use HasFactory<AttendanceLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nfc_card_id',
        'attendable_type',
        'attendable_id',
        'uid_raw',
        'uid_normalized',
        'device_id',
        'scanned_at',
        'result',
        'idempotency_key',
        'response_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nfc_card_id' => 'integer',
            'attendable_id' => 'integer',
            'device_id' => 'integer',
            'scanned_at' => 'datetime',
            'result' => ScanResultEnum::class,
            'response_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<NfcCard, $this>
     */
    public function nfcCard(): BelongsTo
    {
        return $this->belongsTo(NfcCard::class);
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
}

