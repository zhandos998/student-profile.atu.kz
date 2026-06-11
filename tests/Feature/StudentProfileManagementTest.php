<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
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

        $advisor = $this->userWithRole(Role::ADVISOR, 'Эдвайзер');
        $student = $this->userWithRole(Role::STUDENT, 'Студент', [
            'name' => 'Айдана Садыкова',
            'email' => 'aidana@example.com',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Айдана Садыкова',
            'faculty' => 'Факультет информационных технологий',
            'group_name' => 'ИС-101',
            'course' => 2,
        ]);

        AcademicProfile::query()->create([
            'user_id' => $student->id,
            'gpa' => 3.5,
        ]);

        $this->actingAs($advisor)
            ->get(route('student-profiles.index', ['group_name' => 'ИС-101']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentProfile/Index')
                ->where('filters.group_name', 'ИС-101')
                ->has('students.data', 1)
                ->where('students.data.0.fullName', 'Айдана Садыкова')
                ->where('students.data.0.gpa', 3.5)
            );
    }

    public function test_advisor_can_create_student_profile_for_student(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Эдвайзер');

        $this->actingAs($advisor)
            ->post(route('student-profiles.store'), [
                'name' => 'Иван Иванов',
                'email' => 'ivan@example.com',
                'password' => 'password123',
                'full_name' => 'Иван Иванов',
                'faculty' => 'Факультет информационных технологий',
                'group_name' => 'ИС-102',
                'specialty' => 'Информационные системы',
                'course' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $student = User::query()->where('email', 'ivan@example.com')->firstOrFail();
        $student->loadMissing('role');

        $this->assertSame(Role::STUDENT, $student->role?->slug);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'full_name' => 'Иван Иванов',
            'group_name' => 'ИС-102',
        ]);
    }

    public function test_advisor_can_edit_selected_student_profile(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Эдвайзер');
        $student = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($advisor)
            ->post(route('student-profiles.update', $student), [
                'full_name' => 'Петров Петр',
                'faculty' => 'Факультет экономики и бизнеса',
                'group_name' => 'ЭК-201',
                'course' => 2,
                'education_language' => 'ru',
                'gpa' => 3.2,
                'academic_debt' => 'Нет',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'full_name' => 'Петров Петр',
            'group_name' => 'ЭК-201',
        ]);
        $this->assertDatabaseHas('academic_profiles', [
            'user_id' => $student->id,
            'gpa' => 3.2,
        ]);
    }

    public function test_advisor_can_add_achievement_to_selected_student(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Эдвайзер');
        $student = $this->userWithRole(Role::STUDENT, 'Студент');

        $this->actingAs($advisor)
            ->post(route('student-profiles.achievements.store', $student), [
                'activity_type' => 'contest',
                'title' => 'Конкурс проектов',
                'level' => 'city',
                'result' => 'participant',
                'document' => UploadedFile::fake()->create('project.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $achievement = ExtracurricularAchievement::query()->where('user_id', $student->id)->firstOrFail();

        $this->assertSame('Конкурс проектов', $achievement->title);
        Storage::disk('public')->assertExists($achievement->document_path);
    }

    public function test_advisor_cannot_open_own_student_profile_route(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = $this->userWithRole(Role::ADVISOR, 'Эдвайзер');

        $this->actingAs($advisor)
            ->get(route('student-profile.edit'))
            ->assertForbidden();
    }

    public function test_student_cannot_manage_student_profiles(): void
    {
        $this->seed(RoleSeeder::class);

        $student = $this->userWithRole(Role::STUDENT, 'Студент');

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
