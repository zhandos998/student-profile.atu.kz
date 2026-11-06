<?php

namespace App\Http\Controllers;

use App\Models\ClearanceRequest;
use App\Models\ClearanceResponse;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClearanceRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canViewClearanceRequests(), 403);

        $canCreate = $user->canCreateClearanceRequests();
        $canReview = $user->canReviewClearanceRequests();
        $studentProfileId = $user->studentProfile?->id;

        return Inertia::render('ClearanceRequests/Index', [
            'canCreate' => $canCreate,
            'canReview' => $canReview,
            'recipientOptions' => $canCreate ? $this->recipientOptions($user) : [],
            'sentRequests' => $canCreate
                ? ClearanceRequest::query()
                    ->where('created_by_id', $user->id)
                    ->with(['studentProfile.user', 'studentProfile.studentGroup', 'responses'])
                    ->latest()
                    ->paginate(10, ['*'], 'sent_page')
                    ->withQueryString()
                    ->through(fn (ClearanceRequest $clearance): array => $this->requestPayload($clearance, $user))
                : null,
            'assignedRequests' => $canReview
                ? ClearanceRequest::query()
                    ->whereHas('responses', fn ($query) => $query->where('reviewer_id', $user->id))
                    ->with(['studentProfile.user', 'studentProfile.studentGroup', 'responses'])
                    ->latest()
                    ->paginate(10, ['*'], 'inbox_page')
                    ->withQueryString()
                    ->through(fn (ClearanceRequest $clearance): array => $this->requestPayload($clearance, $user))
                : null,
            'studentRequests' => $studentProfileId
                ? ClearanceRequest::query()
                    ->where('student_profile_id', $studentProfileId)
                    ->with(['studentProfile.user', 'studentProfile.studentGroup', 'responses'])
                    ->latest()
                    ->paginate(10, ['*'], 'student_page')
                    ->withQueryString()
                    ->through(fn (ClearanceRequest $clearance): array => $this->requestPayload($clearance, $user))
                : null,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        abort_unless($request->user()->canCreateClearanceRequests(), 403);

        $validated = $request->validate(['iin' => ['required', 'digits:12']]);
        $profiles = StudentProfile::query()
            ->where('iin', $validated['iin'])
            ->whereHas('user.role', fn ($query) => $query->whereIn('slug', User::STUDENT_DATA_ROLES))
            ->with(['user', 'studentGroup'])
            ->limit(2)
            ->get();

        if ($profiles->isEmpty()) {
            return response()->json(['message' => 'Студент с указанным ИИН не найден.'], 404);
        }

        if ($profiles->count() > 1) {
            return response()->json(['message' => 'Найдено несколько анкет с этим ИИН. Обратитесь в ДИТ.'], 409);
        }

        $profile = $profiles->first();

        return response()->json([
            'student' => [
                'id' => $profile->id,
                'iin' => $profile->iin,
                'name' => $profile->full_name ?: $profile->user?->name,
                'faculty' => $profile->faculty,
                'group' => $profile->currentGroupName(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $creator = $request->user();
        abort_unless($creator->canCreateClearanceRequests(), 403);

        $validated = $request->validate([
            'iin' => ['required', 'digits:12'],
            'student_profile_id' => ['required', 'integer', Rule::exists('student_profiles', 'id')],
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
        ]);

        $reviewerIds = array_map('intval', $validated['reviewer_ids']);
        $reviewers = User::query()->with('role')->whereKey($reviewerIds)->get();

        if ($reviewers->count() !== count($reviewerIds)
            || $reviewers->contains(fn (User $reviewer): bool => ! $reviewer->canReviewClearanceRequests()
                || $reviewer->id === $creator->id)) {
            throw ValidationException::withMessages(['reviewer_ids' => 'Выберите сотрудников из списка получателей.']);
        }

        DB::transaction(function () use ($validated, $creator, $reviewers): void {
            $profile = StudentProfile::query()
                ->whereKey($validated['student_profile_id'])
                ->where('iin', $validated['iin'])
                ->with('user.role')
                ->lockForUpdate()
                ->first();

            if (! $profile || ! $profile->user?->hasStudentDataRole()) {
                throw ValidationException::withMessages(['iin' => 'Студент с указанным ИИН не найден.']);
            }

            if ($reviewers->contains('id', $profile->user_id)) {
                throw ValidationException::withMessages(['reviewer_ids' => 'Студент не может согласовывать свою заявку.']);
            }

            $hasOpenRequest = ClearanceRequest::query()
                ->where('student_profile_id', $profile->id)
                ->whereHas('responses', fn ($query) => $query->where('decision', ClearanceResponse::PENDING))
                ->exists();

            if ($hasOpenRequest) {
                throw ValidationException::withMessages(['iin' => 'У студента уже есть заявка с ожидающими ответами.']);
            }

            $clearance = ClearanceRequest::query()->create([
                'student_profile_id' => $profile->id,
                'created_by_id' => $creator->id,
                'creator_name' => $creator->name,
            ]);

            foreach ($reviewers as $reviewer) {
                $clearance->responses()->create([
                    'reviewer_id' => $reviewer->id,
                    'reviewer_name' => $reviewer->name,
                    'reviewer_position' => $reviewer->position ?: $reviewer->role?->name,
                    'decision' => ClearanceResponse::PENDING,
                ]);
            }
        });

        return redirect()->route('clearance-requests.index')->with('status', 'clearance-request-created');
    }

    public function respond(Request $request, ClearanceResponse $clearanceResponse): RedirectResponse
    {
        $reviewer = $request->user();
        abort_unless($reviewer->canReviewClearanceRequests(), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([ClearanceResponse::APPROVED, ClearanceResponse::REJECTED])],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($clearanceResponse, $reviewer, $validated): void {
            $response = ClearanceResponse::query()
                ->whereKey($clearanceResponse->id)
                ->where('reviewer_id', $reviewer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($response->decision !== ClearanceResponse::PENDING) {
                throw ValidationException::withMessages(['decision' => 'Решение по этой заявке уже отправлено.']);
            }

            $response->update([
                'decision' => $validated['decision'],
                'comment' => $validated['comment'],
                'responded_at' => now(),
            ]);
        });

        return back()->with('status', 'clearance-response-saved');
    }

    private function recipientOptions(User $creator): array
    {
        return User::query()
            ->whereKeyNot($creator->id)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', User::CLEARANCE_REVIEWER_ROLES))
            ->with('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'position', 'role_id'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'position' => $user->position ?: $user->role?->name,
                'email' => $user->email,
            ])
            ->all();
    }

    private function requestPayload(ClearanceRequest $clearance, User $viewer): array
    {
        $profile = $clearance->studentProfile;

        return [
            'id' => $clearance->id,
            'student_name' => $profile?->full_name ?: $profile?->user?->name ?: 'Без имени',
            'student_iin' => $profile?->iin,
            'student_faculty' => $profile?->faculty,
            'student_group' => $profile?->currentGroupName(),
            'creator_name' => $clearance->creator_name,
            'status' => $clearance->status(),
            'created_at' => $clearance->created_at?->toIso8601String(),
            'responses' => $clearance->responses
                ->sortBy('id')
                ->map(fn (ClearanceResponse $response): array => [
                    'id' => $response->id,
                    'reviewer_name' => $response->reviewer_name,
                    'reviewer_position' => $response->reviewer_position,
                    'decision' => $response->decision,
                    'comment' => $response->comment,
                    'responded_at' => $response->responded_at?->toIso8601String(),
                    'is_mine' => $response->reviewer_id === $viewer->id,
                ])
                ->values()
                ->all(),
        ];
    }
}
