<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_locale(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'kk'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'kk');
    }

    public function test_inertia_shares_current_locale(): void
    {
        $this->withSession(['locale' => 'kk'])
            ->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'kk')
                ->where('availableLocales.0.value', 'ru')
                ->where('availableLocales.1.value', 'kk')
            );
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertSessionHasErrors('locale');
    }
}
