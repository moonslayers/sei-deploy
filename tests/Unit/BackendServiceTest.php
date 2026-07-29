<?php

namespace Tests\Unit;

use App\Services\BackendService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BackendServiceTest extends TestCase
{
    private function invokeGetMigrateCommand(BackendService $service, string $repoFullName): string
    {
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getMigrateCommand');

        return $method->invoke($service, $repoFullName);
    }

    public function test_get_migrate_command_returns_default_for_unconfigured_backend(): void
    {
        Config::set('sei.backend_commands', [
            'moonslayers/vdin' => 'migrate:fresh --seed',
        ]);

        $service = app(BackendService::class);
        $result = $this->invokeGetMigrateCommand($service, 'moonslayers/cef-backend');

        $this->assertEquals('migrate --force', $result);
    }

    public function test_get_migrate_command_returns_custom_command_for_configured_backend(): void
    {
        Config::set('sei.backend_commands', [
            'moonslayers/vdin' => 'migrate:fresh --seed',
        ]);

        $service = app(BackendService::class);
        $result = $this->invokeGetMigrateCommand($service, 'moonslayers/vdin');

        $this->assertEquals('migrate:fresh --seed', $result);
    }

    public function test_get_migrate_command_returns_default_when_no_commands_configured(): void
    {
        Config::set('sei.backend_commands', []);

        $service = app(BackendService::class);
        $result = $this->invokeGetMigrateCommand($service, 'moonslayers/cef-backend');

        $this->assertEquals('migrate --force', $result);
    }
}
