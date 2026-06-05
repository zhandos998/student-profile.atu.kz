<?php

namespace Tests\Feature;

use App\Models\GroupSocialPassport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupSocialPassportTest extends TestCase
{
    use RefreshDatabase;

    public function test_curator_can_view_group_social_passport_page(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::CURATOR)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Куратор',
        ]);

        $this->actingAs($user)
            ->get(route('group-social-passport.edit'))
            ->assertOk();
    }

    public function test_student_cannot_view_group_social_passport_page(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Студент',
        ]);

        $this->actingAs($user)
            ->get(route('group-social-passport.edit'))
            ->assertForbidden();
    }

    public function test_curator_can_save_group_social_passport(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::CURATOR)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Куратор',
        ]);

        $this->actingAs($user)
            ->post(route('group-social-passport.update'), [
                'group_name' => 'ИС-23-1',
                'leader_full_name' => 'Иванов Иван Иванович',
                'leader_phone' => '+7 700 000 00 00',
                'leader_email' => 'leader@atu.kz',
                'curator_full_name' => 'Петров Петр Петрович',
                'curator_phone' => '+7 701 000 00 00',
                'curator_email' => 'curator@atu.kz',
                'students' => [
                    [
                        'full_name' => 'Сидоров Сидор Сидорович',
                        'birth_date' => '2005-01-10',
                        'study_form' => 'Очная',
                        'nationality' => 'Казах',
                        'iin' => '123456789012',
                        'identity_document_number' => 'ID1234567',
                        'contact_details' => '+7 702 000 00 00',
                        'stay_address' => 'г. Алматы, общежитие 1',
                        'residence_address' => 'г. Алматы',
                        'parent_details' => 'Отец, мать',
                        'social_status' => 'Многодетная семья',
                        'religion_details' => 'Не указано',
                    ],
                ],
                'summary' => [
                    'disabled_students' => 1,
                    'orphan_students' => 0,
                    'incomplete_family_students' => 0,
                    'large_family_students' => 1,
                    'low_income_students' => 0,
                    'married_students' => 0,
                    'foreign_students' => 0,
                    'dormitory_students' => 1,
                    'relatives_living_students' => 0,
                    'rental_housing_students' => 0,
                    'total_students' => 1,
                ],
                'departed_students' => [
                    [
                        'full_name' => 'Ахметов Ахмет Ахметович',
                        'faculty' => 'Факультет информационных технологий',
                        'education_program' => 'Информационные системы',
                        'group_name' => 'ИС-23-1',
                        'course' => 2,
                        'reason' => 'expelled',
                        'reason_other' => '',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $passport = GroupSocialPassport::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('ИС-23-1', $passport->group_name);
        $this->assertSame('Сидоров Сидор Сидорович', $passport->students[0]['full_name']);
        $this->assertSame('123456789012', $passport->students[0]['iin']);
        $this->assertSame('ID1234567', $passport->students[0]['identity_document_number']);
        $this->assertSame(1, $passport->summary['total_students']);
        $this->assertSame('Ахметов Ахмет Ахметович', $passport->departed_students[0]['full_name']);
        $this->assertSame('expelled', $passport->departed_students[0]['reason']);
    }

    public function test_student_cannot_save_group_social_passport(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Студент',
        ]);

        $this->actingAs($user)
            ->post(route('group-social-passport.update'), [
                'group_name' => 'ИС-23-1',
            ])
            ->assertForbidden();
    }
}
