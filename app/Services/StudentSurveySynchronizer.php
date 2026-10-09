<?php

namespace App\Services;

use App\Models\StudentProfile;

class StudentSurveySynchronizer
{
    public function __construct(
        private readonly HubStudentSurveyClient $studentSurveys,
        private readonly StudentSurveyResultService $surveyResultService,
        private readonly StudentSurveyResultStore $surveyResultStore,
    ) {}

    /** @return array<string, mixed> */
    public function synchronize(?StudentProfile $profile, string $locale = 'ru'): array
    {
        $iin = trim((string) $profile?->iin);

        if ($iin === '') {
            return [
                'iin' => '',
                'configured' => true,
                'ok' => false,
                'message' => 'У студента не указан ИИН. Анкеты нельзя загрузить.',
                'cached' => false,
                'cached_at' => null,
                'results' => [],
            ];
        }

        $response = $this->studentSurveys->surveys($iin, $locale);
        $results = $response['ok']
            ? $this->surveyResultService->summarizeAll($response['surveys'], $locale)
            : [];

        if ($locale === 'kk' && collect($results)->contains(
            fn (array $result): bool => str_contains((string) $result['name'], 'HADS') && $result['status'] === 'incomplete'
        )) {
            $russian = $this->studentSurveys->surveys($iin, 'ru');
            $russianHads = collect($russian['surveys'])->first(
                fn (array $survey): bool => str_contains((string) $survey['name'], 'HADS')
            );

            if ($russian['ok'] && $russianHads) {
                $fallback = $this->surveyResultService->summarize($russianHads);
                if ($fallback['status'] === 'calculated') {
                    foreach ($results as &$result) {
                        if (str_contains((string) $result['name'], 'HADS') && $result['status'] === 'incomplete') {
                            $result = [
                                'key' => $result['key'],
                                'name' => $result['name'],
                                'status' => $fallback['status'],
                                'metrics' => $fallback['metrics'],
                                'message' => $fallback['message'],
                            ];
                        }
                    }
                    unset($result);
                }
            }
        }

        if ($response['ok'] && $profile) {
            $this->surveyResultStore->synchronize($profile, $iin, $results);
        }

        $stored = ! $response['ok'] && $profile
            ? $this->surveyResultStore->latest($profile, $iin)
            : ['results' => [], 'updated_at' => null];

        return [
            'iin' => $iin,
            'configured' => $response['configured'],
            'ok' => $response['ok'],
            'message' => $response['message'],
            'cached' => ! $response['ok'] && $stored['results'] !== [],
            'cached_at' => $stored['updated_at'],
            'results' => $response['ok'] ? $results : $stored['results'],
        ];
    }
}
