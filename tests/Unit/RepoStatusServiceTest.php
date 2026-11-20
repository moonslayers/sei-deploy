<?php

namespace Tests\Unit;

use App\Models\Backend;
use App\Models\Frontend;
use App\Services\RepoStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepoStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_directory_returns_error_state(): void
    {
        $backend = new Backend(['repo' => 'org/missing']);
        $service = new RepoStatusService;
        $status = $service->backendStatus($backend);

        $this->assertFalse($status['exists']);
        $this->assertFalse($status['is_git']);
        $this->assertNotNull($status['error']);
        $this->assertFalse($status['dirty']);
    }

    public function test_parses_git_repo_clean(): void
    {
        $name = 'testrepo-'.uniqid();
        $dir = base_path('../'.$name);
        mkdir($dir, 0777, true);
        exec('cd '.escapeshellarg($dir).' && git init -q && git config user.email t@t.com && git config user.name t && echo hi > f.txt && git add . && git commit -q -m "initial commit"');

        $backend = new Backend(['repo' => 'org/'.$name]);
        $service = new RepoStatusService;
        $status = $service->backendStatus($backend);

        $this->assertTrue($status['exists']);
        $this->assertTrue($status['is_git']);
        $this->assertContains($status['branch'], ['master', 'main']);
        $this->assertNotNull($status['commit_hash']);
        $this->assertSame('initial commit', $status['commit_message']);
        $this->assertFalse($status['dirty']);

        // make dirty
        file_put_contents($dir.'/f.txt', "changed\n");
        $status2 = $service->backendStatus($backend);
        $this->assertTrue($status2['dirty']);

        // cleanup
        exec('rm -rf '.escapeshellarg($dir));
    }

    public function test_frontend_missing(): void
    {
        $frontend = new Frontend(['name' => 'nope-frontend-'.uniqid()]);
        $service = new RepoStatusService;
        $status = $service->frontendStatus($frontend);
        $this->assertFalse($status['exists']);
    }
}
