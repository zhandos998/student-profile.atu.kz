<?php

namespace Tests\Feature;

use App\Models\GroupSocialPassport;
use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GroupSocialPassportTest extends TestCase
{
    use RefreshDatabase;

    public function test_curator_can_view_group_list_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::CURATOR, 'Curator');

        $this->actingAs($user)
            ->get(route('groups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentGroups/Index')
                ->has('groups', 0)
            );
    }

    public function test_student_cannot_view_group_list_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($user)
            ->get(route('groups.index'))
            ->assertForbidden();
    }

    public function test_group_leader_cannot_view_group_list_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::GROUP_LEADER, 'Group leader');

        $this->actingAs($user)
            ->get(route('groups.index'))
            ->assertForbidden();
    }

    public function test_curator_can_create_multiple_groups(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::CURATOR, 'Curator');
        $faculty = StudentProfileOptions::facultyNames()[3];

        foreach (['IS-23-1', 'IS-23-2'] as $groupName) {
            $this->actingAs($user)
                ->post(route('groups.store'), [
                    'faculty' => $faculty,
                    'name' => $groupName,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }

        $this->assertSame(2, StudentGroup::query()->where('curator_id', $user->id)->count());
        $this->assertSame(2, GroupSocialPassport::query()->where('user_id', $user->id)->count());
    }

    public function test_administration_can_filter_group_list(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = $this->userWithRole(Role::ADMINISTRATION, 'Dean');
        $curator = $this->userWithRole(Role::CURATOR, 'Curator');
        $otherCurator = $this->userWithRole(Role::CURATOR, 'Other curator');
        $student = $this->userWithRole(Role::STUDENT, 'Student');
        $otherStudent = $this->userWithRole(Role::STUDENT, 'Other student');
        $faculty = StudentProfileOptions::facultyNames()[3];
        $otherFaculty = StudentProfileOptions::facultyNames()[4];

        $group = StudentGroup::query()->create([
            'curator_id' => $curator->id,
            'faculty' => $faculty,
            'name' => 'IS-23-1',
        ]);
        $otherGroup = StudentGroup::query()->create([
            'curator_id' => $otherCurator->id,
            'faculty' => $otherFaculty,
            'name' => 'TPP-23-1',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_group_id' => $group->id,
            'faculty' => $faculty,
            'group_name' => 'IS-23-1',
            'course' => 2,
        ]);
        StudentProfile::query()->create([
            'user_id' => $otherStudent->id,
            'student_group_id' => $otherGroup->id,
            'faculty' => $otherFaculty,
            'group_name' => 'TPP-23-1',
            'course' => 3,
        ]);

        $this->actingAs($administrator)
            ->get(route('groups.index', [
                'faculty' => $faculty,
                'course' => 2,
                'curator_id' => $curator->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentGroups/Index')
                ->where('filters.faculty', $faculty)
                ->where('filters.course', '2')
                ->where('filters.curator_id', (string) $curator->id)
                ->has('options.curators', 2)
                ->has('groups', 1)
                ->where('groups.0.name', 'IS-23-1')
            );
    }

    public function test_curator_can_open_group_social_passport_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::CURATOR, 'Curator');
        $group = $this->studentGroup($user, 'IS-23-1');

        $this->actingAs($user)
            ->get(route('groups.social-passport.edit', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GroupSocialPassport/Edit')
                ->where('passport.student_group_id', (string) $group->id)
                ->where('passport.group_name', 'IS-23-1')
            );
    }

    public function test_curator_cannot_open_another_curators_group(): void
    {
        $this->seed(RoleSeeder::class);

        $curator = $this->userWithRole(Role::CURATOR, 'Curator');
        $otherCurator = $this->userWithRole(Role::CURATOR, 'Other curator');
        $group = $this->studentGroup($otherCurator, 'IS-23-1');

        $this->actingAs($curator)
            ->get(route('groups.social-passport.edit', $group))
            ->assertForbidden();
    }

    public function test_legacy_group_social_passport_route_redirects_to_first_group(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::CURATOR, 'Curator');
        $group = $this->studentGroup($user, 'IS-23-1');

        $this->actingAs($user)
            ->get(route('group-social-passport.edit'))
            ->assertRedirect(route('groups.social-passport.edit', $group));
    }

    public function test_curator_can_save_group_social_passport_for_selected_group(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::CURATOR, 'Curator');
        $group = $this->studentGroup($user, 'IS-23-1');

        $this->actingAs($user)
            ->post(route('groups.social-passport.update', $group), [
                'faculty' => StudentProfileOptions::facultyNames()[3],
                'group_name' => 'IS-23-1',
                'leader_full_name' => 'Ivan Leader',
                'leader_phone' => '+7 700 000 00 00',
                'leader_email' => 'leader@atu.kz',
                'curator_full_name' => 'Petr Curator',
                'curator_phone' => '+7 701 000 00 00',
                'curator_email' => 'curator@atu.kz',
                'deputy_dean_ur_full_name' => 'Anna UR',
                'deputy_dean_ur_phone' => '+7 702 111 22 33',
                'deputy_dean_ur_email' => 'deputy.ur@atu.kz',
                'deputy_dean_vr_full_name' => 'Asel VR',
                'deputy_dean_vr_phone' => '+7 703 111 22 33',
                'deputy_dean_vr_email' => 'deputy.vr@atu.kz',
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
                        'full_name' => 'Departed Student',
                        'faculty' => StudentProfileOptions::facultyNames()[3],
                        'education_program' => 'Information systems',
                        'group_name' => 'IS-23-1',
                        'course' => 2,
                        'reason' => 'expelled',
                        'reason_other' => '',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $passport = GroupSocialPassport::query()
            ->where('student_group_id', $group->id)
            ->firstOrFail();

        $this->assertSame(StudentProfileOptions::facultyNames()[3], $passport->faculty);
        $this->assertSame($group->id, $passport->student_group_id);
        $this->assertSame('IS-23-1', $passport->group_name);
        $this->assertSame('Anna UR', $passport->deputy_dean_ur_full_name);
        $this->assertSame('deputy.ur@atu.kz', $passport->deputy_dean_ur_email);
        $this->assertSame('Asel VR', $passport->deputy_dean_vr_full_name);
        $this->assertSame([], $passport->students);
        $this->assertSame(0, $passport->summary['total_students']);
        $this->assertSame(0, $passport->summary['disabled_students']);
        $this->assertSame([], $passport->departed_students);
    }

    public function test_group_social_passport_loads_students_from_student_profiles(): void
    {
        $this->seed(RoleSeeder::class);

        $curator = $this->userWithRole(Role::CURATOR, 'Curator');
        $student = $this->userWithRole(Role::STUDENT, 'Student', [
            'name' => 'Student User',
        ]);
        $group = $this->studentGroup($curator, 'IS-23-2');

        GroupSocialPassport::query()->create([
            'user_id' => $curator->id,
            'student_group_id' => $group->id,
            'faculty' => $group->faculty,
            'group_name' => $group->name,
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Student From Profile',
            'birth_date' => '2005-01-10',
            'study_form' => 'Full time',
            'nationality' => 'Kazakh',
            'iin' => '123456789012',
            'identity_document_number' => 'ID1234567',
            'student_group_id' => $group->id,
            'faculty' => $group->faculty,
            'group_name' => $group->name,
            'contact_details' => '+7 702 000 00 00',
            'stay_address' => 'Almaty, dormitory 1',
            'residence_address' => 'Almaty',
            'disability_group' => '2',
            'is_orphan' => true,
            'is_incomplete_family' => true,
            'is_large_family' => true,
            'is_low_income' => true,
            'marital_status' => 'married_male',
            'foreign_student_country' => 'Mongolia',
            'dormitory_details' => 'Dormitory 1',
            'relatives_living_details' => 'Aunt',
            'rental_housing_details' => 'Apartment',
        ]);
        StudentProfile::query()->create([
            'user_id' => $this->userWithRole(Role::STUDENT, 'Departed student')->id,
            'full_name' => 'Departed From Profile',
            'student_group_id' => $group->id,
            'faculty' => $group->faculty,
            'group_name' => $group->name,
            'specialty' => 'Information systems',
            'course' => 2,
            'student_status' => StudentProfile::STUDENT_STATUS_DEPARTED,
            'departure_reason' => 'expelled',
            'departed_at' => '2026-06-01',
        ]);

        $this->actingAs($curator)
            ->get(route('groups.social-passport.edit', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GroupSocialPassport/Edit')
                ->has('passport.students', 1)
                ->where('passport.students.0.full_name', 'Student From Profile')
                ->where('passport.students.0.iin', '123456789012')
                ->where('passport.summary.disabled_students', 1)
                ->where('passport.summary.orphan_students', 1)
                ->where('passport.summary.incomplete_family_students', 1)
                ->where('passport.summary.large_family_students', 1)
                ->where('passport.summary.low_income_students', 1)
                ->where('passport.summary.married_students', 1)
                ->where('passport.summary.foreign_students', 1)
                ->where('passport.summary.dormitory_students', 1)
                ->where('passport.summary.relatives_living_students', 1)
                ->where('passport.summary.rental_housing_students', 1)
                ->where('passport.summary.total_students', 1)
                ->has('passport.departed_students', 1)
                ->where('passport.departed_students.0.full_name', 'Departed From Profile')
                ->where('passport.departed_students.0.reason', 'expelled')
                ->where('passport.departed_students.0.reason_label', 'Отчислен')
                ->where('passport.departed_students.0.departed_at', '2026-06-01')
            );
    }

    public function test_student_cannot_save_group_social_passport(): void
    {
        $this->seed(RoleSeeder::class);

        $curator = $this->userWithRole(Role::CURATOR, 'Curator');
        $student = $this->userWithRole(Role::STUDENT, 'Student');
        $group = $this->studentGroup($curator, 'IS-23-1');

        $this->actingAs($student)
            ->post(route('groups.social-passport.update', $group), [
                'group_name' => 'IS-23-1',
            ])
            ->assertForbidden();
    }

    public function test_group_leader_cannot_save_group_social_passport(): void
    {
        $this->seed(RoleSeeder::class);

        $curator = $this->userWithRole(Role::CURATOR, 'Curator');
        $leader = $this->userWithRole(Role::GROUP_LEADER, 'Group leader');
        $group = $this->studentGroup($curator, 'IS-23-1');

        $this->actingAs($leader)
            ->post(route('groups.social-passport.update', $group), [
                'group_name' => 'IS-23-1',
            ])
            ->assertForbidden();
    }

    private function userWithRole(string $roleSlug, string $position, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'position' => $position,
            ...$attributes,
        ]);
    }

    private function studentGroup(User $curator, string $name): StudentGroup
    {
        return StudentGroup::query()->create([
            'curator_id' => $curator->id,
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'name' => $name,
        ]);
    }
}
