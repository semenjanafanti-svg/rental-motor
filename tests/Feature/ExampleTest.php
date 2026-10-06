<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_home_displays_the_dedicated_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('Motor siap jalan, urusan sewa kami bikin gampang.');
    }
}
