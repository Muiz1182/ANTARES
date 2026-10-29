<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SaveUserAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $payload = [
                'name' => trim((string) $data['name']),
                'email' => strtolower(trim((string) $data['email'])),
                'nip' => isset($data['nip']) && trim((string) $data['nip']) !== '' ? trim((string) $data['nip']) : null,
                'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
                'is_active' => (bool) $data['is_active'],
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make((string) $data['password']);
            }

            if ($user) {
                $user->update($payload);
            } else {
                $user = User::create($payload);
            }

            $user->syncRoles([(string) $data['role']]);

            return $user->refresh();
        });
    }
}
