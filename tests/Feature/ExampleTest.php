<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_renders_through_inertia(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('app.name', config('app.name'))
                ->where('auth.user', null)
            );
    }

    public function test_unknown_pages_show_the_error_page(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
    }
}
