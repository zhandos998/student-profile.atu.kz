<?php

namespace App\Console\Commands;

use App\Models\StudentProfile;
use App\Services\PlatonusAuthClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStudentSpecialties extends Command
{
    protected $signature = 'student-specialties:sync
        {--dry-run : Check API values without changing profiles}
        {--limit=0 : Maximum number of profiles to check (0 = all)}
        {--after-id=0 : Continue after this student profile ID}
        {--delay-ms=100 : Delay between API requests in milliseconds}';

    protected $description = 'Replace student profile GOP specialties with Russian education program codes and names from the Hub API';

    public function handle(PlatonusAuthClient $client): int
    {
        if (blank(config('services.platonus.api_key')) || blank(config('services.platonus.student_full_url'))) {
            $this->error('Hub student full API is not configured.');

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $afterId = filter_var($this->option('after-id'), FILTER_VALIDATE_INT);
        $delayMs = filter_var($this->option('delay-ms'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 0 || $afterId === false || $afterId < 0 || $delayMs === false || $delayMs < 0 || $delayMs > 5000) {
            $this->error('Invalid --limit, --after-id, or --delay-ms value.');

            return self::FAILURE;
        }

        $counts = ['checked' => 0, 'updated' => 0, 'would_update' => 0, 'unchanged' => 0, 'invalid_iin' => 0, 'api_failed' => 0, 'invalid_data' => 0, 'exception' => 0];
        $lastId = $afterId;
        $dryRun = (bool) $this->option('dry-run');

        StudentProfile::query()
            ->select(['id', 'iin', 'specialty'])
            ->where('id', '>', $afterId)
            ->whereNotNull('iin')
            ->where('iin', '<>', '')
            ->chunkById(100, function ($profiles) use ($client, $limit, $delayMs, $dryRun, &$counts, &$lastId): bool {
                foreach ($profiles as $profile) {
                    $lastId = $profile->id;
                    $counts['checked']++;

                    try {
                        $result = $this->syncProfile($client, $profile, $dryRun);
                    } catch (Throwable $exception) {
                        $result = 'exception';
                        Log::error('Student specialty sync failed', [
                            'student_profile_id' => $profile->id,
                            'exception_type' => $exception::class,
                        ]);
                    }

                    $counts[$result]++;

                    if ($this->getOutput()->isVerbose() || $limit > 0 && $limit <= 10) {
                        $this->line("Profile {$profile->id}: {$result}.");
                    } elseif ($counts['checked'] % 100 === 0) {
                        $this->line("Checked {$counts['checked']}; last profile ID {$lastId}; updated {$counts['updated']}; API failures {$counts['api_failed']}.");
                    }

                    if ($limit > 0 && $counts['checked'] >= $limit) {
                        return false;
                    }

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }

                return true;
            });

        $this->info("Checked: {$counts['checked']}; updated: {$counts['updated']}; would update: {$counts['would_update']}; unchanged: {$counts['unchanged']}; invalid IIN: {$counts['invalid_iin']}; API failed: {$counts['api_failed']}; invalid API data: {$counts['invalid_data']}; exceptions: {$counts['exception']}; last profile ID: {$lastId}.");

        return $counts['exception'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function syncProfile(PlatonusAuthClient $client, StudentProfile $profile, bool $dryRun): string
    {
        if (! preg_match('/^\d{12}$/D', (string) $profile->iin)) {
            return 'invalid_iin';
        }

        $response = $client->studentFull((string) $profile->iin);

        if (! $response['ok']) {
            return 'api_failed';
        }

        $student = $response['student'];
        $code = trim((string) data_get($student, 'education.speciality_code'));
        $name = trim((string) data_get($student, 'education.speciality_name_ru'));
        $specialty = $code.' - '.$name;

        if (
            (string) data_get($student, 'iin') !== (string) $profile->iin
            || ! preg_match('/^[678][BMDВМД]\d{5,6}$/iu', $code)
            || $name === ''
            || mb_strlen($specialty) > 255
        ) {
            return 'invalid_data';
        }

        if ($profile->specialty === $specialty) {
            return 'unchanged';
        }

        if ($dryRun) {
            return 'would_update';
        }

        $profile->update(['specialty' => $specialty]);

        return 'updated';
    }
}
