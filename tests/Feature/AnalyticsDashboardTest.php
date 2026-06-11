<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
use App\Models\HealthPassport;
use App\Models\PsychologicalProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_can_view_analytics_dashboard(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Декан',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertOk();
    }

    public function test_dit_administrator_can_view_analytics_dashboard(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Администратор ДИТ',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertOk();
    }

    public function test_analytics_dashboard_has_required_risk_group_categories(): void
    {
        $this->seed(RoleSeeder::class);

        $ditRole = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();

        $administrator = User::factory()->create([
            'role_id' => $ditRole->id,
            'position' => 'Администратор ДИТ',
        ]);

        $academicRiskStudent = User::factory()->create(['role_id' => $studentRole->id]);
        AcademicProfile::query()->create([
            'user_id' => $academicRiskStudent->id,
            'gpa' => 1.8,
        ]);

        $socialRiskStudent = User::factory()->create(['role_id' => $studentRole->id]);
        StudentProfile::query()->create([
            'user_id' => $socialRiskStudent->id,
            'full_name' => 'Студент социального риска',
            'is_low_income' => true,
        ]);

        $psychologicalRiskStudent = User::factory()->create(['role_id' => $studentRole->id]);
        PsychologicalProfile::query()->create([
            'user_id' => $psychologicalRiskStudent->id,
            'testing_results' => 'Есть психологические риски',
        ]);

        $medicalRiskStudent = User::factory()->create(['role_id' => $studentRole->id]);
        HealthPassport::query()->create([
            'user_id' => $medicalRiskStudent->id,
            'diagnosis' => 'Медицинский риск',
        ]);

        $this->actingAs($administrator)
            ->get(route('analytics-dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AnalyticsDashboard/Index')
                ->has('riskGroups', 4)
                ->where('riskGroups.0.label', 'Академические риски')
                ->where('riskGroups.0.count', 1)
                ->where('riskGroups.1.label', 'Социальные риски')
                ->where('riskGroups.1.count', 1)
                ->where('riskGroups.2.label', 'Психологические риски')
                ->where('riskGroups.2.count', 1)
                ->where('riskGroups.3.label', 'Медицинские риски')
                ->where('riskGroups.3.count', 1)
            );
    }

    public function test_analytics_dashboard_has_notification_channels(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Администратор ДИТ',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AnalyticsDashboard/Index')
                ->has('notificationChannels', 4)
                ->where('notificationChannels.0.name', 'Web')
                ->where('notificationChannels.1.name', 'Email')
                ->where('notificationChannels.2.name', 'Push')
                ->where('notificationChannels.3.name', 'WhatsApp')
            );
    }

    public function test_analytics_dashboard_has_notification_events(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Администратор ДИТ',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AnalyticsDashboard/Index')
                ->has('notificationEvents', 3)
                ->where('notificationEvents.0.name', 'Снижение успеваемости')
                ->where('notificationEvents.1.name', 'Новые достижения')
                ->where('notificationEvents.2.name', 'Необходимость обновления данных')
            );
    }

    public function test_analytics_dashboard_has_reports(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Администратор ДИТ',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AnalyticsDashboard/Index')
                ->has('reports', 8)
                ->where('reports.0.type', 'student')
                ->where('reports.1.type', 'group')
                ->where('reports.2.type', 'course')
                ->where('reports.3.type', 'faculty')
                ->where('reports.4.type', 'academic-risks')
                ->where('reports.5.type', 'social-risks')
                ->where('reports.6.type', 'psychological-risks')
                ->where('reports.7.type', 'medical-risks')
            );
    }

    public function test_analytics_dashboard_has_required_integrations(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Администратор ДИТ',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AnalyticsDashboard/Index')
                ->has('integrations', 5)
                ->where('integrations.0.name', 'LMS')
                ->where('integrations.1.name', 'Электронный журнал')
                ->where('integrations.2.name', 'Платформа тестирования')
                ->where('integrations.3.name', 'База студентов')
                ->where('integrations.4.name', 'Google Workspace / Microsoft 365')
            );
    }

    public function test_dit_administrator_can_export_student_report(): void
    {
        $this->seed(RoleSeeder::class);

        $ditRole = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();

        $administrator = User::factory()->create([
            'role_id' => $ditRole->id,
            'position' => 'Администратор ДИТ',
        ]);

        $student = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Иван Иванов',
            'email' => 'ivan@example.com',
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Иван Иванов',
            'faculty' => 'Факультет информационных технологий',
            'group_name' => 'ИС-101',
            'course' => 1,
        ]);

        AcademicProfile::query()->create([
            'user_id' => $student->id,
            'gpa' => 3.45,
            'academic_debt' => 'Нет',
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('analytics-dashboard.reports.export', ['type' => 'student']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertHeader('Content-Disposition');

        $this->assertStringContainsString('Иван Иванов', $response->getContent());
        $this->assertStringContainsString('ИС-101', $response->getContent());
    }

    public function test_dit_administrator_can_export_academic_risks_report(): void
    {
        $this->seed(RoleSeeder::class);

        $ditRole = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();

        $administrator = User::factory()->create([
            'role_id' => $ditRole->id,
            'position' => 'Администратор ДИТ',
        ]);

        $student = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Risk Student',
            'email' => 'risk@example.com',
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Risk Student',
            'faculty' => 'Факультет информационных технологий',
            'group_name' => 'IS-101',
            'course' => 1,
        ]);

        AcademicProfile::query()->create([
            'user_id' => $student->id,
            'gpa' => 1.75,
            'academic_debt' => 'Calculus',
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('analytics-dashboard.reports.export', ['type' => 'academic-risks']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertHeader('Content-Disposition');

        $this->assertStringContainsString('Risk Student', $response->getContent());
        $this->assertStringContainsString('Calculus', $response->getContent());
    }

    public function test_student_cannot_export_analytics_report(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Студент',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.reports.export', ['type' => 'student']))
            ->assertForbidden();
    }

    public function test_student_cannot_view_analytics_dashboard(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Студент',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertForbidden();
    }

    public function test_curator_cannot_view_analytics_dashboard(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::CURATOR)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Куратор',
        ]);

        $this->actingAs($user)
            ->get(route('analytics-dashboard.index'))
            ->assertForbidden();
    }
}
