<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
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
                ->has('reports', 4)
                ->where('reports.0.type', 'student')
                ->where('reports.1.type', 'group')
                ->where('reports.2.type', 'course')
                ->where('reports.3.type', 'faculty')
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
