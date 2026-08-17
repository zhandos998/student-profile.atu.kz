<?php

namespace App\Http\Requests\Auth;

use App\Models\Role;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\PlatonusAuthClient;
use App\Support\Phone;
use App\Support\StudentProfileOptions;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isPlatonus = $this->authType() === 'platonus';

        return [
            'auth_type' => ['nullable', Rule::in(['local', 'platonus'])],
            'email' => [$isPlatonus ? 'nullable' : 'required', 'string'],
            'login' => [$isPlatonus ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if ($this->authType() === 'platonus') {
            $this->authenticateWithPlatonus();

            return;
        }

        $login = (string) $this->input('email');
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
        $credentials = [
            $isEmail ? 'email' : 'phone_normalized' => $isEmail
                ? Str::lower($login)
                : Phone::normalize($login),
            'password' => $this->input('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function authenticateWithPlatonus(): void
    {
        $login = trim((string) $this->input('login'));
        $client = app(PlatonusAuthClient::class);
        $result = $client->verify($login, (string) $this->input('password'));

        if (! $result['ok']) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => $result['message'] ?: trans('auth.failed'),
            ]);
        }

        Auth::login(
            $this->syncPlatonusUser($login, $this->studentWithFullData($client, $result['student'])),
            $this->boolean('remember'),
        );

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function syncPlatonusUser(string $login, array $student): User
    {
        return DB::transaction(function () use ($login, $student): User {
            $platonusLogin = $this->normalizePlatonusLogin($login);
            $email = $this->studentEmail($student);
            $user = User::query()->where('platonus_login', $platonusLogin)->first();

            if (! $user && $email) {
                $user = User::query()->where('email', $email)->first();
            }

            if (! $user) {
                $user = new User();
                $user->password = Hash::make(Str::random(48));
                $user->email = $this->availableEmail($email, null, $platonusLogin);
            }

            $name = $this->studentFullName($student) ?: $user->name ?: $login;

            $user->forceFill([
                'name' => $name,
                'platonus_login' => $platonusLogin,
                'role_id' => $user->role_id ?: Role::query()->where('slug', Role::STUDENT)->value('id'),
                'position' => $user->position ?: 'Студент',
            ]);

            if ($email && $this->emailIsAvailableFor($email, $user)) {
                $user->email = $email;
            }

            $phone = $this->studentValue($student, [
                'phone',
                'phone_number',
                'mobile',
                'mobile_phone',
                'contact_phone',
                'contacts.mobile',
                'contacts.phone',
            ]);
            if ($phone && blank($user->phone)) {
                $user->phone = $phone;
                $phoneNormalized = Phone::normalize($phone);

                $phoneQuery = User::query()->where('phone_normalized', $phoneNormalized);

                if ($user->exists) {
                    $phoneQuery->whereKeyNot($user->getKey());
                }

                if (strlen($phoneNormalized) >= 10 && ! $phoneQuery->exists()) {
                    $user->phone_normalized = $phoneNormalized;
                }
            }

            $user->save();
            $this->syncStudentProfile($user, $student);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function syncStudentProfile(User $user, array $student): void
    {
        $profile = StudentProfile::query()->firstOrNew(['user_id' => $user->id]);
        $groupName = $this->studentValue($student, ['group', 'group_name', 'student_group', 'groupName', 'group_title', 'education.group_name']);
        $faculty = $this->normalizeFaculty($this->studentValue($student, [
            'faculty',
            'faculty_name',
            'facultyName',
            'education.faculty_name_ru',
            'education.faculty_name_kz',
        ]));
        $studentGroup = $this->studentGroup($groupName, $faculty);

        if (! $profile->exists) {
            $profile->profile_status = StudentProfile::STATUS_DRAFT;
            $profile->student_status = StudentProfile::STUDENT_STATUS_ACTIVE;
        }

        $data = [
            'full_name' => $this->studentFullName($student) ?: $user->name,
            'birth_date' => $this->studentValue($student, ['birth_date', 'birthday', 'date_of_birth']),
            'study_form' => $this->studentValue($student, ['study_form', 'education_form', 'form_of_study', 'education.study_form_ru']),
            'nationality' => $this->studentValue($student, ['nationality', 'nationality.ru', 'nationality.kz']),
            'citizenship' => $this->studentCitizenship($student),
            'iin' => $this->studentValue($student, ['iin', 'IIN', 'iin_number', 'individual_identification_number']),
            'identity_document_number' => $this->studentValue($student, ['identity_document_number', 'identity_number', 'document_number']),
            'gender' => $this->studentGender($student),
            'faculty' => $faculty,
            'group_name' => $groupName,
            'specialty' => $this->studentSpecialty($student),
            'course' => $this->studentCourse($student),
            'stay_address' => $this->studentValue($student, ['stay_address', 'living_address', 'address.living_address']),
            'residence_address' => $this->studentValue($student, ['residence_address', 'registration_address', 'address.registration_address']),
            'contact_details' => $this->studentValue($student, ['phone', 'phone_number', 'mobile', 'mobile_phone', 'contact_phone', 'contacts.mobile', 'contacts.phone']),
            'personal_email' => $this->studentEmail($student),
            'parent_guardian_contacts' => $this->studentParentContacts($student),
        ];

        if ($studentGroup) {
            $profile->student_group_id = $studentGroup->id;
        }

        foreach ($data as $field => $value) {
            if (filled($value)) {
                $profile->{$field} = $value;
            }
        }

        $profile->save();
    }

    private function authType(): string
    {
        return (string) $this->input('auth_type', 'local');
    }

    /**
     * @param  array<string, mixed>  $student
     * @return array<string, mixed>
     */
    private function studentWithFullData(PlatonusAuthClient $client, array $student): array
    {
        $iin = $this->studentIin($student);

        if (! $iin) {
            return $student;
        }

        $full = $client->studentFull($iin);

        if (! $full['ok']) {
            return $student;
        }

        return array_replace_recursive($student, $full['student']);
    }

    private function loginField(): string
    {
        return $this->authType() === 'platonus' ? 'login' : 'email';
    }

    private function normalizePlatonusLogin(string $login): string
    {
        return Str::lower(trim($login));
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentFullName(array $student): ?string
    {
        $fullName = $this->studentValue($student, [
            'full_name',
            'fullname',
            'fullName',
            'fio',
            'student_name',
            'studentName',
            'name',
        ]);

        if ($fullName) {
            return $fullName;
        }

        $parts = array_filter([
            $this->studentValue($student, ['last_name', 'lastname', 'surname']),
            $this->studentValue($student, ['first_name', 'firstname', 'given_name']),
            $this->studentValue($student, ['middle_name', 'middlename', 'patronymic']),
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentEmail(array $student): ?string
    {
        $email = $this->studentValue($student, ['email', 'mail', 'e_mail', 'personal_email', 'contacts.email']);

        if (! $email || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return Str::lower($email);
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentCourse(array $student): ?int
    {
        $course = $this->studentValue($student, ['course', 'year', 'study_year', 'education.course']);

        if (! is_numeric($course)) {
            return null;
        }

        $course = (int) $course;

        return $course > 0 && $course <= 10 ? $course : null;
    }

    /**
     * @param  array<string, mixed>  $student
     * @param  array<int, string>  $keys
     */
    private function studentValue(array $student, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($student, $key);

            if ($value === null) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value)) {
                $value = data_get($value, 'ru')
                    ?? data_get($value, 'kz')
                    ?? data_get($value, 'name')
                    ?? data_get($value, 'title')
                    ?? data_get($value, 'value');
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentIin(array $student): ?string
    {
        return $this->studentValue($student, ['iin', 'IIN', 'iin_number', 'individual_identification_number']);
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentGender(array $student): ?string
    {
        $gender = Str::lower((string) $this->studentValue($student, ['gender', 'sex', 'sex.ru', 'sex.kz']));

        if ($gender === '') {
            return null;
        }

        if (Str::contains($gender, ['female', 'жен', 'әйел'])) {
            return 'female';
        }

        if (Str::contains($gender, ['male', 'муж', 'ер'])) {
            return 'male';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentCitizenship(array $student): ?string
    {
        $citizenship = Str::lower((string) $this->studentValue($student, ['citizenship', 'citizenship.ru', 'citizenship.kz']));

        if ($citizenship === '') {
            return null;
        }

        if (Str::contains($citizenship, ['канд', 'қандас'])) {
            return 'kandas';
        }

        if (Str::contains($citizenship, ['казахстан', 'қазақстан', 'рк', 'kazakhstan'])) {
            return 'kazakhstan_citizen';
        }

        return 'foreign_citizen';
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentSpecialty(array $student): ?string
    {
        $specialty = $this->studentValue($student, [
            'specialty',
            'speciality',
            'educational_program',
            'education_program',
            'program',
            'education.speciality_name_ru',
        ]);

        $code = $this->studentValue($student, ['speciality_code', 'education.speciality_code']);

        if ($specialty && $code) {
            return $code.' - '.$specialty;
        }

        return $specialty ?: $code;
    }

    /**
     * @param  array<string, mixed>  $student
     */
    private function studentParentContacts(array $student): ?string
    {
        $parents = data_get($student, 'parents');

        if (! is_array($parents) || $parents === []) {
            return null;
        }

        $contacts = collect($parents)
            ->map(function (mixed $parent): ?string {
                if (is_scalar($parent)) {
                    return trim((string) $parent) ?: null;
                }

                if (! is_array($parent)) {
                    return null;
                }

                $parts = array_filter([
                    $this->studentFullName($parent),
                    $this->studentValue($parent, ['phone', 'mobile', 'phone_number']),
                    $this->studentValue($parent, ['address', 'registration_address', 'living_address']),
                ]);

                return $parts === [] ? null : implode(', ', $parts);
            })
            ->filter()
            ->values();

        return $contacts->isEmpty() ? null : $contacts->implode("\n");
    }

    private function normalizeFaculty(?string $faculty): ?string
    {
        if (! $faculty) {
            return null;
        }

        $facultyLower = Str::lower($faculty);
        $faculties = StudentProfileOptions::facultyNames();

        if (Str::contains($facultyLower, ['биотехнолог', 'химичес'])) {
            return $faculties[0] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['дизайн', 'текстил', 'одежд', 'киім'])) {
            return $faculties[1] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['интеллект', 'инженер'])) {
            return $faculties[2] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['информацион', 'ақпарат'])) {
            return $faculties[3] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['пищев', 'тағам'])) {
            return $faculties[4] ?? $faculty;
        }

        if (Str::contains($facultyLower, ['эконом', 'бизнес'])) {
            return $faculties[5] ?? $faculty;
        }

        return $faculty;
    }

    private function studentGroup(?string $groupName, ?string $faculty): ?StudentGroup
    {
        if (! $groupName) {
            return null;
        }

        return StudentGroup::query()
            ->where('name', $groupName)
            ->when($faculty, fn ($query) => $query->where(function ($query) use ($faculty): void {
                $query->whereNull('faculty')->orWhere('faculty', $faculty);
            }))
            ->first();
    }

    private function availableEmail(?string $email, ?User $user, string $platonusLogin): string
    {
        if ($email && (! $user || $this->emailIsAvailableFor($email, $user))) {
            return $email;
        }

        return 'platonus-'.sha1($platonusLogin).'@student-profile.local';
    }

    private function emailIsAvailableFor(string $email, User $user): bool
    {
        $query = User::query()->where('email', $email);

        if ($user->exists) {
            $query->whereKeyNot($user->getKey());
        }

        return ! $query->exists();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            $this->loginField() => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input($this->loginField())).'|'.$this->ip());
    }
}
