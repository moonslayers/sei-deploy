<?php

namespace Tests\Unit;

use App\Models\Backend;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\TerminalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $this->assertTrue(collect($commands)->contains(fn ($c) => str_contains($c, 'new-session')));
        $this->assertTrue(collect($commands)->contains(fn ($c) => str_contains($c, 'pipe-pane')));
        $pipe = collect($commands)->first(fn ($c) => str_contains($c, 'pipe-pane'));
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

    // appended
    public function test_conf_absent_is_created(): void
    {
        $conf = storage_path('app/terminal/tmux.conf');
        @unlink($conf);

        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;
            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();

        $service->start($backend, $user);

        $this->assertFileExists($conf);
        $this->assertStringContainsString('set -g default-shell /bin/bash', (string) file_get_contents($conf));
    }

    public function test_is_alive_false_when_closed(): void
    {
        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();
        $session = TerminalSession::create([
            'uuid' => '99999999-aaaa-bbbb-cccc-dddddddddddd',
            'backend_id' => $backend->id,
            'user_id' => $user->id,
            'closed_at' => now(),
        ]);

        $this->assertFalse(TerminalService::isAlive($session));
    }

    public function test_stale_session_killed_before_new_session(): void
    {
        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;
            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();

        $session = $service->start($backend, $user);
        $name = 'sei-'.$session->uuid;

        $killIdx = null;
        $newIdx = null;
        foreach ($commands as $i => $c) {
            if (str_contains($c, 'kill-session') && str_contains($c, $name)) {
                $killIdx = $i;
            }
            if (str_contains($c, 'new-session') && str_contains($c, $name)) {
                $newIdx = $i;
            }
        }
        $this->assertNotNull($killIdx);
        $this->assertNotNull($newIdx);
        $this->assertLessThan($newIdx, $killIdx);
    }

    public function test_multi_session_names_isolated(): void
    {
        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;
            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend1 = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $backend2 = Backend::create(['repo' => 'org/repo2', 'path' => 'sei-deploy']);
        $user = User::factory()->create();

        Str::createUuidsUsingSequence([
            '11111111-2222-3333-4444-555555555555',
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $s1 = $service->start($backend1, $user);
        $s2 = $service->start($backend2, $user);

        Str::createUuidsNormally();

        $this->assertNotSame($s1->uuid, $s2->uuid);
        $this->assertTrue(collect($commands)->contains(fn ($c) => str_contains($c, 'sei-'.$s1->uuid)));
        $this->assertTrue(collect($commands)->contains(fn ($c) => str_contains($c, 'sei-'.$s2->uuid)));
    }

    public function test_tmux_prefix_present_in_all_commands(): void
    {
        $commands = [];
        $runner = function (string $command) use (&$commands): array {
            $commands[] = $command;
            return [0, ''];
        };
        $service = new TerminalService($runner);

        $backend = Backend::create(['repo' => 'org/repo', 'path' => 'sei-deploy']);
        $user = User::factory()->create();
        $service->start($backend, $user);

        foreach ($commands as $c) {
            if (str_contains($c, 'command -v tmux')) {
                continue;
            }
            $this->assertStringContainsString('tmux -f', $c);
            $this->assertTrue(str_contains($c, '-L') && str_contains($c, 'sei-terminal'));
        }
    }
}
