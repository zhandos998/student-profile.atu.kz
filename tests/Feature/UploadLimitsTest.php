<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UploadLimitsTest extends TestCase
{
    public function test_request_exceeding_php_post_limit_has_a_readable_error_page(): void
    {
        $maxBytes = ini_parse_quantity(ini_get('post_max_size'));

        if ($maxBytes <= 0) {
            $this->markTestSkipped('PHP post_max_size is unlimited.');
        }

        $this->withServerVariables(['CONTENT_LENGTH' => (string) ($maxBytes + 1)])
            ->post('/services/requests')
            ->assertStatus(413)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 413)
            );
    }
}
