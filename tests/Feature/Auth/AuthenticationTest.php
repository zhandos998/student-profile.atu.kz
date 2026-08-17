<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\StudentProfileOptions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_platonus_login_screen_can_be_rendered(): void
    {
        $this->get('/login/platonus')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/PlatonusLogin'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_using_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '+7 700 000 00 00',
            'phone_normalized' => '77000000000',
        ]);

        $response = $this->post('/login', [
            'email' => '8 700 000 00 00',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_using_platonus_login(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'services.platonus.verify_url' => 'https://hub.atu.kz/api/v1/students/verify',
            'services.platonus.api_key' => 'test-key',
        ]);

        $faculty = StudentProfileOptions::facultyNames()[3];
        $group = StudentGroup::query()->create([
            'faculty' => $faculty,
            'name' => 'ИС-101',
        ]);

        Http::fake([
            'https://hub.atu.kz/api/v1/students/verify' => Http::response([
                'authenticated' => true,
                'student' => [
                    'iin' => '980915300671',
                ],
            ]),
            'https://hub.atu.kz/api/v1/hub/student_full*' => Http::response([
                'lastname' => 'Дәулетов',
                'firstname' => 'Рауан',
                'patronymic' => 'Ерланұлы',
                'iin' => '980915300671',
                'birth_date' => '1998-09-15',
                'sex' => [
                    'ru' => 'мужской',
                    'kz' => 'ер',
                ],
                'nationality' => [
                    'ru' => 'казах',
                    'kz' => 'қазақ',
                ],
                'citizenship' => [
                    'ru' => 'Гражданин Республики Казахстан',
                    'kz' => 'Қазақстан Республикасының азаматы',
                ],
                'contacts' => [
                    'phone' => '+7 701 111 22 33',
                    'mobile' => '+7 701 111 22 33',
                    'email' => 'daulet.rauan@atu.kz',
                ],
                'address' => [
                    'living_address' => 'Общежитие АТУ',
                    'registration_address' => 'г. Алматы',
                ],
                'education' => [
                    'faculty_name_ru' => $faculty,
                    'speciality_code' => 'B057',
                    'speciality_name_ru' => 'Информационные технологии',
                    'group_name' => 'ИС-101',
                    'course' => 2,
                    'study_form_ru' => 'очная',
                ],
            ]),
        ]);

        $response = $this->post('/login', [
            'auth_type' => 'platonus',
            'login' => '1Daulet_Rauan',
            'password' => 'plain_password',
        ]);

        $user = User::query()->where('platonus_login', '1daulet_rauan')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame(Role::STUDENT, $user->role?->slug);
        $this->assertSame('daulet.rauan@atu.kz', $user->email);
        $this->assertSame('+7 701 111 22 33', $user->phone);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $user->id,
            'student_group_id' => $group->id,
            'full_name' => 'Дәулетов Рауан Ерланұлы',
            'iin' => '980915300671',
            'birth_date' => '1998-09-15',
            'gender' => 'male',
            'nationality' => 'казах',
            'citizenship' => 'kazakhstan_citizen',
            'faculty' => $faculty,
            'group_name' => 'ИС-101',
            'specialty' => 'B057 - Информационные технологии',
            'course' => 2,
            'study_form' => 'очная',
            'stay_address' => 'Общежитие АТУ',
            'residence_address' => 'г. Алматы',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hub.atu.kz/api/v1/students/verify'
            && $request->hasHeader('X-API-Key', 'test-key')
            && $request['login'] === '1Daulet_Rauan'
            && $request['password'] === 'plain_password');

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://hub.atu.kz/api/v1/hub/student_full')
            && str_contains($request->url(), 'iin=980915300671')
            && $request->hasHeader('X-API-Key', 'test-key'));
    }

    public function test_platonus_login_updates_existing_student_profile_iin(): void
    {
        $this->seed(RoleSeeder::class);

        config([
            'services.platonus.verify_url' => 'https://hub.atu.kz/api/v1/students/verify',
            'services.platonus.api_key' => 'test-key',
        ]);

        $role = Role::query()->where('slug', Role::STUDENT)->firstOrFail();
        $faculty = StudentProfileOptions::facultyNames()[3];
        $group = StudentGroup::query()->create([
            'faculty' => $faculty,
            'name' => 'IS-101',
        ]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'position' => 'Student',
            'platonus_login' => '1daulet_rauan',
        ]);

        StudentProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Old Name',
            'iin' => '000000000000',
            'group_name' => 'OLD',
            'course' => 1,
        ]);

        Http::fake([
            'https://hub.atu.kz/api/v1/students/verify' => Http::response([
                'authenticated' => true,
                'student' => [
                    'iin' => '980915300671',
                ],
            ]),
            'https://hub.atu.kz/api/v1/hub/student_full*' => Http::response([
                'lastname' => 'New',
                'firstname' => 'Student',
                'iin' => '980915300671',
                'education' => [
                    'faculty_name_ru' => $faculty,
                    'group_name' => 'IS-101',
                    'course' => 4,
                ],
            ]),
        ]);

        $this->post('/login', [
            'auth_type' => 'platonus',
            'login' => '1Daulet_Rauan',
            'password' => 'plain_password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $profile = $user->studentProfile()->firstOrFail();

        $this->assertSame('980915300671', $profile->iin);
        $this->assertSame('New Student', $profile->full_name);
        $this->assertSame($group->id, $profile->student_group_id);
        $this->assertSame('IS-101', $profile->group_name);
        $this->assertSame(4, $profile->course);
    }

    public function test_platonus_login_rejects_invalid_credentials(): void
    {
        config([
            'services.platonus.verify_url' => 'https://hub.atu.kz/api/v1/students/verify',
            'services.platonus.api_key' => 'test-key',
        ]);

        Http::fake([
            'https://hub.atu.kz/api/v1/students/verify' => Http::response([
                'authenticated' => false,
                'detail' => 'User not found',
            ]),
        ]);

        $response = $this->from('/login/platonus')->post('/login', [
            'auth_type' => 'platonus',
            'login' => 'wrong_login',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login/platonus');
        $response->assertSessionHasErrors(['login' => 'User not found']);
    }

    public function test_platonus_login_page_keeps_login_after_failed_login(): void
    {
        config([
            'services.platonus.verify_url' => 'https://hub.atu.kz/api/v1/students/verify',
            'services.platonus.api_key' => 'test-key',
        ]);

        Http::fake([
            'https://hub.atu.kz/api/v1/students/verify' => Http::response([
                'authenticated' => false,
                'detail' => 'User not found',
            ]),
        ]);

        $this->from('/login/platonus')->post('/login', [
            'auth_type' => 'platonus',
            'login' => 'wrong_login',
            'password' => 'wrong-password',
        ])->assertRedirect('/login/platonus');

        $this->get('/login/platonus')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/PlatonusLogin')
                ->where('oldInput.login', 'wrong_login'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Указанные данные не совпадают с нашими записями.']);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
