<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
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
        ];

        foreach ($users as $definition) {
            $user = User::query()->firstOrNew(['email' => $definition['email']]);

            $user->fill([
                'name' => $definition['name'],
                'role_id' => $roles[$definition['role']] ?? null,
                'position' => $definition['position'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                'password' => Hash::make('password'),
            ]);

            $user->save();
        }

        if (isset($roles[Role::STUDENT])) {
            User::query()
                ->whereNull('role_id')
                ->update(['role_id' => $roles[Role::STUDENT]]);
        }
    }
}
