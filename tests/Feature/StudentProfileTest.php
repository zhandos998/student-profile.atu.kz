<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
use App\Models\PortfolioItem;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_page_can_be_rendered_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('student-profile.edit'));

        $response->assertOk();
    }

    public function test_user_can_save_student_card_and_academic_profile(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('student-profile.update'), [
                'full_name' => 'Иванов Иван Иванович',
                'birth_date' => '2005-03-15',
                'study_form' => 'Очная',
                'nationality' => 'Казах',
                'photo' => UploadedFile::fake()->image('student.png'),
                'iin' => '123456789012',
                'identity_document_number' => 'ID1234567',
                'identity_card' => UploadedFile::fake()->create('id-card.pdf', 100, 'application/pdf'),
                'gender' => 'male',
                'faculty' => 'Факультет пищевых технологий',
                'group_name' => 'ТПП-23-1',
                'specialty' => 'Технология продовольственных продуктов',
                'course' => 2,
                'admission_year' => 2023,
                'marital_status' => 'single_male',
                'disability_group' => '2',
                'disabled_parent_group' => '1',
                'disabled_sibling_group' => '3',
                'is_orphan' => true,
                'legal_representative' => 'Иванов Петр',
                'is_half_orphan' => true,
                'half_orphan_type' => 'single_parent',
                'is_incomplete_family' => true,
                'is_large_family' => true,
                'is_low_income' => true,
                'benefits' => ['allowance', 'pension'],
                'stay_address' => 'г. Алматы, общежитие 1',
                'residence_address' => 'г. Алматы',
                'contact_details' => '+7 700 000 00 00',
                'dormitory_details' => 'Общежитие 1, комната 101',
                'education_language' => 'ru',
                'gpa' => 3.45,
                'final_grades' => 'A, B+',
                'current_performance' => 'Стабильная',
                'academic_debt' => 'Нет',
                'grade_dynamics' => 'Рост',
                'group_comparison' => 'Выше среднего',
                'success_forecast' => 'Высокий',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $profile = StudentProfile::query()->where('user_id', $user->id)->firstOrFail();
        $academicProfile = AcademicProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Иванов Иван Иванович', $profile->full_name);
        $this->assertSame('Факультет пищевых технологий', $profile->faculty);
        $this->assertSame('2', $profile->disability_group);
        $this->assertSame('1', $profile->disabled_parent_group);
        $this->assertSame('3', $profile->disabled_sibling_group);
        $this->assertTrue($profile->is_orphan);
        $this->assertSame('Иванов Петр', $profile->legal_representative);
        $this->assertTrue($profile->is_half_orphan);
        $this->assertSame('single_parent', $profile->half_orphan_type);
        $this->assertTrue($profile->is_incomplete_family);
        $this->assertTrue($profile->is_large_family);
        $this->assertTrue($profile->is_low_income);
        $this->assertSame(['allowance', 'pension'], $profile->benefits);
        $this->assertSame('3.45', $academicProfile->gpa);
        Storage::disk('public')->assertExists($profile->photo_path);
        Storage::disk('public')->assertExists($profile->identity_card_path);
    }

    public function test_user_can_add_and_delete_own_achievement(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('student-profile.achievements.store'), [
                'activity_type' => 'olympiad',
                'title' => 'Республиканская олимпиада',
                'level' => 'republican',
                'result' => 'first_place',
                'description' => 'Победа в профильной олимпиаде',
                'document' => UploadedFile::fake()->create('diploma.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $achievement = ExtracurricularAchievement::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Республиканская олимпиада', $achievement->title);
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

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('student-profile.portfolio.store'), [
                'item_type' => 'certificate',
                'title' => 'Сертификат волонтера',
                'file' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $portfolioItem = PortfolioItem::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Сертификат волонтера', $portfolioItem->title);
        Storage::disk('public')->assertExists($portfolioItem->file_path);

        $this->actingAs($user)
            ->delete(route('student-profile.portfolio.destroy', $portfolioItem))
            ->assertRedirect();

        $this->assertDatabaseMissing('portfolio_items', [
            'id' => $portfolioItem->id,
        ]);
        Storage::disk('public')->assertMissing($portfolioItem->file_path);
    }
}
