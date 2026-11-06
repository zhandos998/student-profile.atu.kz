<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class HikvisionFaceIdClient
{
    public function submit(array $student, UploadedFile $photo): string
    {
        $orgCode = $this->orgCode($student);
        $photoData = $photo->get();

        if ($photoData === false || $photoData === '') {
            throw new RuntimeException('Не удалось прочитать фото для Face ID.');
        }

        $faceData = base64_encode($photoData);
        $year = now()->year;
        $payload = [
            'personCode' => $student['iin'],
            'personFamilyName' => $student['last_name'],
            'personGivenName' => $student['first_name'],
            'gender' => 1,
            'beginTime' => "{$year}-01-01T15:00:00+08:00",
            'endTime' => ($year + 10).'-01-01T15:00:00+08:00',
            'orgIndexCode' => $orgCode,
            'faces' => [['faceData' => $faceData]],
        ];

        $response = $this->post('/artemis/api/resource/v1/person/single/add', $payload);

        if ($response['code'] === 0) {
            return $orgCode;
        }

        if ($response['code'] !== 131) {
            throw new RuntimeException('Hikvision отклонил заявку Face ID (код '.$response['code'].').');
        }

        $person = $this->post('/artemis/api/resource/v1/person/personCode/personInfo', [
            'personCode' => $student['iin'],
        ]);
        $personId = $person['data']['personId'] ?? null;

        if ($person['code'] !== 0 || ! is_scalar($personId) || (string) $personId === '') {
            throw new RuntimeException('Не удалось найти существующую запись Face ID в Hikvision.');
        }

        $updated = $this->post('/artemis/api/resource/v1/person/face/update', [
            'personId' => $personId,
            'faceData' => $faceData,
        ]);

        if ($updated['code'] !== 0) {
            throw new RuntimeException('Не удалось обновить фото Face ID в Hikvision.');
        }

        return $orgCode;
    }

    private function orgCode(array $student): string
    {
        $level = $student['education_level'];
        $year = (int) $student['admission_year'];
        $code = $level === 'bachelor'
            ? config('hikvision.org_codes.bachelor.'.$student['faculty'].'.'.$year)
            : config('hikvision.org_codes.'.$level.'.'.$year);

        if (! is_numeric($code)) {
            throw new RuntimeException('Для этого факультета, уровня образования и года поступления не настроен код Hikvision.');
        }

        return (string) $code;
    }

    private function post(string $path, array $payload): array
    {
        $baseUrl = rtrim((string) config('hikvision.base_url'), '/');
        $appKey = (string) config('hikvision.app_key');
        $appSecret = (string) config('hikvision.app_secret');

        if (! str_starts_with($baseUrl, 'https://') || $appKey === '' || $appSecret === '') {
            throw new RuntimeException('Подключение к Hikvision не настроено.');
        }

        $stringToSign = "POST\n*/*\napplication/json\nx-ca-key:{$appKey}\n{$path}";
        $verify = app()->environment('local') && config('hikvision.verify_tls') === false
            ? false
            : (config('hikvision.ca_bundle') ?: true);

        try {
            $response = Http::withOptions(['verify' => $verify])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => '*/*',
                    'X-Ca-Key' => $appKey,
                    'x-ca-signature-headers' => 'x-ca-key',
                    'x-ca-signature' => base64_encode(hash_hmac('sha256', $stringToSign, $appSecret, true)),
                    'x-ca-timestamp' => (string) round(microtime(true) * 1000),
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->asJson()
                ->timeout((int) config('hikvision.timeout', 20))
                ->post($baseUrl.$path, $payload);
        } catch (Throwable) {
            throw new RuntimeException('Не удалось подключиться к Hikvision.');
        }

        return $this->responseData($response);
    }

    private function responseData(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data) || ! isset($data['code']) || ! is_numeric($data['code'])) {
            throw new RuntimeException('Hikvision вернул некорректный ответ.');
        }

        if (! $response->successful() && ! ((int) $data['code'] === 131 && $response->status() < 500)) {
            throw new RuntimeException('Hikvision не принял запрос (HTTP '.$response->status().').');
        }

        $data['code'] = (int) $data['code'];

        return $data;
    }
}
