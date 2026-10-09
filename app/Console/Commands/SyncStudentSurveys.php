<?php

namespace App\Console\Commands;

use App\Models\StudentProfile;
use App\Services\StudentSurveyResultStore;
use App\Services\StudentSurveySynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncStudentSurveys extends Command
{
    protected $signature = 'student-surveys:sync {--limit=0 : Maximum number of profiles to fetch (0 = all)} {--delay-ms=200 : Delay between API requests in milliseconds} {--force : Refresh profiles with complete saved results}';

    protected $description = 'Fetch and save current survey results for all student profiles with an IIN';

    public function handle(StudentSurveySynchronizer $synchronizer, StudentSurveyResultStore $store): int
    {
        if (blank(config('services.platonus.api_key')) || blank(config('services.platonus.surveys_url'))) {
            $this->error('Hub survey API is not configured.');

            return self::FAILURE;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $delayMs = filter_var($this->option('delay-ms'), FILTER_VALIDATE_INT);
        $force = (bool) $this->option('force');

        if ($limit === false || $limit < 0 || $delayMs === false || $delayMs < 0 || $delayMs > 5000) {
            $this->error('Invalid --limit or --delay-ms value.');

            return self::FAILURE;
        }

        $processed = 0;
        $apiOk = 0;
        $withCalculatedTests = 0;
        $calculatedTests = 0;
        $completeAfterSync = 0;
        $failed = 0;
        $invalid = 0;
        $skipped = 0;

        StudentProfile::query()
            ->select(['id', 'iin'])
            ->whereNotNull('iin')
            ->where('iin', '<>', '')
            ->chunkById(100, function ($profiles) use ($synchronizer, $store, $limit, $delayMs, $force, &$processed, &$apiOk, &$withCalculatedTests, &$calculatedTests, &$completeAfterSync, &$failed, &$invalid, &$skipped): bool {
                foreach ($profiles as $profile) {
                    if (! preg_match('/^\d{12}$/D', (string) $profile->iin)) {
                        $invalid++;
                        continue;
                    }

                    try {
                        $result = $synchronizer->synchronize($profile, 'ru', $force);
                        if ($result['skipped'] ?? false) {
                            $skipped++;
                            if ($limit === 1 || $this->getOutput()->isVerbose()) {
                                $this->line("Profile {$profile->id}: skipped, saved tests {$store->expectedTestCount()}/{$store->expectedTestCount()}.");
                            }
                            continue;
                        }

                        $processed++;
                        if ($result['ok']) {
                            $apiOk++;
                            $calculated = collect($result['results'])
                                ->filter(fn (array $survey): bool => $survey['status'] === 'calculated')
                                ->count();
                            $saved = $store->completedTestCount($profile, (string) $profile->iin);
                            $calculatedTests += $calculated;
                            $withCalculatedTests += (int) ($calculated > 0);
                            $completeAfterSync += (int) ($saved === $store->expectedTestCount());

                            if ($limit === 1 || $this->getOutput()->isVerbose()) {
                                $this->line("Profile {$profile->id}: API OK; calculated tests in response {$calculated}; saved complete tests {$saved}/{$store->expectedTestCount()}.");
                            }
                        } else {
                            $failed++;
                            if ($limit === 1 || $this->getOutput()->isVerbose()) {
                                $this->warn("Profile {$profile->id}: API failed: {$result['message']}");
                            }
                            Log::warning('Student survey sync API failed', [
                                'student_profile_id' => $profile->id,
                                'message' => $result['message'],
                            ]);
                        }
                    } catch (Throwable $exception) {
                        $processed++;
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

        $this->info("Processed: {$processed}; API OK: {$apiOk}; profiles with calculated tests: {$withCalculatedTests}; calculated tests in responses: {$calculatedTests}; complete after sync: {$completeAfterSync}; skipped complete: {$skipped}; failed: {$failed}; invalid IIN: {$invalid}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
