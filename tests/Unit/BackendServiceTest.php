<?php

namespace Tests\Unit;

use App\Models\Backend;
use App\Services\BackendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendServiceTest extends TestCase
{
    use RefreshDatabase;

    private function invokeGetMigrateCommand(BackendService $service, string $repoFullName): string
    {
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getMigrateCommand');

        return $method->invoke($service, $repoFullName);
    }

    private function invokeGetDeployScript(BackendService $service, string $repoFullName): string
    {
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getDeployScript');

        return $method->invoke($service, $repoFullName);
    }

    public function test_get_migrate_command_returns_default_for_unconfigured_backend(): void
    {
        $service = app(BackendService::class);
        $result = $this->invokeGetMigrateCommand($service, 'moonslayers/cef-backend');

        $this->assertEquals('migrate --force', $result);
    }

    public function test_get_migrate_command_returns_custom_command_for_configured_backend(): void
    {
        $service = app(BackendService::class);
        $result = $this->invokeGetMigrateCommand($service, 'moonslayers/vdin');

        $this->assertEquals('migrate:fresh --seed', $result);
    }

    public function test_get_migrate_command_returns_default_when_no_commands_configured(): void
    {
        $service = app(BackendService::class);
        $result = $this->invokeGetMigrateCommand($service, 'moonslayers/cef-backend');

        $this->assertEquals('migrate --force', $result);
    }

    public function test_get_deploy_script_throws_clear_error_when_template_missing_and_no_own_script(): void
    {
        Backend::create(['repo' => 'foo/bar', 'path' => null, 'script' => null]);

        $template = base_path('scripts/deploy-backend.sh');
        $backup = $template.'.bak';
        rename($template, $backup);

        try {
            $service = app(BackendService::class);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Deploy template missing');

            $this->invokeGetDeployScript($service, 'foo/bar');
        } finally {
            if (file_exists($backup)) {
                rename($backup, $template);
            }
        }
    }

    public function test_get_deploy_script_uses_own_script_even_if_template_missing(): void
    {
        Backend::create(['repo' => 'foo/bar', 'path' => null, 'script' => "#!/bin/bash\necho ok"]);

        $template = base_path('scripts/deploy-backend.sh');
        $backup = $template.'.bak';
        rename($template, $backup);

        try {
            $service = app(BackendService::class);
            $result = $this->invokeGetDeployScript($service, 'foo/bar');

            $this->assertSame("#!/bin/bash\necho ok", $result);
        } finally {
            if (file_exists($backup)) {
                rename($backup, $template);
            }
        }
    }
}
