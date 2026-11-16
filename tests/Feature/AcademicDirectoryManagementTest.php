<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AcademicDirectory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademicDirectoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_only_dit_admin_can_manage_the_directory(): void
    {
        $student = User::factory()->create(['role_id' => Role::query()->where('slug', Role::STUDENT)->value('id')]);
        $specialty = Specialty::query()->create(['code' => '6B06102', 'value' => '6B06102 - Test', 'name_ru' => 'Test']);

        $this->actingAs($student)->get(route('academic-directory.index'))->assertForbidden();
        $this->actingAs($student)->patch(route('academic-directory.specialties.visibility', $specialty))->assertForbidden();
        $this->assertTrue($specialty->fresh()->is_active);
    }

    public function test_admin_can_hide_and_restore_specialty_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role_id' => Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->value('id')]);
        $specialty = Specialty::query()->create(['code' => '6B06102', 'value' => '6B06102 - Test', 'name_ru' => 'Test']);

        $this->actingAs($admin)->get(route('academic-directory.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('AcademicDirectory/Index'));
        $this->actingAs($admin)->patch(route('academic-directory.specialties.visibility', $specialty))->assertRedirect();
        $this->assertFalse($specialty->fresh()->is_active);
        $this->assertSame([], app(AcademicDirectory::class)->options('ru')['specialties']);

        $this->actingAs($admin)->patch(route('academic-directory.specialties.visibility', $specialty))->assertRedirect();
        $this->assertTrue($specialty->fresh()->is_active);
        $this->assertCount(1, app(AcademicDirectory::class)->options('ru')['specialties']);
    }

    public function test_manual_edit_is_not_overwritten_by_platonus_sync(): void
    {
        $admin = User::factory()->create(['role_id' => Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->value('id')]);
        $specialty = Specialty::query()->create(['code' => '6B06102', 'value' => '6B06102 - Old', 'name_ru' => 'Old']);

        $this->actingAs($admin)->patch(route('academic-directory.specialties.update', $specialty), [
            'name_ru' => 'Correct name',
            'name_kk' => 'Kazakh name',
        ])->assertRedirect();

        app(AcademicDirectory::class)->syncFromPlatonus([
            'speciality_code' => '6B06102',
            'speciality_name_ru' => 'Old API name',
            'speciality_name_kz' => 'Old API Kazakh name',
        ], null);

        $this->assertSame('Correct name', $specialty->fresh()->name_ru);
        $this->assertSame('Kazakh name', $specialty->fresh()->name_kk);
        $this->assertSame('6B06102 - Old', $specialty->fresh()->value);
    }

    public function test_admin_can_add_specialty_and_edit_faculty_translation(): void
    {
        $admin = User::factory()->create(['role_id' => Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->value('id')]);
        $faculty = Faculty::query()->firstOrFail();

        $this->actingAs($admin)->post(route('academic-directory.specialties.store'), [
            'faculty_id' => $faculty->id,
            'code' => '6b06102',
            'name_ru' => 'Information systems',
            'name_kk' => 'Ақпараттық жүйелер',
        ])->assertRedirect();

        $specialty = Specialty::query()->firstOrFail();
        $this->assertSame('6B06102', $specialty->code);
        $this->assertSame($faculty->id, $specialty->faculty_id);
        $this->assertTrue($specialty->is_manual);

        $this->actingAs($admin)->patch(route('academic-directory.faculties.update', $faculty), [
            'name_kk' => 'Жаңа атау',
            'name_en' => 'New name',
        ])->assertRedirect();
        $this->assertSame('Жаңа атау', $faculty->fresh()->name_kk);
    }

    public function test_hidden_filter_only_lists_hidden_specialties(): void
    {
        $admin = User::factory()->create(['role_id' => Role::query()->where('slug', Role::ADMINISTRATOR_DIT)->value('id')]);
        Specialty::query()->create(['value' => 'Visible', 'name_ru' => 'Visible']);
        Specialty::query()->create(['value' => 'Hidden', 'name_ru' => 'Hidden', 'is_active' => false]);

        $this->actingAs($admin)->get(route('academic-directory.index', ['status' => 'hidden']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AcademicDirectory/Index')
                ->has('specialties.data', 1)
                ->where('specialties.data.0.name_ru', 'Hidden')
            );
    }
}
