<?php

namespace App\Actions\Setting;

use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateSchoolSettingAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        array $data,
        User $actor,
        ?UploadedFile $logo = null,
    ): SchoolSetting {
        return DB::transaction(function () use ($data, $actor, $logo): SchoolSetting {
            $setting = SchoolSetting::current();

            $before = [
                'school_name' => $setting->school_name,
                'school_address' => $setting->school_address,
                'principal_name' => $setting->principal_name,
                'principal_nip' => $setting->principal_nip,
                'logo_path' => $setting->logo_path,
                'timezone' => $setting->timezone,
            ];

            $logoPath = $setting->logo_path;

            if (! empty($data['remove_logo']) && $logoPath !== null) {
                Storage::disk('public')->delete($logoPath);
                $logoPath = null;
            }

            if ($logo !== null) {
                if ($logoPath !== null) {
                    Storage::disk('public')->delete($logoPath);
                }
                $logoPath = $logo->store('school-logos', 'public');
            }

            $setting->update([
                'school_name' => trim((string) $data['school_name']),
                'school_address' => isset($data['school_address']) && trim((string) $data['school_address']) !== ''
                    ? trim((string) $data['school_address'])
                    : null,
                'principal_name' => isset($data['principal_name']) && trim((string) $data['principal_name']) !== ''
                    ? trim((string) $data['principal_name'])
                    : null,
                'principal_nip' => isset($data['principal_nip']) && trim((string) $data['principal_nip']) !== ''
                    ? trim((string) $data['principal_nip'])
                    : null,
                'logo_path' => $logoPath,
                'timezone' => 'Asia/Jakarta',
            ]);

            $fresh = $setting->fresh();

            $this->auditLogService->record(
                action: 'school_setting.updated',
                subject: $fresh,
                before: $before,
                after: [
                    'school_name' => $fresh->school_name,
                    'school_address' => $fresh->school_address,
                    'principal_name' => $fresh->principal_name,
                    'principal_nip' => $fresh->principal_nip,
                    'logo_path' => $fresh->logo_path,
                    'timezone' => $fresh->timezone,
                ],
                reason: 'Pembaruan pengaturan identitas sekolah',
                actor: $actor,
            );

            return $fresh;
        });
    }
}

