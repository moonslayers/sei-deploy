<?php

namespace Tests\Unit;

use App\Models\Backend;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\TerminalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TerminalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_happy_path_creates_session_log_and_pipe_pane(): void
    {
        Log::spy();
        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;

            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();

        $session = $service->start($backend, $user);

        $this->assertNotNull($session->id);
        $logFile = storage_path('app/terminal/'.$session->uuid.'.log');
        $this->assertFileExists($logFile);

        $this->assertTrue(collect($commands)->contains(fn ($c) => str_contains($c, 'tmux new-session')));
        $this->assertTrue(collect($commands)->contains(fn ($c) => str_contains($c, 'tmux pipe-pane')));
        $pipe = collect($commands)->first(fn ($c) => str_contains($c, 'tmux pipe-pane'));
        $this->assertStringContainsString('cat >> '.$logFile, $pipe);
    }

    public function test_write_escapes_user_payload(): void
    {
        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;

            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();
        $session = TerminalSession::create([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'backend_id' => $backend->id,
            'user_id' => $user->id,
        ]);

        $payload = 'echo "hi"; rm -rf /';
        $service->write($session, $payload, $user);

        $send = collect($commands)->first(fn ($c) => str_contains($c, 'send-keys') && str_contains($c, '-l'));
        $this->assertNotNull($send);
        $this->assertStringContainsString(escapeshellarg($payload), $send);
        $this->assertStringNotContainsString($payload, str_replace(escapeshellarg($payload), '', $send));
    }

    public function test_start_logs_warning_when_mkdir_fails(): void
    {
        Log::spy();
        $terminalPath = storage_path('app/terminal');
        if (is_dir($terminalPath)) {
            foreach (glob($terminalPath.'/*') as $f) {
                @unlink($f);
            }
            @rmdir($terminalPath);
        }
        if (file_exists($terminalPath)) {
            @unlink($terminalPath);
        }
        @file_put_contents($terminalPath, 'blocker');

        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;

            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();

        try {
            $service->start($backend, $user);
        } catch (\Throwable $e) {
            // ignore
        }

        Log::shouldHaveReceived('warning')
            ->with('TerminalService: failed to create terminal log directory', \Mockery::on(function ($ctx) use ($terminalPath) {
                return ($ctx['log_dir'] ?? null) === $terminalPath;
            }))
            ->atLeast()->once();

        @unlink($terminalPath);
    }

    public function test_start_logs_warning_when_pipe_pane_fails(): void
    {
        Log::spy();
        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;
            if (str_contains($command, 'pipe-pane')) {
                return [1, ''];
            }

            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();

        $session = $service->start($backend, $user);

        Log::shouldHaveReceived('warning')
            ->with('TerminalService: failed to configure tmux pipe-pane', \Mockery::on(function ($ctx) {
                return isset($ctx['tmux_session']) && str_starts_with($ctx['tmux_session'], 'sei-');
            }))
            ->once();
    }
}
