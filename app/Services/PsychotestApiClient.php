<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PsychotestApiClient
{
    /**
     * @param  array<int, string|int>  $testIds
     * @return array{
     *     configured: bool,
     *     ok: bool,
     *     status: int|null,
     *     message: string|null,
     *     user: mixed,
     *     results: array<int, mixed>,
     *     raw: mixed
     * }
     */
    public function testResults(string $iin, array $testIds): array
    {
        $baseUrl = trim((string) config('services.psychotest.base_url'));
        $token = trim((string) config('services.psychotest.token'));

        if ($baseUrl === '' || $token === '') {
            return $this->response(false, false, null, 'API психотестов не настроен. Укажите PSYCHOTEST_API_URL и PSYCHOTEST_API_TOKEN в .env.');
        }

        $testIds = collect($testIds)
            ->map(fn (string|int $id): string => trim((string) $id))
            ->filter()
            ->values()
            ->all();

        try {
            $query = $testIds === []
                ? []
                : ['test_ids' => implode(',', $testIds)];

            $response = Http::acceptJson()
                ->withHeaders(['X-API-TOKEN' => $token])
                ->timeout(15)
                ->get($this->url($baseUrl, $iin), $query);
        } catch (ConnectionException) {
            return $this->response(true, false, null, 'Не удалось подключиться к API психотестов.');
        } catch (Throwable) {
            return $this->response(true, false, null, 'Ошибка при запросе к API психотестов.');
        }

        $raw = $response->json();

        if (! $response->successful()) {
            return $this->response(true, false, $response->status(), $this->errorMessage($raw, $response->status()), [], $raw);
        }

        $normalized = $this->normalizeResponse($raw);

        return $this->response(true, true, $response->status(), null, $normalized['results'], $raw, $normalized['user']);
    }

    private function url(string $baseUrl, string $iin): string
    {
        if (! Str::startsWith($baseUrl, ['http://', 'https://'])) {
            $baseUrl = 'http://'.$baseUrl;
        }

        return rtrim($baseUrl, '/').'/api/students/'.rawurlencode($iin).'/test-results';
    }

    /**
     * @param  array<int, mixed>  $results
     * @return array{
     *     configured: bool,
     *     ok: bool,
     *     status: int|null,
     *     message: string|null,
     *     user: mixed,
     *     results: array<int, mixed>,
     *     raw: mixed
     * }
     */
    private function response(
        bool $configured,
        bool $ok,
        ?int $status,
        ?string $message,
        array $results = [],
        mixed $raw = null,
        mixed $user = null,
    ): array {
        return compact('configured', 'ok', 'status', 'message', 'user', 'results', 'raw');
    }

    /**
     * @return array{user: mixed, results: array<int, mixed>}
     */
    private function normalizeResponse(mixed $raw): array
    {
        if (! is_array($raw)) {
            return ['user' => null, 'results' => []];
        }

        if (array_is_list($raw)) {
            return ['user' => null, 'results' => $raw];
        }

        $tests = $raw['tests'] ?? $raw['data'] ?? $raw['results'] ?? [];

        return [
            'user' => $raw['user'] ?? null,
            'results' => is_array($tests) && array_is_list($tests) ? $tests : [],
        ];
    }

    private function errorMessage(mixed $raw, int $status): string
    {
        if (is_array($raw)) {
            foreach (['message', 'error', 'detail'] as $field) {
                if (isset($raw[$field]) && is_string($raw[$field]) && $raw[$field] !== '') {
                    return $raw[$field];
                }
            }
        }

        return Str::of('API психотестов вернул ошибку :status.')
            ->replace(':status', (string) $status)
            ->toString();
    }
}
