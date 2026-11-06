<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\StudentServiceRequest;
use App\Models\User;
use App\Services\HikvisionFaceIdClient;
use App\Services\LibraryBarcodeClient;
use App\Support\StudentProfileOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentServiceRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canViewStudentServices(), 403);

        $canManage = $user->canManageStudentServiceRequests();
        $filters = $canManage ? $request->validate([
            'service_type' => ['nullable', Rule::in([StudentServiceRequest::ID_CARD, StudentServiceRequest::FACE_ID])],
            'status' => ['nullable', Rule::in([
                StudentServiceRequest::PENDING,
                StudentServiceRequest::IN_PROGRESS,
                StudentServiceRequest::COMPLETED,
                StudentServiceRequest::REJECTED,
            ])],
        ]) : [];

        $requests = StudentServiceRequest::query()
            ->with(['student.studentProfile', 'handler'])
            ->when(! $canManage, fn ($query) => $query->where('student_id', $user->id))
            ->when($filters['service_type'] ?? null, fn ($query, $type) => $query->where('service_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StudentServiceRequest $serviceRequest): array => [
                'id' => $serviceRequest->id,
                'service_type' => $serviceRequest->service_type,
                'status' => $serviceRequest->status,
                'student_name' => $serviceRequest->full_name ?: ($serviceRequest->student?->studentProfile?->full_name ?: $serviceRequest->student?->name),
                'student_iin' => $serviceRequest->iin ?: $serviceRequest->student?->studentProfile?->iin,
                'student_comment' => $serviceRequest->student_comment,
                'staff_comment' => $serviceRequest->staff_comment,
                'handled_by' => $serviceRequest->handler?->name,
                'created_at' => $serviceRequest->created_at?->toIso8601String(),
                'handled_at' => $serviceRequest->handled_at?->toIso8601String(),
            ]);

        return Inertia::render('StudentServices/Index', [
            'canSubmit' => $user->canUseOwnStudentProfile(),
            'canManage' => $canManage,
            'requests' => $requests,
            'filters' => $filters,
            'activeTypes' => $user->canUseOwnStudentProfile()
                ? StudentServiceRequest::query()
                    ->where('student_id', $user->id)
                    ->whereIn('status', [StudentServiceRequest::PENDING, StudentServiceRequest::IN_PROGRESS])
                    ->pluck('service_type')
                    ->all()
                : [],
        ]);
    }

    public function createIdCard(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canUseOwnStudentProfile(), 403);

        $user->load(['studentProfile.studentGroup', 'academicProfile']);
        $profile = $user->studentProfile;

        return Inertia::render('StudentServices/IdCard', [
            'initial' => [
                'full_name' => $profile?->full_name ?? '',
                'iin' => $profile?->iin ?? '',
                'phone' => $user->phone ?? '',
                'study_start_date' => '',
                'study_end_date' => '',
                'course' => $profile?->course ?? '',
                'education_language' => $user->academicProfile?->education_language ?? '',
                'education_level' => $profile?->education_level ?? StudentProfile::EDUCATION_BACHELOR,
                'faculty' => $profile?->studentGroup?->faculty ?: ($profile?->faculty ?? ''),
                'specialty' => $profile?->specialty ?? '',
            ],
            'hasOpenRequest' => StudentServiceRequest::query()
                ->where('student_id', $user->id)
                ->where('service_type', StudentServiceRequest::ID_CARD)
                ->whereIn('status', [StudentServiceRequest::PENDING, StudentServiceRequest::IN_PROGRESS])
                ->exists(),
        ]);
    }

    public function createFaceId(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canUseOwnStudentProfile(), 403);

        $user->load('studentProfile.studentGroup');
        $profile = $user->studentProfile;
        $nameParts = preg_split('/\s+/u', trim($profile?->full_name ?? '')) ?: [];

        return Inertia::render('StudentServices/FaceId', [
            'initial' => [
                'iin' => $profile?->iin ?? '',
                'last_name' => $nameParts[0] ?? '',
                'first_name' => $nameParts[1] ?? '',
                'middle_name' => implode(' ', array_slice($nameParts, 2)),
                'faculty' => $profile?->studentGroup?->faculty ?: ($profile?->faculty ?? ''),
                'course' => $profile?->course ?? '',
                'education_level' => $profile?->education_level ?? StudentProfile::EDUCATION_BACHELOR,
            ],
            'hasOpenRequest' => StudentServiceRequest::query()
                ->where('student_id', $user->id)
                ->where('service_type', StudentServiceRequest::FACE_ID)
                ->whereIn('status', [StudentServiceRequest::PENDING, StudentServiceRequest::IN_PROGRESS])
                ->exists(),
            'integrationReady' => str_starts_with((string) config('hikvision.base_url'), 'https://')
                && filled(config('hikvision.app_key'))
                && filled(config('hikvision.app_secret')),
            'faculties' => StudentProfileOptions::toSameValueOptions(StudentProfileOptions::facultyNames()),
        ]);
    }

    public function show(Request $request, StudentServiceRequest $studentServiceRequest): Response
    {
        $user = $request->user();
        abort_unless($user->canManageStudentServiceRequests() || $studentServiceRequest->student_id === $user->id, 403);

        $studentServiceRequest->load(['student', 'handler']);

        return Inertia::render('StudentServices/Show', [
            'canManage' => $user->canManageStudentServiceRequests(),
            'canPrint' => $user->canManageStudentServiceRequests()
                && $studentServiceRequest->service_type === StudentServiceRequest::ID_CARD
                && filled($studentServiceRequest->full_name)
                && filled($studentServiceRequest->photo_path)
                && Storage::disk('local')->exists($studentServiceRequest->photo_path),
            'canRequestLibraryBarcode' => $user->canManageStudentServiceRequests()
                && $studentServiceRequest->service_type === StudentServiceRequest::ID_CARD
                && blank($studentServiceRequest->library_rdr_id),
            'libraryDates' => [
                'regdate' => ($studentServiceRequest->study_start_date ?? now())->format('Y-m-d'),
                'reregdate' => ($studentServiceRequest->study_start_date ?? now())->copy()->addYear()->format('Y-m-d'),
            ],
            'serviceRequest' => $studentServiceRequest->only([
                'id', 'service_type', 'status', 'full_name', 'last_name', 'first_name', 'middle_name', 'iin', 'phone',
                'study_start_date', 'study_end_date', 'course', 'education_language', 'education_level', 'faculty',
                'specialty', 'admission_year', 'hikvision_org_index_code', 'hikvision_synced_at',
                'student_comment', 'staff_comment', 'created_at', 'handled_at',
                'library_rdr_id', 'library_regdate', 'library_reregdate', 'library_registered_at',
            ]) + [
                'student_name' => $studentServiceRequest->student?->name,
                'handled_by' => $studentServiceRequest->handler?->name,
                'has_photo' => filled($studentServiceRequest->photo_path),
                'has_receipt' => filled($studentServiceRequest->receipt_path),
            ],
        ]);
    }

    public function printIdCard(Request $request, StudentServiceRequest $studentServiceRequest): Response
    {
        abort_unless($request->user()->canManageStudentServiceRequests(), 403);
        abort_unless(
            $studentServiceRequest->service_type === StudentServiceRequest::ID_CARD
                && filled($studentServiceRequest->full_name)
                && filled($studentServiceRequest->photo_path)
                && Storage::disk('local')->exists($studentServiceRequest->photo_path),
            404,
        );

        return Inertia::render('StudentServices/PrintIdCard', [
            'card' => [
                'id' => $studentServiceRequest->id,
                'full_name' => $studentServiceRequest->full_name,
                'faculty' => $studentServiceRequest->faculty,
                'specialty' => $studentServiceRequest->specialty,
                'study_start_date' => $studentServiceRequest->study_start_date?->format('Y-m-d'),
                'study_end_date' => $studentServiceRequest->study_end_date?->format('Y-m-d'),
                'education_language' => $studentServiceRequest->education_language,
                'iin' => $studentServiceRequest->iin,
                'library_rdr_id' => $studentServiceRequest->library_rdr_id,
            ],
        ]);
    }

    public function registerLibraryBarcode(
        Request $request,
        StudentServiceRequest $studentServiceRequest,
        LibraryBarcodeClient $client,
    ): RedirectResponse {
        abort_unless($request->user()->canManageStudentServiceRequests(), 403);
        abort_unless($studentServiceRequest->service_type === StudentServiceRequest::ID_CARD, 404);

        if (filled($studentServiceRequest->library_rdr_id)) {
            throw ValidationException::withMessages(['library_barcode' => 'Штрихкод для этой заявки уже получен.']);
        }

        $validated = $request->validate([
            'regdate' => ['required', 'date_format:Y-m-d'],
            'reregdate' => ['required', 'date_format:Y-m-d', 'after:regdate'],
        ]);

        $studentServiceRequest->loadMissing('student.studentProfile');
        $birthDate = $studentServiceRequest->student?->studentProfile?->birth_date;

        if (! $birthDate || blank($studentServiceRequest->iin) || blank($studentServiceRequest->full_name)) {
            throw ValidationException::withMessages([
                'library_barcode' => 'Для библиотеки нужны ИИН, ФИО и дата рождения в анкете студента.',
            ]);
        }

        try {
            $rdrId = $client->register(
                $studentServiceRequest,
                $birthDate,
                CarbonImmutable::parse($validated['regdate']),
                CarbonImmutable::parse($validated['reregdate']),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['library_barcode' => $exception->getMessage()]);
        }

        $studentServiceRequest->update([
            'library_rdr_id' => $rdrId,
            'library_regdate' => $validated['regdate'],
            'library_reregdate' => $validated['reregdate'],
            'library_registered_at' => now(),
        ]);

        return back()->with('status', 'library-barcode-created');
    }

    public function printPhoto(Request $request, StudentServiceRequest $studentServiceRequest): StreamedResponse
    {
        abort_unless($request->user()->canManageStudentServiceRequests(), 403);
        abort_unless($studentServiceRequest->service_type === StudentServiceRequest::ID_CARD, 404);

        $path = $studentServiceRequest->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Disposition' => 'inline']);
    }

    public function download(Request $request, StudentServiceRequest $studentServiceRequest, string $type): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->canManageStudentServiceRequests() || $studentServiceRequest->student_id === $user->id, 403);

        $path = $type === 'photo' ? $studentServiceRequest->photo_path : $studentServiceRequest->receipt_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $type.'-request-'.$studentServiceRequest->id.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }

    public function store(Request $request, HikvisionFaceIdClient $hikvision): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canUseOwnStudentProfile(), 403);

        $validated = $request->validate([
            'service_type' => ['required', Rule::in([StudentServiceRequest::ID_CARD, StudentServiceRequest::FACE_ID])],
            'student_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['service_type'] === StudentServiceRequest::ID_CARD) {
            $validated += $request->validate([
                'full_name' => ['required', 'string', 'max:255'],
                'iin' => ['required', 'digits:12'],
                'phone' => ['required', 'string', 'min:9', 'max:32'],
                'study_start_date' => ['required', 'date_format:Y-m'],
                'study_end_date' => ['required', 'date_format:Y-m', 'after:study_start_date'],
                'course' => ['required', 'integer', 'between:1,8'],
                'education_language' => ['required', Rule::in(array_keys(StudentProfileOptions::EDUCATION_LANGUAGES))],
                'education_level' => ['nullable', Rule::in(StudentProfile::EDUCATION_LEVELS)],
                'faculty' => ['required', 'string', 'max:255'],
                'specialty' => ['required', 'string', 'max:255'],
                'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:16384'],
                'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:32768'],
            ]);

            $validated['study_start_date'] = CarbonImmutable::createFromFormat('!Y-m', $validated['study_start_date'])->toDateString();
            $validated['study_end_date'] = CarbonImmutable::createFromFormat('!Y-m', $validated['study_end_date'])->toDateString();
            $validated['education_level'] = StudentProfile::inferredEducationLevel($validated['specialty'])
                ?? ($validated['education_level'] ?? null)
                ?? $user->studentProfile?->education_level
                ?? StudentProfile::EDUCATION_BACHELOR;
        } else {
            $validated += $request->validate([
                'iin' => ['required', 'digits:12'],
                'last_name' => ['required', 'string', 'max:100'],
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:50'],
                'faculty' => ['required', 'string', Rule::in(StudentProfileOptions::facultyNames())],
                'course' => ['required', 'integer', 'between:1,8'],
                'education_level' => ['required', Rule::in(StudentProfile::EDUCATION_LEVELS)],
                'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:16384'],
            ]);

            $today = now();
            $academicYear = $today->month >= 9 ? $today->year : $today->year - 1;
            $validated['admission_year'] = $academicYear - (int) $validated['course'] + 1;

            if (blank($user->studentProfile?->iin) || $validated['iin'] !== $user->studentProfile->iin) {
                throw ValidationException::withMessages([
                    'iin' => 'ИИН заявки должен совпадать с ИИН в вашей анкете студента.',
                ]);
            }

            $validated['last_name'] = trim($validated['last_name']);
            $validated['first_name'] = trim($validated['first_name']);
            $validated['middle_name'] = trim($validated['middle_name'] ?? '') ?: null;
            $validated['full_name'] = implode(' ', array_filter([
                $validated['last_name'],
                $validated['first_name'],
                $validated['middle_name'],
            ]));
        }

        DB::transaction(function () use ($user, $validated, $request, $hikvision): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $hasOpenRequest = StudentServiceRequest::query()
                ->where('student_id', $user->id)
                ->where('service_type', $validated['service_type'])
                ->whereIn('status', [StudentServiceRequest::PENDING, StudentServiceRequest::IN_PROGRESS])
                ->exists();

            if ($hasOpenRequest) {
                throw ValidationException::withMessages([
                    'service_type' => __('student_services.already_open'),
                ]);
            }

            $photoPath = null;
            $receiptPath = null;
            $hikvisionOrgCode = null;

            try {
                if ($validated['service_type'] === StudentServiceRequest::ID_CARD) {
                    $photoPath = $request->file('photo')->store('student-services/id-cards/photos', 'local');
                    $receiptPath = $request->file('receipt')->store('student-services/id-cards/receipts', 'local');
                    if (! $photoPath || ! $receiptPath) {
                        throw new RuntimeException('Could not store ID card attachments.');
                    }
                } else {
                    $photoPath = $request->file('photo')->store('student-services/face-ids/photos', 'local');
                    if (! $photoPath) {
                        throw new RuntimeException('Could not store Face ID photo.');
                    }

                    try {
                        $hikvisionOrgCode = $hikvision->submit($validated, $request->file('photo'));
                    } catch (RuntimeException $exception) {
                        throw ValidationException::withMessages(['service_type' => $exception->getMessage()]);
                    }
                }

                StudentServiceRequest::query()->create(array_merge(
                    collect($validated)->except(['photo', 'receipt'])->all(),
                    [
                        'student_id' => $user->id,
                        'status' => $hikvisionOrgCode ? StudentServiceRequest::COMPLETED : StudentServiceRequest::PENDING,
                        'photo_path' => $photoPath,
                        'receipt_path' => $receiptPath,
                        'hikvision_org_index_code' => $hikvisionOrgCode,
                        'hikvision_synced_at' => $hikvisionOrgCode ? now() : null,
                    ],
                ));
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete(array_filter([$photoPath, $receiptPath]));
                throw $exception;
            }
        });

        return to_route('student-services.index')->with(
            'status',
            $validated['service_type'] === StudentServiceRequest::FACE_ID
                ? 'face-id-synced'
                : 'student-service-request-created',
        );
    }

    public function update(Request $request, StudentServiceRequest $studentServiceRequest): RedirectResponse
    {
        $handler = $request->user();
        abort_unless($handler->canManageStudentServiceRequests(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                StudentServiceRequest::IN_PROGRESS,
                StudentServiceRequest::COMPLETED,
                StudentServiceRequest::REJECTED,
            ])],
            'staff_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === StudentServiceRequest::REJECTED && blank($validated['staff_comment'] ?? null)) {
            throw ValidationException::withMessages([
                'staff_comment' => __('student_services.rejection_reason_required'),
            ]);
        }

        DB::transaction(function () use ($studentServiceRequest, $handler, $validated): void {
            $locked = StudentServiceRequest::query()->whereKey($studentServiceRequest->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [StudentServiceRequest::COMPLETED, StudentServiceRequest::REJECTED], true)) {
                throw ValidationException::withMessages([
                    'status' => __('student_services.already_closed'),
                ]);
            }

            $locked->update([
                'status' => $validated['status'],
                'staff_comment' => $validated['staff_comment'] ?? null,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
            ]);
        });

        return back()->with('status', 'student-service-request-updated');
    }
}
