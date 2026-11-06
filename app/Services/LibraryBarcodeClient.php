<?php

namespace App\Services;

use App\Models\StudentServiceRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LibraryBarcodeClient
{
    public function register(
        StudentServiceRequest $request,
        CarbonInterface $birthDate,
        CarbonInterface $registrationDate,
        CarbonInterface $reregistrationDate,
    ): string {
        $status = config('services.library_barcode.statuses.'.$request->education_level);

        if (blank($status)) {
            throw new RuntimeException('Для этого уровня образования не настроен статус библиотеки.');
        }

        if (blank($request->photo_path) || ! Storage::disk('local')->exists($request->photo_path)) {
            throw new RuntimeException('Фото заявки не найдено.');
        }

        $url = (string) config('services.library_barcode.url');

        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('Адрес API библиотеки не настроен.');
        }

        $photo = $this->jpegPhoto(Storage::disk('local')->get($request->photo_path));

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.library_barcode.timeout', 30))
                ->post($url, [
                    'iin' => $request->iin,
                    'fio' => $request->full_name,
                    'birthday' => $birthDate->format('Y-m-d'),
                    'status' => $status,
                    'regdate' => $registrationDate->format('d-m-Y'),
                    'reregdate' => $reregistrationDate->format('d-m-Y'),
                    'bookpoints' => config('services.library_barcode.bookpoints'),
                    'custom1' => $request->faculty,
                    'custom2' => $request->specialty,
                    'ext' => 'JPEG',
                    'photo_hex' => strtoupper(bin2hex($photo)),
                ]);
        } catch (\Throwable) {
            throw new RuntimeException('Не удалось подключиться к библиотеке.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Библиотека не подтвердила создание штрихкода.');
        }

        $rdrId = trim((string) ($data['rdrId'] ?? ''));
        $iin = (string) ($data['iin'] ?? '');
        $alreadyExists = ($data['ok'] ?? null) === false
            && str_contains(mb_strtolower((string) ($data['error'] ?? '')), 'уже существует');

        if ((! $response->successful() && ! ($response->status() < 500 && $alreadyExists))
            || $iin !== $request->iin || ! preg_match('/^[A-Za-z0-9_-]{2,32}$/', $rdrId)
            || (($data['ok'] ?? null) !== true && ! $alreadyExists)) {
            throw new RuntimeException('Библиотека не вернула штрихкод для этого ИИН.');
        }

        return $rdrId;
    }

    private function jpegPhoto(string $contents): string
    {
        $info = @getimagesizefromstring($contents);

        if (($info['mime'] ?? null) === 'image/jpeg') {
            return $contents;
        }

        if (($info['mime'] ?? null) !== 'image/png') {
            throw new RuntimeException('Фото заявки должно быть JPG или PNG.');
        }

        $source = @imagecreatefromstring($contents);

        if (! $source) {
            throw new RuntimeException('Не удалось прочитать фото заявки.');
        }

        $image = imagecreatetruecolor(imagesx($source), imagesy($source));
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        imagecopy($image, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($image);

        if (! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Не удалось подготовить фото для библиотеки.');
        }

        return $jpeg;
    }
}
