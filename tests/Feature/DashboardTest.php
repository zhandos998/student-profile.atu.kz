<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_has_student_home_blocks(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $student = User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Айдана Садыкова',
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Айдана Садыкова',
            'birth_date' => '2005-02-10',
            'faculty' => 'Факультет информационных технологий',
            'group_name' => 'ИС-101',
            'course' => 1,
            'specialty' => 'Информационные системы',
            'contact_details' => '+7 700 000 00 00',
            'residence_address' => 'Алматы',
        ]);

        AcademicProfile::query()->create([
            'user_id' => $student->id,
            'education_language' => 'русский',
            'gpa' => 3.25,
            'current_performance' => 'Стабильная',
            'academic_debt' => 'Нет',
            'success_forecast' => 'Положительный',
        ]);

        ExtracurricularAchievement::query()->create([
            'user_id' => $student->id,
            'activity_type' => 'Конкурсы',
            'title' => 'IT конкурс',
            'level' => 'Республиканский',
            'result' => 'Участник',
        ]);

        PortfolioItem::query()->create([
            'user_id' => $student->id,
            'item_type' => 'сертификат',
            'title' => 'React certificate',
            'file_path' => 'portfolio/react.pdf',
            'original_name' => 'react.pdf',
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->where('studentHome.personalInfo.fullName', 'Айдана Садыкова')
                    ->where('studentHome.academic.gpa', 3.25)
                    ->where('studentHome.achievements.count', 1)
                    ->where('studentHome.portfolio.count', 1)
                    ->has('studentHome.recommendations', 6)
                    ->where('studentHome.recommendations.0.title', 'Индивидуальное консультирование')
                    ->where('studentHome.recommendations.1.title', 'Мониторинг академической успеваемости')
                    ->where('studentHome.recommendations.2.title', 'План ментора')
                    ->where('studentHome.recommendations.3.title', 'Социальная поддержка')
                    ->where('studentHome.recommendations.4.title', 'Работа с семьей')
                    ->where('studentHome.recommendations.5.title', 'Контроль проживания в общежитии')
                    ->where('curatorAdvisorHome', null)
            );
    }

    public function test_curator_dashboard_has_curator_advisor_home_blocks(): void
    {
        $this->seed(RoleSeeder::class);

        $curatorRole = Role::query()->where('slug', Role::CURATOR)->firstOrFail();
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();

        $curator = User::factory()->create([
            'role_id' => $curatorRole->id,
            'position' => 'Куратор',
        ]);

        $group = StudentGroup::query()->create([
            'curator_id' => $curator->id,
            'faculty' => 'Факультет информационных технологий',
            'name' => 'ИС-101',
        ]);

        $firstStudent = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Сергей Петров',
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $firstStudent->id,
            'student_group_id' => $group->id,
            'full_name' => 'Сергей Петров',
            'birth_date' => '2004-09-01',
            'faculty' => 'Факультет информационных технологий',
            'group_name' => 'ИС-101',
            'course' => 2,
            'specialty' => 'Информационные системы',
            'is_orphan' => true,
        ]);

        AcademicProfile::query()->create([
            'user_id' => $firstStudent->id,
            'gpa' => 2.1,
            'academic_debt' => 'Математика',
        ]);

        $secondStudent = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Мария Ахметова',
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $secondStudent->id,
            'student_group_id' => $group->id,
            'full_name' => 'Мария Ахметова',
            'birth_date' => '2005-01-12',
            'faculty' => 'Факультет информационных технологий',
            'group_name' => 'ИС-101',
            'course' => 2,
            'specialty' => 'Компьютерная инженерия',
            'contact_details' => '+7 701 000 00 00',
            'residence_address' => 'Алматы',
        ]);

        AcademicProfile::query()->create([
            'user_id' => $secondStudent->id,
            'gpa' => 3.8,
            'academic_debt' => 'Нет',
        ]);

        ExtracurricularAchievement::query()->create([
            'user_id' => $secondStudent->id,
            'activity_type' => 'Олимпиады',
            'title' => 'Олимпиада по программированию',
        ]);

        $this->actingAs($curator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->where('studentHome', null)
                    ->where('curatorAdvisorHome.students.total', 2)
                    ->where('curatorAdvisorHome.analytics.totalStudents', 2)
                    ->where('curatorAdvisorHome.riskGroups.0.label', 'Снижение успеваемости')
                    ->where('curatorAdvisorHome.riskGroups.0.count', 1)
                    ->has('curatorAdvisorHome.socialPassports', 1)
                    ->has('curatorAdvisorHome.riskStudents')
                    ->has('curatorAdvisorHome.notifications', 3)
            );
    }

    public function test_advisor_dashboard_has_curator_advisor_home_blocks(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADVISOR)->firstOrFail();
        $advisor = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Эдвайзер',
        ]);

        $this->actingAs($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->where('studentHome', null)
                    ->where('curatorAdvisorHome.students.total', 0)
                    ->where('curatorAdvisorHome.analytics.totalStudents', 0)
            );
    }

    public function test_group_leader_dashboard_has_group_management_blocks(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::GROUP_LEADER)->firstOrFail();
        $leader = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Староста',
        ]);

        $this->actingAs($leader)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->has('studentHome')
                    ->where('curatorAdvisorHome.students.total', 0)
                    ->where('curatorAdvisorHome.analytics.totalStudents', 0)
            );
    }


    public function test_administration_dashboard_has_administration_home_blocks(): void
    {
        $this->seed(RoleSeeder::class);

        $administrationRole = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();
        $studentRole = Role::query()->where('slug', Role::STUDENT)->firstOrFail();

        $administrator = User::factory()->create([
            'role_id' => $administrationRole->id,
            'position' => 'Декан',
        ]);

        $student = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Данияр Алиев',
            'position' => 'Студент',
        ]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Данияр Алиев',
            'birth_date' => '2004-03-15',
            'faculty' => 'Факультет экономики и бизнеса',
            'group_name' => 'ЭК-201',
            'course' => 2,
            'specialty' => 'Экономика',
            'contact_details' => '+7 702 000 00 00',
            'residence_address' => 'Алматы',
        ]);

        AcademicProfile::query()->create([
            'user_id' => $student->id,
            'gpa' => 3.7,
            'academic_debt' => 'Нет',
        ]);

        ExtracurricularAchievement::query()->create([
            'user_id' => $student->id,
            'activity_type' => 'Проекты',
            'title' => 'Бизнес-проект',
        ]);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->where('studentHome', null)
                    ->where('curatorAdvisorHome', null)
                    ->has('administrationHome.statistics', 4)
                    ->where('administrationHome.statistics.0.label', 'Количество студентов')
                    ->where('administrationHome.statistics.0.value', 1)
                    ->has('administrationHome.ratings', 1)
                    ->has('administrationHome.reports', 8)
                    ->has('administrationHome.monitoring', 5)
                    ->has('administrationHome.responsiblePersons', 4)
                    ->where('administrationHome.responsiblePersons.0.risk', 'Социальные риски')
                    ->where('administrationHome.responsiblePersons.0.responsible', 'Зам.деканы по ВР и кураторы / эдвайзеры')
                    ->where('administrationHome.responsiblePersons.1.risk', 'Академические риски')
                    ->where('administrationHome.responsiblePersons.1.responsible', 'Зам.декана по УР и кураторы / эдвайзеры')
                    ->where('administrationHome.responsiblePersons.2.risk', 'Психологические риски')
                    ->where('administrationHome.responsiblePersons.2.responsible', 'СПП')
                    ->where('administrationHome.responsiblePersons.3.risk', 'Медицинские риски')
                    ->where('administrationHome.responsiblePersons.3.responsible', 'Здравпункт')
            );
    }

    public function test_dit_dashboard_has_administration_home_blocks(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Администратор ДИТ',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->where('studentHome', null)
                    ->where('curatorAdvisorHome', null)
                    ->has('administrationHome.statistics', 4)
                    ->has('administrationHome.reports', 8)
                    ->has('administrationHome.responsiblePersons', 4)
                    ->where('administrationHome.reports.4.type', 'academic-risks')
                    ->where('administrationHome.reports.5.type', 'social-risks')
                    ->where('administrationHome.reports.6.type', 'psychological-risks')
                    ->where('administrationHome.reports.7.type', 'medical-risks')
            );
    }

    public function test_non_student_dashboard_does_not_have_student_home(): void
    {
        $this->seed(RoleSeeder::class);

        $role = Role::query()->where('slug', Role::ADMINISTRATION)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Декан',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->where('studentHome', null)
                    ->where('curatorAdvisorHome', null)
            );
    }
}
