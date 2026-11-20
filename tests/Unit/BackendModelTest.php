<?php

namespace Tests\Unit;

use App\Models\Backend;
use Tests\TestCase;

class BackendModelTest extends TestCase
{
    public function test_resolved_path_normal(): void
    {
        $b = new Backend(['repo' => 'moonslayers/cef-backend', 'path' => null]);
        $this->assertEquals(base_path('../cef-backend'), $b->resolved_path);
    }

    public function test_resolved_path_monorepo_vdin(): void
    {
        $b = new Backend(['repo' => 'moonslayers/vdin', 'path' => 'vdin/vdin-api']);
        $this->assertEquals(base_path('../vdin/vdin-api'), $b->resolved_path);
    }
}
