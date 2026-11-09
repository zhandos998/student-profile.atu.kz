<?php

namespace Tests\Feature;

use App\Models\AcademicProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\StudentServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private array $hikvisionResponses = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 12:00:00'));
        $this->seed(RoleSeeder::class);
        config()->set('hikvision.base_url', 'https://hikvision.test');
        config()->set('hikvision.app_key', 'test-key');
        config()->set('hikvision.app_secret', 'test-secret');
        Http::fake(['https://hikvision.test/*' => fn () => array_shift($this->hikvisionResponses)
            ?? Http::response(['code' => 0])]);
    }

    public function test_student_can_submit_both_services_and_only_see_own_requests(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        $other = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);
        $this->ownFaceIdProfile($other, '060515500609');

        $this->actingAs($student)
            ->post(route('student-services.store'), [...$this->faceIdData(), 'student_comment' => 'Новая карта'])
            ->assertSessionHasNoErrors();

        $this->actingAs($other)
            ->post(route('student-services.store'), $this->faceIdData('060515500609'))
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->get(route('student-services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentServices/Index')
                ->where('canSubmit', true)
                ->where('canManage', false)
                ->has('requests.data', 1)
                ->where('requests.data.0.student_comment', 'Новая карта')
                ->has('activeTypes', 0)
            );

        $this->assertDatabaseHas('student_service_requests', [
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::FACE_ID,
            'status' => StudentServiceRequest::COMPLETED,
        ]);
    }

    public function test_face_id_page_prefills_profile_and_saves_private_photo(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        $other = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        StudentProfile::query()->create([
            'user_id' => $student->id,
            'iin' => '060515500608',
            'full_name' => 'Иванов Иван Иванович',
            'faculty' => 'Факультет информационных технологий',
            'course' => 2,
            'education_level' => StudentProfile::EDUCATION_MASTER,
            'admission_year' => 2025,
        ]);

        $this->actingAs($student)
            ->get(route('student-services.face-id.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentServices/FaceId')
                ->where('initial.iin', '060515500608')
                ->where('initial.last_name', 'Иванов')
                ->where('initial.first_name', 'Иван')
                ->where('initial.middle_name', 'Иванович')
                ->where('initial.faculty', 'Факультет информационных технологий')
                ->where('initial.course', 2)
                ->where('initial.education_level', StudentProfile::EDUCATION_MASTER)
                ->where('hasOpenRequest', false)
                ->where('integrationReady', true)
                ->has('faculties', 6)
            );

        $this->actingAs($student)
            ->post(route('student-services.store'), [...$this->faceIdData(), 'education_level' => StudentProfile::EDUCATION_MASTER])
            ->assertRedirect(route('student-services.index'))
            ->assertSessionHasNoErrors();

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame('060515500608', $serviceRequest->iin);
        $this->assertSame('Иванов Иван Иванович', $serviceRequest->full_name);
        $this->assertSame('Иванов', $serviceRequest->last_name);
        $this->assertSame('Иван', $serviceRequest->first_name);
        $this->assertSame('Иванович', $serviceRequest->middle_name);
        $this->assertSame(2, $serviceRequest->course);
        $this->assertSame(StudentProfile::EDUCATION_MASTER, $serviceRequest->education_level);
        $this->assertSame(2025, $serviceRequest->admission_year);
        $this->assertSame('77', $serviceRequest->hikvision_org_index_code);
        $this->assertNull($serviceRequest->receipt_path);
        Storage::disk('local')->assertExists($serviceRequest->photo_path);

        $this->actingAs($student)->get(route('student-services.face-id.create'))
            ->assertInertia(fn (Assert $page) => $page->where('hasOpenRequest', false));
        $this->actingAs($student)->get(route('student-services.show', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceRequest.education_level', StudentProfile::EDUCATION_MASTER)
                ->where('serviceRequest.last_name', 'Иванов')
                ->where('serviceRequest.first_name', 'Иван')
                ->where('serviceRequest.middle_name', 'Иванович')
                ->where('serviceRequest.has_photo', true)
            );
        $this->actingAs($student)->get(route('student-services.download', [$serviceRequest, 'photo']))->assertOk();
        $this->actingAs($other)->get(route('student-services.download', [$serviceRequest, 'photo']))->assertForbidden();
        $this->actingAs($admin)->get(route('student-services.download', [$serviceRequest, 'photo']))->assertOk();
        $this->actingAs($admin)->get(route('student-services.face-id.create'))->assertForbidden();
    }

    public function test_face_id_requires_student_details_and_image(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);

        $this->actingAs($student)
            ->post(route('student-services.store'), ['service_type' => StudentServiceRequest::FACE_ID])
            ->assertSessionHasErrors(['iin', 'last_name', 'first_name', 'faculty', 'course', 'education_level', 'photo']);

        $this->actingAs($student)
            ->post(route('student-services.store'), [
                ...$this->faceIdData(),
                'iin' => '123',
                'faculty' => 'Неизвестный факультет',
                'course' => 9,
                'education_level' => 'unknown',
                'photo' => UploadedFile::fake()->create('photo.pdf', 1, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['iin', 'faculty', 'course', 'education_level', 'photo']);

        $this->assertDatabaseCount('student_service_requests', 0);
    }

    public function test_face_id_cannot_register_another_students_iin(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $student = $this->user(Role::STUDENT);

        $this->actingAs($student)->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasErrors('iin');

        $this->ownFaceIdProfile($student, '060515500609');

        $this->actingAs($student)->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasErrors('iin');

        Http::assertNothingSent();
        $this->assertDatabaseCount('student_service_requests', 0);
    }

    public function test_face_id_patronymic_is_optional(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);

        $this->actingAs($student)
            ->post(route('student-services.store'), [...$this->faceIdData(), 'middle_name' => ''])
            ->assertSessionHasNoErrors();

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame('Иванов Иван', $serviceRequest->full_name);
        $this->assertNull($serviceRequest->middle_name);
    }

    public function test_face_id_is_sent_to_hikvision_before_request_is_completed(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);

        $this->actingAs($student)->post(route('student-services.store'), [
            ...$this->faceIdData(),
            'faculty' => 'Факультет биотехнологии и химических технологий',
            'course' => 1,
            'admission_year' => 1999,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'face-id-synced');

        Http::assertSent(fn ($request) => $request->url() === 'https://hikvision.test/artemis/api/resource/v1/person/single/add'
            && $request['personCode'] === '060515500608'
            && $request['personFamilyName'] === 'Иванов'
            && $request['personGivenName'] === 'Иван'
            && $request['gender'] === 1
            && $request['orgIndexCode'] === '177'
            && base64_decode($request['faces'][0]['faceData'], true) !== false
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('Accept', '*/*')
            && $request->hasHeader('X-Ca-Key', 'test-key')
            && $request->hasHeader('x-ca-signature-headers', 'x-ca-key')
            && $request->hasHeader('X-Requested-With', 'XMLHttpRequest')
            && $request->hasHeader('x-ca-signature', base64_encode(hash_hmac(
                'sha256',
                "POST\n*/*\napplication/json\nx-ca-key:test-key\n/artemis/api/resource/v1/person/single/add",
                'test-secret',
                true,
            ))));

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame(StudentServiceRequest::COMPLETED, $serviceRequest->status);
        $this->assertSame('177', $serviceRequest->hikvision_org_index_code);
        $this->assertSame(2026, $serviceRequest->admission_year);
        $this->assertNotNull($serviceRequest->hikvision_synced_at);
        Storage::disk('local')->assertExists($serviceRequest->photo_path);
    }

    public function test_existing_hikvision_person_has_photo_updated(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->hikvisionResponses = [
            Http::response(['code' => 131], 409),
            Http::response(['code' => 0, 'data' => ['personId' => 'person-123']]),
            Http::response(['code' => 0]),
        ];

        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);
        $this->actingAs($student)->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasNoErrors();

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/person/face/update')
            && $request['personId'] === 'person-123'
            && base64_decode($request['faceData'], true) !== false);
        $this->assertSame(StudentServiceRequest::COMPLETED, StudentServiceRequest::query()->firstOrFail()->status);
    }

    public function test_face_id_api_failure_queues_request_and_keeps_photo(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->hikvisionResponses = [Http::response(['code' => 400])];

        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);
        $this->actingAs($student)->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'face-id-pending');

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame(StudentServiceRequest::PENDING, $serviceRequest->status);
        $this->assertNull($serviceRequest->hikvision_synced_at);
        Storage::disk('local')->assertExists($serviceRequest->photo_path);

        $this->actingAs($student)->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasErrors('service_type');
        $this->assertDatabaseCount('student_service_requests', 1);
        Http::assertSentCount(1);
    }

    public function test_face_id_connection_failure_queues_request(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->hikvisionResponses = [Http::failedConnection()];

        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);

        $this->actingAs($student)->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'face-id-pending');

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame(StudentServiceRequest::PENDING, $serviceRequest->status);
        Storage::disk('local')->assertExists($serviceRequest->photo_path);
    }

    public function test_face_id_queues_unmapped_admission_year_for_manual_processing(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->travelTo(CarbonImmutable::parse('2027-09-01 12:00:00'));

        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);
        $this->actingAs($student)->post(route('student-services.store'), [
            ...$this->faceIdData(),
            'course' => 1,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'face-id-pending');

        Http::assertNothingSent();
        $this->assertSame(StudentServiceRequest::PENDING, StudentServiceRequest::query()->firstOrFail()->status);
    }

    public function test_face_id_keeps_previous_academic_year_until_september(): void
    {
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2027-01-15 12:00:00'));
        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);

        $this->actingAs($student)->post(route('student-services.store'), [
            ...$this->faceIdData(),
            'course' => 1,
        ])->assertSessionHasNoErrors();

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame(2026, $serviceRequest->admission_year);
        $this->assertSame('173', $serviceRequest->hikvision_org_index_code);
    }

    public function test_doctorate_face_id_uses_doctorate_org_code(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);

        $this->actingAs($student)->post(route('student-services.store'), [
            ...$this->faceIdData(),
            'education_level' => StudentProfile::EDUCATION_DOCTORATE,
            'course' => 1,
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => $request['orgIndexCode'] === '178');
        $this->assertSame('178', StudentServiceRequest::query()->firstOrFail()->hikvision_org_index_code);
    }

    public function test_face_id_is_queued_when_hikvision_is_not_configured(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        config()->set('hikvision.app_secret', null);
        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);

        $this->actingAs($student)->get(route('student-services.face-id.create'))
            ->assertInertia(fn (Assert $page) => $page->where('integrationReady', false));

        $this->actingAs($student)->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'face-id-pending');

        Http::assertNothingSent();
        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame(StudentServiceRequest::PENDING, $serviceRequest->status);
        Storage::disk('local')->assertExists($serviceRequest->photo_path);
    }

    public function test_id_card_form_prefills_profile_and_saves_private_attachments(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        StudentProfile::query()->create([
            'user_id' => $student->id,
            'full_name' => 'Иванов Иван Иванович',
            'iin' => '060515500608',
            'course' => 2,
            'faculty' => 'Факультет информационных технологий',
            'specialty' => 'Информационные системы',
        ]);
        AcademicProfile::query()->create(['user_id' => $student->id, 'education_language' => 'ru']);
        $student->update(['phone' => '+77001234567']);

        $this->actingAs($student)
            ->get(route('student-services.id-card.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentServices/IdCard')
                ->where('initial.full_name', 'Иванов Иван Иванович')
                ->where('initial.iin', '060515500608')
                ->where('initial.phone', '+77001234567')
                ->where('initial.education_level', StudentProfile::EDUCATION_BACHELOR)
                ->where('initial.education_language', 'ru')
            );

        $this->actingAs($student)
            ->post(route('student-services.store'), $this->idCardData())
            ->assertRedirect(route('student-services.index'))
            ->assertSessionHasNoErrors();

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $this->assertSame('060515500608', $serviceRequest->iin);
        $this->assertSame('Иванов Иван Иванович', $serviceRequest->full_name);
        $this->assertSame(StudentProfile::EDUCATION_BACHELOR, $serviceRequest->education_level);
        $this->assertSame('2024-09-01', $serviceRequest->study_start_date->toDateString());
        $this->assertSame('2028-06-01', $serviceRequest->study_end_date->toDateString());
        Storage::disk('local')->assertExists([$serviceRequest->photo_path, $serviceRequest->receipt_path]);

        $this->actingAs($student)->get(route('student-services.show', $serviceRequest))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceRequest.has_photo', true)
                ->where('serviceRequest.has_receipt', true)
                ->where('serviceRequest.receipt_is_pdf', false)
                ->where('serviceRequest.education_level', StudentProfile::EDUCATION_BACHELOR)
            );
        $this->actingAs($student)->get(route('student-services.download', [$serviceRequest, 'receipt']))->assertOk();
        $photoPreview = $this->actingAs($student)->get(route('student-services.preview', [$serviceRequest, 'photo']))->assertOk();
        $receiptPreview = $this->actingAs($student)->get(route('student-services.preview', [$serviceRequest, 'receipt']))->assertOk();
        $this->assertStringStartsWith('inline', $photoPreview->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('inline', $receiptPreview->headers->get('Content-Disposition'));
        $receiptPreview->assertHeader('X-Content-Type-Options', 'nosniff');

        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        $curator = $this->user(Role::CURATOR);

        $this->actingAs($admin)->get(route('student-services.show', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page->where('canPrint', false));
        $this->actingAs($admin)->get(route('student-services.id-card.print', $serviceRequest))
            ->assertNotFound();
        $this->actingAs($admin)->get(route('student-services.id-card.photo', $serviceRequest))->assertOk();
        $this->actingAs($admin)->get(route('student-services.preview', [$serviceRequest, 'receipt']))->assertOk();
        $this->actingAs($student)->get(route('student-services.id-card.print', $serviceRequest))->assertForbidden();
        $this->actingAs($student)->get(route('student-services.id-card.photo', $serviceRequest))->assertForbidden();
        $this->actingAs($curator)->get(route('student-services.id-card.print', $serviceRequest))->assertForbidden();
        $this->actingAs($curator)->get(route('student-services.preview', [$serviceRequest, 'receipt']))->assertForbidden();
    }

    public function test_id_card_pdf_receipt_can_be_previewed_only_by_owner_or_staff(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        $other = $this->user(Role::STUDENT);
        $path = 'student-services/id-cards/receipts/receipt.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n");
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::ID_CARD,
            'status' => StudentServiceRequest::PENDING,
            'receipt_path' => $path,
        ]);

        $this->actingAs($student)->get(route('student-services.show', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page->where('serviceRequest.receipt_is_pdf', true));
        $this->actingAs($student)->get(route('student-services.preview', [$serviceRequest, 'receipt']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($other)->get(route('student-services.preview', [$serviceRequest, 'receipt']))->assertForbidden();
        $this->actingAs($student)->get(route('student-services.preview', [$serviceRequest, 'unknown']))->assertNotFound();
    }

    public function test_id_card_request_keeps_selected_education_level_at_submission(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        $profile = StudentProfile::query()->create([
            'user_id' => $student->id,
            'specialty' => 'Менеджмент',
            'education_level' => StudentProfile::EDUCATION_MASTER,
        ]);

        $this->actingAs($student)
            ->get(route('student-services.id-card.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('initial.education_level', StudentProfile::EDUCATION_MASTER)
            );

        $this->actingAs($student)
            ->post(route('student-services.store'), array_merge($this->idCardData(), [
                'education_level' => StudentProfile::EDUCATION_DOCTORATE,
            ]))
            ->assertSessionHasNoErrors();

        $serviceRequest = StudentServiceRequest::query()->firstOrFail();
        $profile->update(['education_level' => StudentProfile::EDUCATION_BACHELOR]);

        $this->actingAs($student)->get(route('student-services.show', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceRequest.education_level', StudentProfile::EDUCATION_DOCTORATE)
            );
    }

    public function test_id_card_program_code_sets_education_level_even_if_bachelor_was_selected(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);

        $this->actingAs($student)
            ->post(route('student-services.store'), array_merge($this->idCardData(), [
                'specialty' => '7М06101 - Информационные системы',
                'education_level' => StudentProfile::EDUCATION_BACHELOR,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            StudentProfile::EDUCATION_MASTER,
            StudentServiceRequest::query()->firstOrFail()->education_level,
        );
    }

    public function test_administrator_can_get_library_barcode_and_photo_hex_is_sent_as_form_data(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(['https://library.atu.edu.kz/setBarcode.asp' => Http::response([
            'ok' => true,
            'rdrId' => 'B50834',
            'iin' => '060515500608',
        ])]);

        $student = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        StudentProfile::query()->create([
            'user_id' => $student->id,
            'birth_date' => '2006-05-15',
        ]);
        $photoPath = UploadedFile::fake()->image('photo.jpg')->store('student-services/id-cards/photos', 'local');
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::ID_CARD,
            'status' => StudentServiceRequest::PENDING,
            'full_name' => 'Тестовый студент',
            'iin' => '060515500608',
            'photo_path' => $photoPath,
            'study_start_date' => '2026-09-01',
            'faculty' => 'Факультет информационных технологий',
            'specialty' => '7M06101 - Информационные системы',
            'education_level' => StudentProfile::EDUCATION_MASTER,
        ]);

        $this->actingAs($student)
            ->post(route('student-services.library-barcode.store', $serviceRequest), [
                'regdate' => '2026-09-01',
                'reregdate' => '2027-09-01',
            ])->assertForbidden();

        $this->actingAs($admin)
            ->get(route('student-services.show', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canRequestLibraryBarcode', true)
                ->where('libraryDates.regdate', '2026-09-01')
                ->where('libraryDates.reregdate', '2027-09-01')
            );

        $this->actingAs($admin)
            ->post(route('student-services.library-barcode.store', $serviceRequest), [
                'regdate' => '2026-09-01',
                'reregdate' => '2027-09-01',
            ])->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['iin'] === '060515500608'
            && $request['fio'] === 'Тестовый студент'
            && $request['birthday'] === '2006-05-15'
            && $request['status'] === 'Магистратура'
            && $request['regdate'] === '01-09-2026'
            && $request['reregdate'] === '01-09-2027'
            && $request['custom1'] === 'Факультет информационных технологий'
            && $request['custom2'] === '7M06101 - Информационные системы'
            && $request['ext'] === 'JPEG'
            && str_starts_with($request['photo_hex'], 'FFD8'));

        $this->assertSame('B50834', $serviceRequest->fresh()->library_rdr_id);
        $this->actingAs($student)
            ->get(route('student-services.show', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceRequest.library_rdr_id', 'B50834')
                ->where('canRequestLibraryBarcode', false)
            );
        $this->actingAs($admin)
            ->get(route('student-services.id-card.print', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page->where('card.library_rdr_id', 'B50834'));
        $this->actingAs($admin)
            ->get(route('student-services.show', $serviceRequest))
            ->assertInertia(fn (Assert $page) => $page->where('canPrint', true));

        $this->actingAs($admin)
            ->post(route('student-services.library-barcode.store', $serviceRequest), [
                'regdate' => '2026-09-01',
                'reregdate' => '2027-09-01',
            ])->assertSessionHasErrors('library_barcode');
        Http::assertSentCount(1);
    }

    public function test_library_existing_iin_response_reuses_rdr_id_and_converts_png_photo_to_jpeg(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(['https://library.atu.edu.kz/setBarcode.asp' => Http::response([
            'ok' => false,
            'error' => 'Пользователь с таким IIN уже существует',
            'rdrId' => 'B50086',
            'iin' => '060515500608',
        ], 409)]);

        $student = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        StudentProfile::query()->create(['user_id' => $student->id, 'birth_date' => '2006-05-15']);
        $photoPath = UploadedFile::fake()->image('photo.png')->store('student-services/id-cards/photos', 'local');
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::ID_CARD,
            'status' => StudentServiceRequest::PENDING,
            'full_name' => 'Тестовый студент',
            'iin' => '060515500608',
            'photo_path' => $photoPath,
        ]);

        $this->actingAs($admin)
            ->post(route('student-services.library-barcode.store', $serviceRequest), [
                'regdate' => '2026-09-01',
                'reregdate' => '2027-09-01',
            ])->assertSessionHasNoErrors();

        $this->assertSame('B50086', $serviceRequest->fresh()->library_rdr_id);
        Http::assertSent(fn ($request) => $request['ext'] === 'JPEG'
            && str_starts_with($request['photo_hex'], 'FFD8'));
    }

    public function test_library_barcode_is_not_saved_when_response_has_another_iin(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(['https://library.atu.edu.kz/setBarcode.asp' => Http::response([
            'ok' => true,
            'rdrId' => 'B50834',
            'iin' => '111111111111',
        ])]);

        $student = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        StudentProfile::query()->create(['user_id' => $student->id, 'birth_date' => '2006-05-15']);
        $photoPath = UploadedFile::fake()->image('photo.jpg')->store('student-services/id-cards/photos', 'local');
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::ID_CARD,
            'status' => StudentServiceRequest::PENDING,
            'full_name' => 'Тестовый студент',
            'iin' => '060515500608',
            'photo_path' => $photoPath,
        ]);

        $this->actingAs($admin)
            ->post(route('student-services.library-barcode.store', $serviceRequest), [
                'regdate' => '2026-09-01',
                'reregdate' => '2027-09-01',
            ])->assertSessionHasErrors('library_barcode');

        $this->assertNull($serviceRequest->fresh()->library_rdr_id);
    }

    public function test_id_card_requires_valid_data_and_attachments_are_not_public_to_other_students(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        $other = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);

        $this->actingAs($student)
            ->post(route('student-services.store'), ['service_type' => StudentServiceRequest::ID_CARD])
            ->assertSessionHasErrors(['full_name', 'iin', 'photo', 'receipt']);

        $this->actingAs($student)
            ->post(route('student-services.store'), array_merge($this->idCardData(), [
                'study_end_date' => '2020-01',
                'receipt' => UploadedFile::fake()->create('receipt.docx'),
            ]))
            ->assertSessionHasErrors(['study_end_date', 'receipt']);

        $this->actingAs($student)
            ->post(route('student-services.store'), array_merge($this->idCardData(), [
                'study_start_date' => '2024-09-01',
            ]))
            ->assertSessionHasErrors('study_start_date');

        $this->actingAs($student)->post(route('student-services.store'), $this->idCardData())->assertSessionHasNoErrors();
        $serviceRequest = StudentServiceRequest::query()->firstOrFail();

        $this->actingAs($other)->get(route('student-services.show', $serviceRequest))->assertForbidden();
        $this->actingAs($other)->get(route('student-services.download', [$serviceRequest, 'photo']))->assertForbidden();
        $this->actingAs($admin)->get(route('student-services.show', $serviceRequest))->assertOk();
        $this->actingAs($admin)->get(route('student-services.download', [$serviceRequest, 'receipt']))->assertOk();

        $this->actingAs($student)->post(route('student-services.store'), $this->idCardData())->assertSessionHasErrors('service_type');
        $this->assertSame(1, StudentServiceRequest::query()->count());
    }

    public function test_id_card_accepts_larger_attachments_within_the_new_limits(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);

        $this->actingAs($student)
            ->post(route('student-services.store'), array_merge($this->idCardData(), [
                'photo' => UploadedFile::fake()->image('photo.jpg')->size(17 * 1024),
            ]))
            ->assertSessionHasErrors('photo');

        $this->actingAs($student)
            ->post(route('student-services.store'), array_merge($this->idCardData(), [
                'receipt' => UploadedFile::fake()->image('receipt.png')->size(33 * 1024),
            ]))
            ->assertSessionHasErrors('receipt');

        $this->actingAs($student)
            ->post(route('student-services.store'), array_merge($this->idCardData(), [
                'photo' => UploadedFile::fake()->image('photo.jpg')->size(6 * 1024),
                'receipt' => UploadedFile::fake()->image('receipt.png')->size(12 * 1024),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, StudentServiceRequest::query()->count());
    }

    public function test_group_leader_can_submit_again_after_success_but_open_request_is_rejected(): void
    {
        Storage::fake('local');
        $leader = $this->user(Role::GROUP_LEADER);
        $this->ownFaceIdProfile($leader);

        $this->actingAs($leader)
            ->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasNoErrors();

        $this->actingAs($leader)
            ->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasNoErrors();

        StudentServiceRequest::query()->where('student_id', $leader->id)->latest()->firstOrFail()
            ->update(['status' => StudentServiceRequest::PENDING]);

        $this->actingAs($leader)
            ->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasErrors('service_type');

        $this->assertSame(2, StudentServiceRequest::query()->count());
    }

    public function test_staff_sees_pending_requests_by_default_and_can_select_all(): void
    {
        $student = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);

        foreach ([StudentServiceRequest::PENDING, StudentServiceRequest::COMPLETED] as $status) {
            StudentServiceRequest::query()->create([
                'student_id' => $student->id,
                'service_type' => StudentServiceRequest::FACE_ID,
                'status' => $status,
            ]);
        }

        $this->actingAs($admin)->get(route('student-services.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', StudentServiceRequest::PENDING)
                ->has('requests.data', 1)
                ->where('requests.data.0.status', StudentServiceRequest::PENDING)
            );

        $this->actingAs($admin)->get(route('student-services.index').'?status=')
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 2));

        $this->actingAs($student)->get(route('student-services.index'))
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 2));
    }

    public function test_administrator_can_process_requests_and_student_sees_response(): void
    {
        Storage::fake('local');
        $student = $this->user(Role::STUDENT);
        $this->ownFaceIdProfile($student);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::FACE_ID,
            'status' => StudentServiceRequest::PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('student-services.update', $serviceRequest), [
                'status' => StudentServiceRequest::IN_PROGRESS,
                'staff_comment' => 'Заявка принята',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('student-services.index', ['status' => StudentServiceRequest::IN_PROGRESS]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', true)
                ->where('canSubmit', false)
                ->has('requests.data', 1)
                ->where('requests.data.0.status', StudentServiceRequest::IN_PROGRESS)
            );

        $this->actingAs($admin)
            ->patch(route('student-services.update', $serviceRequest), [
                'status' => StudentServiceRequest::COMPLETED,
                'staff_comment' => 'Готово',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_service_requests', [
            'id' => $serviceRequest->id,
            'status' => StudentServiceRequest::COMPLETED,
            'handled_by_id' => $admin->id,
        ]);

        $this->actingAs($student)
            ->get(route('student-services.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.data.0.status', StudentServiceRequest::COMPLETED)
                ->where('requests.data.0.staff_comment', 'Готово')
                ->has('activeTypes', 0)
            );

        $this->actingAs($student)
            ->post(route('student-services.store'), $this->faceIdData())
            ->assertSessionHasNoErrors();
    }

    public function test_rejection_requires_comment_and_closed_request_cannot_be_changed(): void
    {
        $student = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::FACE_ID,
            'status' => StudentServiceRequest::PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('student-services.update', $serviceRequest), ['status' => StudentServiceRequest::REJECTED])
            ->assertSessionHasErrors('staff_comment');

        $this->actingAs($admin)
            ->patch(route('student-services.update', $serviceRequest), [
                'status' => StudentServiceRequest::REJECTED,
                'staff_comment' => 'Нужно обратиться лично',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->patch(route('student-services.update', $serviceRequest), [
                'status' => StudentServiceRequest::COMPLETED,
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_face_id_request_has_no_printable_card(): void
    {
        $student = $this->user(Role::STUDENT);
        $admin = $this->user(Role::ADMINISTRATOR_DIT);
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::FACE_ID,
            'status' => StudentServiceRequest::PENDING,
        ]);

        $this->actingAs($admin)->get(route('student-services.id-card.print', $serviceRequest))->assertNotFound();
        $this->actingAs($admin)->get(route('student-services.id-card.photo', $serviceRequest))->assertNotFound();
    }

    public function test_other_roles_cannot_access_or_manage_student_services(): void
    {
        $curator = $this->user(Role::CURATOR);
        $student = $this->user(Role::STUDENT);
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::ID_CARD,
            'status' => StudentServiceRequest::PENDING,
        ]);

        $this->actingAs($curator)->get(route('student-services.index'))->assertForbidden();
        $this->actingAs($curator)->post(route('student-services.store'), ['service_type' => StudentServiceRequest::ID_CARD])->assertForbidden();
        $this->actingAs($student)->patch(route('student-services.update', $serviceRequest), ['status' => StudentServiceRequest::COMPLETED])->assertForbidden();
        $this->actingAs($curator)->patch(route('student-services.update', $serviceRequest), ['status' => StudentServiceRequest::COMPLETED])->assertForbidden();
    }

    public function test_service_operator_can_process_requests_but_cannot_access_other_staff_sections(): void
    {
        $operator = $this->user(Role::SERVICE_OPERATOR);
        $student = $this->user(Role::STUDENT);
        $serviceRequest = StudentServiceRequest::query()->create([
            'student_id' => $student->id,
            'service_type' => StudentServiceRequest::ID_CARD,
            'status' => StudentServiceRequest::PENDING,
        ]);

        $this->actingAs($operator)->get(route('dashboard'))->assertRedirect(route('student-services.index'));
        $this->get(route('student-services.index'))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->where('canSubmit', false)
            ->has('requests.data', 1)
        );
        $this->get(route('student-services.show', $serviceRequest))->assertOk();
        $this->patch(route('student-services.update', $serviceRequest), [
            'status' => StudentServiceRequest::IN_PROGRESS,
        ])->assertSessionHasNoErrors();
        $this->assertSame($operator->id, $serviceRequest->fresh()->handled_by_id);

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('student-profiles.index'))->assertForbidden();
        $this->get(route('analytics-dashboard.index'))->assertForbidden();
        $this->get(route('groups.index'))->assertForbidden();
        $this->get(route('student-profile.edit'))->assertForbidden();
    }

    private function user(string $roleSlug): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
        ]);
    }

    private function idCardData(): array
    {
        return [
            'service_type' => StudentServiceRequest::ID_CARD,
            'full_name' => 'Иванов Иван Иванович',
            'iin' => '060515500608',
            'phone' => '+77001234567',
            'study_start_date' => '2024-09',
            'study_end_date' => '2028-06',
            'course' => 2,
            'education_language' => 'ru',
            'faculty' => 'Факультет информационных технологий',
            'specialty' => 'Информационные системы',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'receipt' => UploadedFile::fake()->image('receipt.png'),
        ];
    }

    private function ownFaceIdProfile(User $user, string $iin = '060515500608'): void
    {
        StudentProfile::query()->create(['user_id' => $user->id, 'iin' => $iin]);
    }

    private function faceIdData(string $iin = '060515500608'): array
    {
        return [
            'service_type' => StudentServiceRequest::FACE_ID,
            'iin' => $iin,
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'middle_name' => 'Иванович',
            'faculty' => 'Факультет информационных технологий',
            'course' => 2,
            'education_level' => StudentProfile::EDUCATION_BACHELOR,
            'photo' => UploadedFile::fake()->image('face-photo.jpg'),
        ];
    }
}
