<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HubStudentSurveyClient
{
    /**
     * @return array{configured: bool, ok: bool, message: ?string, surveys: array<int, array<string, mixed>>}
     */
    public function surveys(string $iin, string $locale): array
    {
        $url = trim((string) config('services.platonus.surveys_url'));
        $apiKey = trim((string) config('services.platonus.api_key'));

        if ($url === '' || $apiKey === '') {
            return $this->result(false, false, 'API анкет студентов не настроен.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->timeout((int) config('services.platonus.timeout', 15))
                ->get($url, [
                    'iin' => $iin,
                    'lang' => $locale === 'kk' ? 'kz' : 'ru',
                ]);
        } catch (ConnectionException) {
            return $this->result(true, false, 'Не удалось подключиться к API анкет студентов.');
        } catch (Throwable) {
            return $this->result(true, false, 'Ошибка при загрузке анкет студентов.');
        }

        if (! $response->successful()) {
            return $this->result(true, false, 'API анкет студентов вернул ошибку '.$response->status().'.');
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['surveys']) || ! is_array($data['surveys']) || ! array_is_list($data['surveys'])) {
            return $this->result(true, false, 'API анкет студентов вернул неожиданный формат данных.');
        }

        if (isset($data['iin']) && (string) $data['iin'] !== $iin) {
            return $this->result(true, false, 'API анкет студентов вернул данные другого студента.');
        }

        $surveys = collect($data['surveys'])
            ->filter(fn (mixed $survey): bool => is_array($survey))
            ->map(function (array $survey): array {
                $questions = $survey['questions'] ?? [];

                return [
                    'name' => $this->text($survey['survey_name'] ?? null),
                    'questions' => collect(is_array($questions) ? $questions : [])
                        ->filter(fn (mixed $item): bool => is_array($item))
                        ->map(fn (array $item): array => [
                            'question' => $this->text($item['question'] ?? null),
                            'answer' => $this->text($item['answer'] ?? null),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return $this->result(true, true, null, $surveys);
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<int, array<string, mixed>>  $surveys
     * @return array{configured: bool, ok: bool, message: ?string, surveys: array<int, array<string, mixed>>}
     */
    private function result(bool $configured, bool $ok, ?string $message, array $surveys = []): array
    {
        return compact('configured', 'ok', 'message', 'surveys');
    }
}
