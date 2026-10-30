<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Enums\NfcCardStatusEnum;
use App\Enums\ScanResultEnum;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceRule;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\NfcCard;
use App\Models\Student;
use App\Support\NfcUidNormalizer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ScanService
{
    /**
     * Evaluate and record an NFC card tap according to PRD Section 4.3.
     *
     * @return array<string, mixed>
     */
    public function processTap(
        string $rawUid,
        ?Device $device = null,
        CarbonInterface|string|null $scannedAt = null,
        ?string $idempotencyKey = null,
    ): array {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existingLog = AttendanceLog::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingLog !== null && is_array($existingLog->response_payload)) {
                return $existingLog->response_payload;
            }
        }

        $scanTime = $this->resolveScanTime($scannedAt);

        return DB::transaction(function () use ($rawUid, $device, $scanTime, $idempotencyKey): array {
            $normalizedUid = NfcUidNormalizer::tryNormalize($rawUid);

            $card = $normalizedUid !== null
                ? NfcCard::query()->where('uid', $normalizedUid)->first()
                : null;

            if ($card === null) {
                return $this->finalizeScan(
                    result: ScanResultEnum::UnknownCard,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: null,
                    student: null,
                    attendance: null,
                    idempotencyKey: $idempotencyKey,
                );
            }

            $student = $card->cardable_type === Student::class
                ? Student::withTrashed()->with('classroom')->find($card->cardable_id)
                : null;

            $dateString = $scanTime->toDateString();

            $existingAttendance = $student !== null
                ? Attendance::query()
                    ->where('attendable_type', Student::class)
                    ->where('attendable_id', $student->id)
                    ->where('date', $dateString)
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($card->status === NfcCardStatusEnum::Inactive) {
                return $this->finalizeScan(
                    result: ScanResultEnum::InactiveCard,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance,
                    idempotencyKey: $idempotencyKey,
                );
            }

            if ($card->status === NfcCardStatusEnum::Lost) {
                return $this->finalizeScan(
                    result: ScanResultEnum::LostCard,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance,
                    idempotencyKey: $idempotencyKey,
                );
            }

            if ($student === null || ! $student->is_active || $student->trashed()) {
                return $this->finalizeScan(
                    result: ScanResultEnum::StudentInactive,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance,
                    idempotencyKey: $idempotencyKey,
                );
            }

            $isHoliday = Holiday::query()->where('date', $dateString)->exists();
            $rule = AttendanceRule::query()
                ->where('day_of_week', $scanTime->isoWeekday())
                ->first();

            if ($isHoliday || $rule === null || ! $rule->is_school_day) {
                return $this->finalizeScan(
                    result: ScanResultEnum::NoSchoolDay,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance,
                    idempotencyKey: $idempotencyKey,
                );
            }

            $debounceSeconds = max(0, (int) $rule->debounce_seconds);
            if ($debounceSeconds > 0) {
                $windowStart = $scanTime->copy()->subSeconds($debounceSeconds);
                $hasRecentTap = AttendanceLog::query()
                    ->where('nfc_card_id', $card->id)
                    ->whereIn('result', [
                        ScanResultEnum::SuccessIn->value,
                        ScanResultEnum::SuccessOut->value,
                        ScanResultEnum::Duplicate->value,
                    ])
                    ->where('scanned_at', '>=', $windowStart)
                    ->where('scanned_at', '<=', $scanTime)
                    ->exists();

                if ($hasRecentTap) {
                    return $this->finalizeScan(
                        result: ScanResultEnum::Duplicate,
                        rawUid: $rawUid,
                        normalizedUid: $normalizedUid,
                        scanTime: $scanTime,
                        device: $device,
                        card: $card,
                        student: $student,
                        attendance: $existingAttendance,
                        idempotencyKey: $idempotencyKey,
                    );
                }
            }

            $timeString = $scanTime->format('H:i:s');
            $lateAfter = $this->normalizeRuleTime($rule->late_after);
            $checkOutStart = $this->normalizeRuleTime($rule->check_out_start);

            if ($timeString < $checkOutStart) {
                if ($existingAttendance === null || $existingAttendance->status === AttendanceStatusEnum::Absent) {
                    $status = $timeString <= $lateAfter
                        ? AttendanceStatusEnum::Present
                        : AttendanceStatusEnum::Late;

                    if ($existingAttendance === null) {
                        $attendance = Attendance::query()->create([
                            'attendable_type' => Student::class,
                            'attendable_id' => $student->id,
                            'date' => $dateString,
                            'check_in_at' => $scanTime,
                            'check_out_at' => null,
                            'status' => $status,
                            'source' => AttendanceSourceEnum::Nfc,
                            'device_id' => $device?->id,
                        ]);
                    } else {
                        $existingAttendance->update([
                            'check_in_at' => $scanTime,
                            'status' => $status,
                            'source' => AttendanceSourceEnum::Nfc,
                            'device_id' => $device?->id,
                        ]);
                        $attendance = $existingAttendance->fresh();
                    }

                    return $this->finalizeScan(
                        result: ScanResultEnum::SuccessIn,
                        rawUid: $rawUid,
                        normalizedUid: $normalizedUid,
                        scanTime: $scanTime,
                        device: $device,
                        card: $card,
                        student: $student,
                        attendance: $attendance,
                        idempotencyKey: $idempotencyKey,
                    );
                }

                if ($existingAttendance->status->isExcused()) {
                    return $this->finalizeScan(
                        result: ScanResultEnum::BlockedExcused,
                        rawUid: $rawUid,
                        normalizedUid: $normalizedUid,
                        scanTime: $scanTime,
                        device: $device,
                        card: $card,
                        student: $student,
                        attendance: $existingAttendance,
                        idempotencyKey: $idempotencyKey,
                    );
                }

                return $this->finalizeScan(
                    result: ScanResultEnum::AlreadyCheckedIn,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance,
                    idempotencyKey: $idempotencyKey,
                );
            }

            if ($existingAttendance !== null && $existingAttendance->status->isExcused() && $existingAttendance->check_in_at === null) {
                return $this->finalizeScan(
                    result: ScanResultEnum::BlockedExcused,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance,
                    idempotencyKey: $idempotencyKey,
                );
            }

            if ($existingAttendance !== null && $existingAttendance->check_in_at !== null && $existingAttendance->check_out_at === null) {
                $existingAttendance->update([
                    'check_out_at' => $scanTime,
                    'device_id' => $device?->id ?? $existingAttendance->device_id,
                ]);

                return $this->finalizeScan(
                    result: ScanResultEnum::SuccessOut,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance->fresh(),
                    idempotencyKey: $idempotencyKey,
                );
            }

            if ($existingAttendance !== null && $existingAttendance->check_out_at !== null) {
                return $this->finalizeScan(
                    result: ScanResultEnum::AlreadyCheckedOut,
                    rawUid: $rawUid,
                    normalizedUid: $normalizedUid,
                    scanTime: $scanTime,
                    device: $device,
                    card: $card,
                    student: $student,
                    attendance: $existingAttendance,
                    idempotencyKey: $idempotencyKey,
                );
            }

            return $this->finalizeScan(
                result: ScanResultEnum::NoCheckin,
                rawUid: $rawUid,
                normalizedUid: $normalizedUid,
                scanTime: $scanTime,
                device: $device,
                card: $card,
                student: $student,
                attendance: $existingAttendance,
                idempotencyKey: $idempotencyKey,
            );
        });
    }

    private function resolveScanTime(CarbonInterface|string|null $scannedAt): Carbon
    {
        if ($scannedAt instanceof CarbonInterface) {
            return Carbon::instance($scannedAt)->setTimezone('Asia/Jakarta');
        }

        if (is_string($scannedAt) && trim($scannedAt) !== '') {
            return Carbon::parse($scannedAt)->setTimezone('Asia/Jakarta');
        }

        return Carbon::now('Asia/Jakarta');
    }

    private function normalizeRuleTime(string $time): string
    {
        $trimmed = trim($time);

        return strlen($trimmed) === 5 ? $trimmed.':00' : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function finalizeScan(
        ScanResultEnum $result,
        string $rawUid,
        ?string $normalizedUid,
        Carbon $scanTime,
        ?Device $device,
        ?NfcCard $card,
        ?Student $student,
        ?Attendance $attendance,
        ?string $idempotencyKey,
    ): array {
        $payload = [
            'ok' => $result->isOk(),
            'result' => $result->value,
            'message' => $result->message(),
            'sound_tone' => $result->soundTone(),
            'scanned_at' => $scanTime->toIso8601String(),
            'uid_normalized' => $normalizedUid,
            'student' => $student !== null ? [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'classroom' => $student->classroom?->name,
                'photo_url' => $student->photoUrl(),
            ] : null,
            'attendance' => $attendance !== null ? [
                'status' => $attendance->status->value,
                'status_label' => $attendance->status->label(),
                'check_in_at' => $attendance->check_in_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
                'check_out_at' => $attendance->check_out_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            ] : null,
        ];

        AttendanceLog::query()->create([
            'nfc_card_id' => $card?->id,
            'attendable_type' => $student !== null ? Student::class : null,
            'attendable_id' => $student?->id,
            'uid_raw' => mb_substr($rawUid, 0, 100),
            'uid_normalized' => $normalizedUid,
            'device_id' => $device?->id,
            'scanned_at' => $scanTime,
            'result' => $result,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'response_payload' => $payload,
        ]);

        return $payload;
    }
}

