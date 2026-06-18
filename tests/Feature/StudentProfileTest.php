<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_page_can_be_rendered_for_authenticated_user(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($user)
            ->get(route('student-profile.edit'))
            ->assertOk();
    }

    public function test_group_leader_can_use_own_student_profile_page(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::GROUP_LEADER, 'Group leader');

        $this->actingAs($user)
            ->get(route('student-profile.edit'))
            ->assertOk();
    }

    public function test_student_can_save_own_profile_with_social_and_academic_fields_pending_review(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Student');
        $group = StudentGroup::query()->create([
            'faculty' => StudentProfileOptions::facultyNames()[4],
            'name' => 'TPP-23-1',
        ]);

        $this->actingAs($user)
            ->post(route('student-profile.update'), [
                'full_name' => 'Ivan Ivanov',
                'student_status' => StudentProfile::STUDENT_STATUS_DEPARTED,
                'departure_reason' => 'expelled',
                'departed_at' => '2026-06-01',
                'birth_date' => '2005-03-15',
                'study_form' => 'Full time',
                'nationality' => 'Kazakh',
                'citizenship' => 'kazakhstan_citizen',
                'military_department_status' => 'studying',
                'military_department_place' => 'Military department',
                'photo' => UploadedFile::fake()->image('student.png'),
                'iin' => '123456789012',
                'identity_document_number' => 'ID1234567',
                'identity_card' => UploadedFile::fake()->create('id-card.pdf', 100, 'application/pdf'),
                'gender' => 'male',
                'faculty' => StudentProfileOptions::facultyNames()[4],
                'student_group_id' => $group->id,
                'group_name' => 'TPP-23-1',
                'specialty' => 'Food technology',
                'course' => 2,
                'admission_year' => 2023,
                'marital_status' => 'single_male',
                'disability_group' => '2',
                'disabled_parent_group' => '1',
                'disabled_sibling_group' => '3',
                'is_orphan' => true,
                'legal_representative' => 'Petr Ivanov',
                'is_half_orphan' => true,
                'half_orphan_type' => 'single_parent',
                'is_incomplete_family' => true,
                'is_large_family' => true,
                'is_low_income' => true,
                'benefits' => ['asp', 'loss_of_breadwinner'],
                'social_support_need_status' => 'needs',
                'social_support_need_details' => 'Needs dormitory payment support',
                'stay_address' => 'Almaty, dormitory 1',
                'residence_address' => 'Almaty',
                'contact_details' => '+7 700 000 00 00',
                'personal_email' => 'ivan.student@example.com',
                'parent_guardian_contacts' => 'Parent: +7 701 000 00 00, Almaty',
                'kandas_country' => 'Mongolia',
                'dormitory_details' => 'Dormitory 1, room 101',
                'education_language' => 'ru',
                'gpa' => 3.45,
                'final_grades' => 'A, B+',
                'current_performance' => 'Stable',
                'academic_debt' => 'No',
                'grade_dynamics' => 'Growth',
                'group_comparison' => 'Above average',
                'success_forecast' => 'High',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = StudentProfile::query()->where('user_id', $user->id)->firstOrFail();
        $academicProfile = AcademicProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Ivan Ivanov', $profile->full_name);
        $this->assertSame(StudentProfile::STUDENT_STATUS_ACTIVE, $profile->student_status);
        $this->assertNull($profile->departure_reason);
        $this->assertNull($profile->departed_at);
        $this->assertSame('kazakhstan_citizen', $profile->citizenship);
        $this->assertSame('studying', $profile->military_department_status);
        $this->assertSame('Military department', $profile->military_department_place);
        $this->assertSame(StudentProfileOptions::facultyNames()[4], $profile->faculty);
        $this->assertSame('TPP-23-1', $profile->group_name);
        $this->assertSame('Mongolia', $profile->kandas_country);
        $this->assertSame(StudentProfile::STATUS_SUBMITTED, $profile->profile_status);
        $this->assertNotNull($profile->submitted_at);
        $this->assertSame('ivan.student@example.com', $profile->personal_email);
        $this->assertSame('Parent: +7 701 000 00 00, Almaty', $profile->parent_guardian_contacts);
        $this->assertSame('2', $profile->disability_group);
        $this->assertSame('1', $profile->disabled_parent_group);
        $this->assertTrue($profile->is_orphan);
        $this->assertSame('Petr Ivanov', $profile->legal_representative);
        $this->assertTrue($profile->is_half_orphan);
        $this->assertSame('single_parent', $profile->half_orphan_type);
        $this->assertTrue($profile->is_incomplete_family);
        $this->assertTrue($profile->is_large_family);
        $this->assertTrue($profile->is_low_income);
        $this->assertSame(['asp', 'loss_of_breadwinner'], $profile->benefits);
        $this->assertSame('needs', $profile->social_support_need_status);
        $this->assertSame('Needs dormitory payment support', $profile->social_support_need_details);
        $this->assertSame(StudentProfile::REVIEW_PENDING, $profile->social_review_status);
        $this->assertSame('3.45', $academicProfile->gpa);
        $this->assertNull($academicProfile->grade_dynamics);
        $this->assertNull($academicProfile->group_comparison);
        $this->assertNull($academicProfile->success_forecast);
        $this->assertSame(AcademicProfile::REVIEW_PENDING, $academicProfile->academic_review_status);
        Storage::disk('public')->assertExists($profile->photo_path);
        Storage::disk('public')->assertExists($profile->identity_card_path);
    }

    public function test_student_can_submit_profile_for_review(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Student');

        StudentProfile::query()->create([
            'user_id' => $user->id,
            'profile_status' => StudentProfile::STATUS_DRAFT,
            'full_name' => 'Draft Student',
        ]);

        $this->actingAs($user)
            ->post(route('student-profile.submit'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = StudentProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(StudentProfile::STATUS_SUBMITTED, $profile->profile_status);
        $this->assertNotNull($profile->submitted_at);
    }

    public function test_user_cannot_save_unknown_group_name(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($user)
            ->post(route('student-profile.update'), [
                'full_name' => 'Unknown Group Student',
                'faculty' => StudentProfileOptions::facultyNames()[3],
                'group_name' => 'UNKNOWN-1',
            ])
            ->assertSessionHasErrors('group_name');
    }

    public function test_student_can_save_selected_group_by_id_without_group_name(): void
    {
        $this->seed(RoleSeeder::class);

        $student = $this->userWithRole(Role::STUDENT, 'Student');
        $group = StudentGroup::query()->create([
            'faculty' => 'Старое значение факультета',
            'name' => 'OLD-23-1',
        ]);

        $this->actingAs($student)
            ->post(route('student-profile.update'), [
                'full_name' => 'Student With Group Id',
                'faculty' => 'Старое значение факультета',
                'student_group_id' => $group->id,
                'course' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = StudentProfile::query()->where('user_id', $student->id)->firstOrFail();

        $this->assertSame($group->id, $profile->student_group_id);
        $this->assertSame('OLD-23-1', $profile->group_name);
        $this->assertSame('Старое значение факультета', $profile->faculty);
    }

    public function test_selected_group_id_overrides_stale_group_name(): void
    {
        $this->seed(RoleSeeder::class);

        $student = $this->userWithRole(Role::STUDENT, 'Student');
        $group = StudentGroup::query()->create([
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'name' => 'IS-23-8',
        ]);

        $this->actingAs($student)
            ->post(route('student-profile.update'), [
                'full_name' => 'Student With Stale Group',
                'faculty' => StudentProfileOptions::facultyNames()[3],
                'student_group_id' => $group->id,
                'group_name' => 'OLD-TEXT-GROUP',
                'course' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = StudentProfile::query()->where('user_id', $student->id)->firstOrFail();

        $this->assertSame($group->id, $profile->student_group_id);
        $this->assertSame('IS-23-8', $profile->group_name);
    }

    public function test_student_appears_in_group_social_passport_after_selecting_group(): void
    {
        $this->seed(RoleSeeder::class);

        $curator = $this->userWithRole(Role::CURATOR, 'Curator');
        $student = $this->userWithRole(Role::STUDENT, 'Student', [
            'name' => 'Student User',
        ]);
        $group = StudentGroup::query()->create([
            'curator_id' => $curator->id,
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'name' => 'IS-23-7',
        ]);

        $this->actingAs($student)
            ->post(route('student-profile.update'), [
                'full_name' => 'Student In Selected Group',
                'faculty' => StudentProfileOptions::facultyNames()[3],
                'student_group_id' => $group->id,
                'group_name' => 'IS-23-7',
                'course' => 2,
                'education_language' => 'ru',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($curator)
            ->get(route('groups.social-passport.edit', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GroupSocialPassport/Edit')
                ->has('passport.students', 1)
                ->where('passport.students.0.full_name', 'Student In Selected Group')
            );
    }

    public function test_user_can_add_and_delete_own_achievement(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($user)
            ->post(route('student-profile.achievements.store'), [
                'activity_type' => 'olympiad',
                'title' => 'Republican olympiad',
                'level' => 'republican',
                'result' => 'first_place',
                'description' => 'Profile olympiad winner',
                'document' => UploadedFile::fake()->create('diploma.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $achievement = ExtracurricularAchievement::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Republican olympiad', $achievement->title);
        Storage::disk('public')->assertExists($achievement->document_path);

        $this->actingAs($user)
            ->delete(route('student-profile.achievements.destroy', $achievement))
            ->assertRedirect();

        $this->assertDatabaseMissing('extracurricular_achievements', [
            'id' => $achievement->id,
        ]);
        Storage::disk('public')->assertMissing($achievement->document_path);
    }

    public function test_user_can_add_and_delete_own_portfolio_item(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $user = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($user)
            ->post(route('student-profile.portfolio.store'), [
                'item_type' => 'thank_you_letter',
                'title' => 'Thank you letter',
                'file' => UploadedFile::fake()->create('thank-you-letter.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $portfolioItem = PortfolioItem::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('thank_you_letter', $portfolioItem->item_type);
        $this->assertSame('Thank you letter', $portfolioItem->title);
        Storage::disk('public')->assertExists($portfolioItem->file_path);

        $this->actingAs($user)
            ->delete(route('student-profile.portfolio.destroy', $portfolioItem))
            ->assertRedirect();

        $this->assertDatabaseMissing('portfolio_items', [
            'id' => $portfolioItem->id,
        ]);
        Storage::disk('public')->assertMissing($portfolioItem->file_path);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleSlug, string $position, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'position' => $position,
            ...$attributes,
        ]);
    }
}
