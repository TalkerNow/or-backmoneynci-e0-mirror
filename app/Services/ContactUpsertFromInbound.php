<?php

namespace App\Services;

use App\Models\InboundEmail;
use App\Models\PersonalInformations;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Upsert Prospect/Contact from CF7 inbound email — TEST Lot 1 (Martin×JF).
 * Prefills état civil; does NOT inject message into contract notes.
 */
class ContactUpsertFromInbound
{
    public function upsert(InboundEmail $row, ?int $parentId = null, ?int $businessIntroducerId = null): array
    {
        $parsed = Cf7ContactParser::parse($row->body ?: $row->snippet);
        $email = strtolower(trim($parsed['email'] ?: (string) $row->from_email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'reason' => 'missing_identity',
                'message' => 'Email requis pour upsert Contact (CF7).',
            ];
        }

        $firstName = $parsed['first_name'] ?: '';
        $lastName = $parsed['last_name'] ?: '';
        if ($firstName === '' && $lastName === '' && $row->from_name) {
            $parts = preg_split('/\s+/', trim($row->from_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) === 1) {
                $firstName = $parts[0];
            } elseif (count($parts) > 1) {
                $firstName = $parts[0];
                $lastName = implode(' ', array_slice($parts, 1));
            }
        }
        $fullName = trim($firstName . ' ' . $lastName) ?: ($row->from_name ?: $email);
        $phone = $parsed['phone'] ?: null;
        $birthDate = $parsed['birth_date'] ?: null;
        $civility = $parsed['civility'] ?: null;
        $inboundMessage = $parsed['message'] ?: null;

        $created = false;
        $updated = false;

        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make(Str::random(16) . '1!'),
                'role' => 'Prospect',
                'parent_id' => $parentId,
                'business_introducer_id' => $businessIntroducerId,
            ]);
            $created = true;
        } else {
            $patch = [];
            $uname = (string) $user->name;
            if ($fullName && ($uname === '' || strpos($uname, 'user-') === 0 || strpos($uname, 'prospect_') === 0)) {
                $patch['name'] = $fullName;
            }
            if ($parentId && empty($user->parent_id)) {
                $patch['parent_id'] = $parentId;
            }
            if ($businessIntroducerId && empty($user->business_introducer_id)) {
                $patch['business_introducer_id'] = $businessIntroducerId;
            }
            $role = strtolower((string) $user->role);
            if ($role === '' || $role === 'user') {
                $patch['role'] = 'Prospect';
            }
            if ($patch) {
                $user->update($patch);
                $updated = true;
            }
        }

        $info = PersonalInformations::find($user->id);
        $infoPayload = [];
        if ($firstName !== '') {
            $infoPayload['first_name'] = $firstName;
        }
        if ($lastName !== '') {
            $infoPayload['last_name'] = $lastName;
        }
        $infoPayload['email'] = $email;
        if ($phone) {
            $infoPayload['mobile_number'] = $phone;
        }
        if ($birthDate) {
            $infoPayload['birth_date'] = $birthDate;
        }
        if ($civility) {
            $infoPayload['civility'] = $civility;
        }
        if ($parentId) {
            $infoPayload['parent_id'] = $parentId;
        }
        if ($businessIntroducerId) {
            $infoPayload['business_introducer_id'] = $businessIntroducerId;
        }

        if (!$info) {
            PersonalInformations::create(array_merge([
                'id' => $user->id,
                'user_id' => 10,
            ], $infoPayload));
            $created = true;
        } else {
            $fill = [];
            foreach ($infoPayload as $k => $v) {
                $cur = $info->{$k} ?? null;
                if ($cur === null || $cur === '') {
                    $fill[$k] = $v;
                }
            }
            if ($fill) {
                $info->update($fill);
                $updated = true;
            }
        }

        $row->client_id = $user->id;
        $row->save();

        return [
            'ok' => true,
            'created' => $created,
            'updated' => $updated,
            'client_id' => $user->id,
            'inbound_email_id' => $row->id,
            'inbound_message' => $inboundMessage,
            'etat_civil' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'mobile_number' => $phone,
                'birth_date' => $birthDate,
                'civility' => $civility,
            ],
        ];
    }
}
