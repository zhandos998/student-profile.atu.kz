<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentServiceRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceIntegrationAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_only_dit_staff_see_unresolved_errors_including_legacy_requests(): void
    {
        $student = $this->user(Role::STUDENT);
        $failed = $this->request($student, ['hikvision_error' => 'Hikvision не принял запрос (HTTP 502).']);
        $this->request($student, ['status' => 'in_progress']);
        $this->request($student, ['status' => 'completed']);
        $this->request($student, ['status' => 'rejected']);
        $this->request($student, ['hikvision_synced_at' => now()]);
        $this->request($student, ['service_type' => 'id_card']);

        foreach ([Role::ADMINISTRATOR_DIT, Role::SERVICE_OPERATOR] as $role) {
            $this->actingAs($this->user($role))->get(route('student-services.index'))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('serviceAlerts.count', 2)
                ->has('serviceAlerts.items', 2)
                ->where('serviceAlerts.items.1.error', $failed->hikvision_error)
                ->where('serviceAlerts.items.1.url', route('student-services.show', $failed)));
        }

        foreach ([$student, $this->user(Role::CURATOR), $this->user(Role::ADMINISTRATION)] as $user) {
            $this->actingAs($user)->get(route('dashboard'))
                ->assertOk()->assertInertia(fn (Assert $page) => $page->where('serviceAlerts', null));
        }

        $this->actingAs($student)->get(route('student-services.show', $failed))
            ->assertInertia(fn (Assert $page) => $page->where('serviceRequest.hikvision_error', null));
    }

    public function test_alert_list_is_bounded_and_processed_requests_disappear(): void
    {
        $staff = $this->user(Role::SERVICE_OPERATOR);
        $student = $this->user(Role::STUDENT);
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->request($student)->id;
        }
        $this->actingAs($staff)->get(route('student-services.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceAlerts.count', 5)
                ->has('serviceAlerts.items', 3)
                ->where('serviceAlerts.items.0.id', $ids[4]));

        $this->patch(route('student-services.update', $ids[4]), ['status' => 'completed'])
            ->assertSessionHasNoErrors();
        $this->get(route('student-services.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceAlerts.count', 4)
                ->where('serviceAlerts.items.0.id', $ids[3]));
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->firstOrFail()->id]);
    }

    private function request(User $student, array $attributes = []): StudentServiceRequest
    {
        return StudentServiceRequest::create([
            'student_id' => $student->id,
            'service_type' => 'face_id',
            'status' => 'pending',
            ...$attributes,
        ]);
    }
}
