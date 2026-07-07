<?php

namespace App\Support;

class FacultyDeputyDeanContacts
{
    /**
     * @return array{
     *     deputy_dean_ur_full_name: string,
     *     deputy_dean_ur_phone: string,
     *     deputy_dean_ur_email: string,
     *     deputy_dean_vr_full_name: string,
     *     deputy_dean_vr_phone: string,
     *     deputy_dean_vr_email: string
     * }
     */
    public static function passportDefaults(?string $faculty): array
    {
        $contacts = $faculty ? config("faculty_deputy_deans.contacts.{$faculty}", []) : [];
        $ur = self::normalizeContact($contacts['ur'] ?? []);
        $vr = self::normalizeContact($contacts['vr'] ?? []);

        return [
            'deputy_dean_ur_full_name' => $ur['full_name'],
            'deputy_dean_ur_phone' => $ur['phone'],
            'deputy_dean_ur_email' => $ur['email'],
            'deputy_dean_vr_full_name' => $vr['full_name'],
            'deputy_dean_vr_phone' => $vr['phone'],
            'deputy_dean_vr_email' => $vr['email'],
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array{full_name: string, phone: string, email: string}
     */
    private static function normalizeContact(array $contact): array
    {
        return [
            'full_name' => (string) ($contact['full_name'] ?? ''),
            'phone' => (string) ($contact['phone'] ?? ''),
            'email' => (string) ($contact['email'] ?? ''),
        ];
    }
}
