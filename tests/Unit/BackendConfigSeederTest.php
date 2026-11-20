<?php

namespace Tests\Unit;

use App\Models\Backend;
use App\Models\Frontend;
use Database\Seeders\BackendConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendConfigSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_idempotent_and_preserves_script(): void
    {
        $this->seed(BackendConfigSeeder::class);
        $firstCount = Backend::count();
        $expectedBackends = count(config('sei.backends'));
        $expectedFrontends = count(config('sei.frontends'));
        $this->assertEquals($expectedBackends, $firstCount);
        $this->assertEquals($expectedFrontends, Frontend::count());

        $backend = Backend::where('repo', 'moonslayers/vdin')->first();
        $backend->update(['script' => 'custom script']);
        $this->assertEquals('custom script', $backend->fresh()->script);

        $this->seed(BackendConfigSeeder::class);

        $this->assertEquals($firstCount, Backend::count());
        $this->assertEquals($expectedFrontends, Frontend::count());
        $this->assertEquals('custom script', Backend::where('repo', 'moonslayers/vdin')->first()->script);
    }
}
