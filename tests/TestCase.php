<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles and permissions after the test database is migrated.
     *
     * @var bool
     */
    protected $seed = true;

    /**
     * @var class-string
     */
    protected $seeder = RolesAndPermissionsSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests check what the server sends, not the compiled JavaScript,
        // so they pass without running `npm run build` first.
        $this->withoutVite();
    }
}
