<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
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

class StudentProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_can_view_student_profiles_with_filters(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $student = $this->userWithRole(Role::STUDENT, 'Student', [
            'name' => 'Aidana Sadykova',
            'email' => 'aidana@example.com',
        ]);
        $group = StudentGroup::query()->create([
            'curator_id' => $advisor->id,
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'name' => 'IS-101',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'student_group_id' => $group->id,
            'full_name' => 'Aidana Sadykova',
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'group_name' => 'IS-101',
            'course' => 2,
        ]);

        AcademicProfile::query()->create([
            'user_id' => $student->id,
            'gpa' => 3.5,
        ]);

        $this->actingAs($advisor)
            ->get(route('student-profiles.index', ['student_group_id' => $group->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentProfile/Index')
                ->where('filters.student_group_id', (string) $group->id)
                ->has('availableGroups', 1)
                ->has('profileStatusOptions', 5)
                ->has('students.data', 1)
                ->where('students.data.0.fullName', 'Aidana Sadykova')
                ->where('students.data.0.profileStatus', StudentProfile::STATUS_DRAFT)
                ->where('students.data.0.gpa', 3.5)
            );
    }

    public function test_advisor_can_create_student_profile_for_student(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $group = StudentGroup::query()->create([
            'curator_id' => $advisor->id,
            'faculty' => StudentProfileOptions::facultyNames()[3],
            'name' => 'IS-102',
        ]);

        $this->actingAs($advisor)
            ->post(route('student-profiles.store'), [
                'name' => 'Ivan Ivanov',
                'email' => 'ivan@example.com',
                'password' => 'password123',
                'full_name' => 'Ivan Ivanov',
                'faculty' => StudentProfileOptions::facultyNames()[3],
                'student_group_id' => $group->id,
                'group_name' => 'IS-102',
                'specialty' => 'Information systems',
                'course' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $student = User::query()->where('email', 'ivan@example.com')->firstOrFail();
        $student->loadMissing('role');

        $this->assertSame(Role::STUDENT, $student->role?->slug);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'full_name' => 'Ivan Ivanov',
            'group_name' => 'IS-102',
        ]);
    }

    public function test_advisor_can_edit_selected_student_profile(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $student = $this->userWithRole(Role::STUDENT, 'Student');
        $group = StudentGroup::query()->create([
            'curator_id' => $advisor->id,
            'faculty' => StudentProfileOptions::facultyNames()[5],
            'name' => 'EK-201',
        ]);

        $this->actingAs($advisor)
            ->post(route('student-profiles.update', $student), [
                'full_name' => 'Petr Petrov',
                'faculty' => StudentProfileOptions::facultyNames()[5],
                'student_group_id' => $group->id,
                'group_name' => 'EK-201',
                'course' => 2,
                'disability_group' => '2',
                'is_orphan' => true,
                'legal_representative' => 'Advisor Representative',
                'benefits' => ['asp'],
                'social_support_need_status' => 'needs',
                'social_support_need_details' => 'Needs food support',
                'education_language' => 'ru',
                'gpa' => 3.2,
                'academic_debt' => 'No',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'full_name' => 'Petr Petrov',
            'group_name' => 'EK-201',
        ]);
        $profile = StudentProfile::query()->where('user_id', $student->id)->firstOrFail();

        $this->assertSame('2', $profile->disability_group);
        $this->assertTrue($profile->is_orphan);
        $this->assertSame('Advisor Representative', $profile->legal_representative);
        $this->assertSame(['asp'], $profile->benefits);
        $this->assertSame('needs', $profile->social_support_need_status);
        $this->assertSame('Needs food support', $profile->social_support_need_details);
        $this->assertDatabaseHas('academic_profiles', [
            'user_id' => $student->id,
            'gpa' => 3.2,
        ]);
    }

    public function test_advisor_can_mark_student_as_departed(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $student = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($advisor)
            ->post(route('student-profiles.update', $student), [
                'full_name' => 'Departed Student',
                'student_status' => StudentProfile::STUDENT_STATUS_DEPARTED,
                'departure_reason' => 'expelled',
                'departure_reason_other' => '',
                'departed_at' => '2026-06-01',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = StudentProfile::query()->where('user_id', $student->id)->firstOrFail();

        $this->assertSame(StudentProfile::STUDENT_STATUS_DEPARTED, $profile->student_status);
        $this->assertSame('expelled', $profile->departure_reason);
        $this->assertSame('2026-06-01', $profile->departed_at->format('Y-m-d'));
    }

    public function test_advisor_cannot_save_student_profile_with_unknown_group(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $student = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($advisor)
            ->post(route('student-profiles.update', $student), [
                'full_name' => 'Unknown Group Student',
                'faculty' => StudentProfileOptions::facultyNames()[3],
                'group_name' => 'UNKNOWN-1',
                'course' => 2,
            ])
            ->assertSessionHasErrors('group_name');
    }

    public function test_advisor_can_filter_student_profiles_by_status(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $submittedStudent = $this->userWithRole(Role::STUDENT, 'Student', [
            'name' => 'Submitted Student',
        ]);
        $draftStudent = $this->userWithRole(Role::STUDENT, 'Student', [
            'name' => 'Draft Student',
        ]);

        StudentProfile::query()->create([
            'user_id' => $submittedStudent->id,
            'profile_status' => StudentProfile::STATUS_SUBMITTED,
            'full_name' => 'Submitted Student',
        ]);
        StudentProfile::query()->create([
            'user_id' => $draftStudent->id,
            'profile_status' => StudentProfile::STATUS_DRAFT,
            'full_name' => 'Draft Student',
        ]);

        $this->actingAs($advisor)
            ->get(route('student-profiles.index', [
                'profile_status' => StudentProfile::STATUS_SUBMITTED,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.profile_status', StudentProfile::STATUS_SUBMITTED)
                ->has('students.data', 1)
                ->where('students.data.0.fullName', 'Submitted Student')
            );
    }

    public function test_advisor_can_review_student_profile_status(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $student = $this->userWithRole(Role::STUDENT, 'Student');
        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'profile_status' => StudentProfile::STATUS_SUBMITTED,
            'full_name' => 'Submitted Student',
        ]);

        $this->actingAs($advisor)
            ->post(route('student-profiles.status.update', $student), [
                'profile_status' => StudentProfile::STATUS_VERIFIED,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile->refresh();

        $this->assertSame(StudentProfile::STATUS_VERIFIED, $profile->profile_status);
        $this->assertSame($advisor->id, $profile->reviewed_by_id);
        $this->assertNotNull($profile->verified_at);

        $this->actingAs($advisor)
            ->post(route('student-profiles.status.update', $student), [
                'profile_status' => StudentProfile::STATUS_NEEDS_REVISION,
                'revision_comment' => 'Update contact details.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile->refresh();

        $this->assertSame(StudentProfile::STATUS_NEEDS_REVISION, $profile->profile_status);
        $this->assertSame($advisor->id, $profile->reviewed_by_id);
        $this->assertNull($profile->verified_at);
        $this->assertSame('Update contact details.', $profile->revision_comment);
    }

    public function test_advisor_can_review_social_and_academic_blocks(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $student = $this->userWithRole(Role::STUDENT, 'Student');
        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'profile_status' => StudentProfile::STATUS_SUBMITTED,
            'full_name' => 'Submitted Student',
            'social_review_status' => StudentProfile::REVIEW_PENDING,
        ]);
        $academic = AcademicProfile::query()->create([
            'user_id' => $student->id,
            'gpa' => 3.2,
            'academic_review_status' => AcademicProfile::REVIEW_PENDING,
        ]);

        $this->actingAs($advisor)
            ->post(route('student-profiles.review-block.update', $student), [
                'block' => 'social',
                'review_status' => StudentProfile::REVIEW_VERIFIED,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile->refresh();

        $this->assertSame(StudentProfile::REVIEW_VERIFIED, $profile->social_review_status);
        $this->assertSame($advisor->id, $profile->social_reviewed_by_id);
        $this->assertNotNull($profile->social_reviewed_at);

        $this->actingAs($advisor)
            ->post(route('student-profiles.review-block.update', $student), [
                'block' => 'academic',
                'review_status' => AcademicProfile::REVIEW_NEEDS_REVISION,
                'review_comment' => 'Check final grades.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $academic->refresh();

        $this->assertSame(AcademicProfile::REVIEW_NEEDS_REVISION, $academic->academic_review_status);
        $this->assertSame($advisor->id, $academic->academic_reviewed_by_id);
        $this->assertNotNull($academic->academic_reviewed_at);
        $this->assertSame('Check final grades.', $academic->academic_review_comment);
    }

    public function test_advisor_can_add_achievement_to_selected_student(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');
        $student = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($advisor)
            ->post(route('student-profiles.achievements.store', $student), [
                'activity_type' => 'contest',
                'title' => 'Project contest',
                'level' => 'city',
                'result' => 'participant',
                'document' => UploadedFile::fake()->create('project.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $achievement = ExtracurricularAchievement::query()->where('user_id', $student->id)->firstOrFail();

        $this->assertSame('Project contest', $achievement->title);
        Storage::disk('public')->assertExists($achievement->document_path);
    }

    public function test_advisor_cannot_open_own_student_profile_route(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Advisor');

        $this->actingAs($advisor)
            ->get(route('student-profile.edit'))
            ->assertForbidden();
    }

    public function test_curator_cannot_open_own_student_profile_route(): void
    {
        $this->seed(RoleSeeder::class);

        $curator = $this->userWithRole(Role::CURATOR, 'Curator');

        $this->actingAs($curator)
            ->get(route('student-profile.edit'))
            ->assertForbidden();
    }

    public function test_student_cannot_manage_student_profiles(): void
    {
        $this->seed(RoleSeeder::class);

        $student = $this->userWithRole(Role::STUDENT, 'Student');

        $this->actingAs($student)
            ->get(route('student-profiles.index'))
            ->assertForbidden();
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
