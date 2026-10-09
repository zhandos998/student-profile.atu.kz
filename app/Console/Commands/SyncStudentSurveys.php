<?php

namespace App\Console\Commands;

use App\Models\StudentProfile;
use App\Services\StudentSurveySynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStudentSurveys extends Command
{
    protected $signature = 'student-surveys:sync {--limit=0 : Maximum number of profiles to process (0 = all)} {--delay-ms=200 : Delay between API requests in milliseconds}';

    protected $description = 'Fetch and save current survey results for all student profiles with an IIN';

    public function handle(StudentSurveySynchronizer $synchronizer): int
    {
        if (blank(config('services.platonus.api_key')) || blank(config('services.platonus.surveys_url'))) {
            $this->error('Hub survey API is not configured.');

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $delayMs = filter_var($this->option('delay-ms'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 0 || $delayMs === false || $delayMs < 0 || $delayMs > 5000) {
            $this->error('Invalid --limit or --delay-ms value.');

            return self::FAILURE;
        }

        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $invalid = 0;

        StudentProfile::query()
            ->select(['id', 'iin'])
            ->whereNotNull('iin')
            ->where('iin', '<>', '')
            ->chunkById(100, function ($profiles) use ($synchronizer, $limit, $delayMs, &$processed, &$succeeded, &$failed, &$invalid): bool {
                foreach ($profiles as $profile) {
                    if (! preg_match('/^\d{12}$/D', (string) $profile->iin)) {
                        $invalid++;
                        continue;
                    }

                    $processed++;

                    try {
                        $result = $synchronizer->synchronize($profile, 'ru');
                        if ($result['ok']) {
                            $succeeded++;
                        } else {
                            $failed++;
                            Log::warning('Student survey sync API failed', [
                                'student_profile_id' => $profile->id,
                                'message' => $result['message'],
                            ]);
                        }
                    } catch (Throwable $exception) {
                        $failed++;
                        Log::error('Student survey sync failed', [
                            'student_profile_id' => $profile->id,
                            'exception_type' => $exception::class,
                        ]);
                    }

                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }

                return true;
            });

        $this->info("Processed: {$processed}; synchronized: {$succeeded}; failed: {$failed}; invalid IIN: {$invalid}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
