<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'slug');

        $users = [
            [
                'name' => 'Администратор ДИТ',
                'email' => 'admin.dit@atu.kz',
                'role' => Role::ADMINISTRATOR_DIT,
                'position' => 'Администратор ДИТ',
            ],
            [
                'name' => 'Проректор по ВР',
                'email' => 'vice.rector.vr@atu.kz',
                'role' => Role::ADMINISTRATION,
                'position' => 'Проректор ВР',
            ],
            [
                'name' => 'Декан',
                'email' => 'dean@atu.kz',
                'role' => Role::ADMINISTRATION,
                'position' => 'Декан',
            ],
            [
                'name' => 'Заместитель декана по ВР',
                'email' => 'deputy.dean.vr@atu.kz',
                'role' => Role::ADMINISTRATION,
                'position' => 'Зам. декана по ВР',
            ],
            [
                'name' => 'Директор ДДМ',
                'email' => 'ddm.director@atu.kz',
                'role' => Role::ADMINISTRATION,
                'position' => 'Директор ДДМ',
            ],
            [
                'name' => 'Психолог',
                'email' => 'psychologist@atu.kz',
                'role' => Role::ADMINISTRATION,
                'position' => 'Психолог',
            ],
            [
                'name' => 'Здравпункт',
                'email' => 'health.center@atu.kz',
                'role' => Role::ADMINISTRATION,
                'position' => 'Здравпункт',
            ],
            [
                'name' => 'Офис регистратора',
                'email' => 'registrar.office@atu.kz',
                'role' => Role::ADMINISTRATION,
                'position' => 'Офис регистратора',
            ],
            [
                'name' => 'Куратор',
                'email' => 'curator@atu.kz',
                'role' => Role::CURATOR,
                'position' => 'Куратор',
            ],
            [
                'name' => 'Эдвайзер',
                'email' => 'advisor@atu.kz',
                'role' => Role::ADVISOR,
                'position' => 'Эдвайзер',
            ],
            [
                'name' => 'Староста',
                'email' => 'group.leader@atu.kz',
                'role' => Role::GROUP_LEADER,
                'position' => 'Староста',
            ],
            [
                'name' => 'Студент',
                'email' => 'student@atu.kz',
                'role' => Role::STUDENT,
                'position' => 'Студент',
            ],
            [
                'name' => 'Куратор ФИТ',
                'email' => 'curator.fit@atu.kz',
                'phone' => '+7 701 100 00 01',
                'platonus_login' => 'curator_fit',
                'role' => Role::CURATOR,
                'position' => 'Куратор / эдвайзер',
            ],
            [
                'name' => 'Куратор ФЭБ',
                'email' => 'curator.feb@atu.kz',
                'phone' => '+7 701 100 00 02',
                'platonus_login' => 'curator_feb',
                'role' => Role::CURATOR,
                'position' => 'Куратор / эдвайзер',
            ],
            [
                'name' => 'Эдвайзер пищевых технологий',
                'email' => 'advisor.food@atu.kz',
                'phone' => '+7 701 100 00 03',
                'platonus_login' => 'advisor_food',
                'role' => Role::ADVISOR,
                'position' => 'Куратор / эдвайзер',
            ],
            [
                'name' => 'Староста ИС-23-1',
                'email' => 'leader.is231@atu.kz',
                'phone' => '+7 701 100 00 04',
                'platonus_login' => 'leader_is231',
                'role' => Role::GROUP_LEADER,
                'position' => 'Староста',
            ],
            [
                'name' => 'Айдана Садыкова',
                'email' => 'aidana.sadykova@atu.kz',
                'phone' => '+7 701 100 00 05',
                'platonus_login' => 'aidana_sadykova',
                'role' => Role::STUDENT,
                'position' => 'Студент',
            ],
            [
                'name' => 'Бекзат Нурланов',
                'email' => 'bekzat.nurlanov@atu.kz',
                'phone' => '+7 701 100 00 06',
                'platonus_login' => 'bekzat_nurlanov',
                'role' => Role::STUDENT,
                'position' => 'Студент',
            ],
            [
                'name' => 'Мадина Ермекова',
                'email' => 'madina.ermekova@atu.kz',
                'phone' => '+7 701 100 00 07',
                'platonus_login' => 'madina_ermekova',
                'role' => Role::STUDENT,
                'position' => 'Студент',
            ],
        ];

        foreach ($users as $definition) {
            $user = User::query()->firstOrNew(['email' => $definition['email']]);

            $attributes = [
                'name' => $definition['name'],
                'role_id' => $roles[$definition['role']] ?? null,
                'position' => $definition['position'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                'password' => Hash::make('password'),
                // $2y$12$9rGX.NKkNgxQuClznp3gSezTwBzCXNY4KHK/ymM0d0FBN9A9B3uYG
            ];

            if (array_key_exists('phone', $definition)) {
                $attributes['phone'] = $definition['phone'];
                $attributes['phone_normalized'] = Phone::normalize($definition['phone']);
            }

            if (array_key_exists('platonus_login', $definition)) {
                $attributes['platonus_login'] = $definition['platonus_login'];
            }

            $user->fill($attributes);

            $user->save();
        }

        if (isset($roles[Role::STUDENT])) {
            User::query()
                ->whereNull('role_id')
                ->update(['role_id' => $roles[Role::STUDENT]]);
        }
    }
}
