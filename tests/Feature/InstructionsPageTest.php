<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstructionsPageTest extends TestCase
{
    public function test_guest_can_open_instructions_page(): void
    {
        $this->get(route('instructions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Instructions')
            );
    }
}
