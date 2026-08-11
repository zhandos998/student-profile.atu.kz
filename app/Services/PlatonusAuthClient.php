<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PlatonusAuthClient
{
    /**
     * @return array{
     *     configured: bool,
     *     ok: bool,
     *     status: int|null,
     *     message: string|null,
     *     student: array<string, mixed>,
     *     raw: mixed
     * }
     */
    public function verify(string $login, string $password): array
    {
        $url = trim((string) config('services.platonus.verify_url'));
        $apiKey = trim((string) config('services.platonus.api_key'));

        if ($url === '' || $apiKey === '') {
            return $this->response(false, false, null, __('auth.platonus_not_configured'));
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->timeout((int) config('services.platonus.timeout', 15))
                ->post($url, [
                    'login' => $login,
                    'password' => $password,
                ]);
        } catch (ConnectionException) {
            return $this->response(true, false, null, __('auth.platonus_connection_failed'));
        } catch (Throwable) {
            return $this->response(true, false, null, __('auth.platonus_request_failed'));
        }

        $raw = $response->json();

        if (! $response->successful()) {
            return $this->response(true, false, $response->status(), $this->errorMessage($raw, $response->status()), [], $raw);
        }

        if (! $this->isAuthenticated($raw)) {
            return $this->response(
                true,
                false,
                $response->status(),
                $this->errorMessage($raw, $response->status(), __('auth.platonus_failed')),
                [],
                $raw,
            );
        }

        return $this->response(
            true,
            true,
            $response->status(),
            null,
            $this->studentData($raw),
            $raw,
        );
    }

    /**
     * @return array{
     *     configured: bool,
     *     ok: bool,
     *     status: int|null,
     *     message: string|null,
     *     student: array<string, mixed>,
     *     raw: mixed
     * }
     */
    public function studentFull(string $iin): array
    {
        $url = trim((string) config('services.platonus.student_full_url'));
        $apiKey = trim((string) config('services.platonus.api_key'));

        if ($url === '' || $apiKey === '') {
            return $this->response(false, false, null, __('auth.platonus_student_full_not_configured'));
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->timeout((int) config('services.platonus.timeout', 15))
                ->get($url, ['iin' => $iin]);
        } catch (ConnectionException) {
            return $this->response(true, false, null, __('auth.platonus_student_full_connection_failed'));
        } catch (Throwable) {
            return $this->response(true, false, null, __('auth.platonus_student_full_request_failed'));
        }

        $raw = $response->json();

        if (! $response->successful()) {
            return $this->response(true, false, $response->status(), $this->errorMessage($raw, $response->status()), [], $raw);
        }

        return $this->response(
            true,
            true,
            $response->status(),
            null,
            $this->studentData($raw),
            $raw,
        );
    }

    /**
     * @param  array<string, mixed>  $student
     * @return array{
     *     configured: bool,
     *     ok: bool,
     *     status: int|null,
     *     message: string|null,
     *     student: array<string, mixed>,
     *     raw: mixed
     * }
     */
    private function response(
        bool $configured,
        bool $ok,
        ?int $status,
        ?string $message,
        array $student = [],
        mixed $raw = null,
    ): array {
        return compact('configured', 'ok', 'status', 'message', 'student', 'raw');
    }

    private function isAuthenticated(mixed $raw): bool
    {
        if (! is_array($raw)) {
            return true;
        }

        foreach (['authenticated', 'success', 'ok', 'verified', 'valid'] as $field) {
            if (array_key_exists($field, $raw)) {
                return filter_var($raw[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (isset($raw['status']) && is_string($raw['status'])) {
            return in_array(Str::lower($raw['status']), ['ok', 'success', 'authenticated', 'verified'], true);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function studentData(mixed $raw): array
    {
        if (! is_array($raw) || array_is_list($raw)) {
            return [];
        }

        foreach (['student', 'data', 'user', 'result'] as $field) {
            if (isset($raw[$field]) && is_array($raw[$field]) && ! array_is_list($raw[$field])) {
                return $raw[$field];
            }
        }

        return $raw;
    }

    private function errorMessage(mixed $raw, int $status, ?string $fallback = null): string
    {
        if (is_array($raw)) {
            foreach (['message', 'error', 'detail'] as $field) {
                if (isset($raw[$field]) && is_string($raw[$field]) && $raw[$field] !== '') {
                    return $raw[$field];
                }
            }
        }

        return $fallback ?? __('auth.platonus_error', ['status' => $status]);
    }
}
