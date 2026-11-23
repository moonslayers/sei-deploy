<?php

namespace Tests\Feature;

use App\Models\Backend;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\TerminalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_resize_end_reject_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);
        $session = TerminalSession::create([
            'uuid' => '22222222-3333-4444-5555-666666666666',
            'backend_id' => $backend->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->post('/terminal/'.$session->uuid.'/input', ['input' => 'ls'])
            ->assertStatus(403);

        $this->actingAs($other)
            ->post('/terminal/'.$session->uuid.'/resize', ['cols' => 80, 'rows' => 24])
            ->assertStatus(403);

        $this->actingAs($other)
            ->delete('/terminal/'.$session->uuid)
            ->assertStatus(403);
    }

    public function test_non_admin_cannot_start_terminal(): void
    {
        config(['admin.emails' => ['admin@example.com']]);
        $user = User::factory()->create(['email' => 'user@example.com']);
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);

        $this->actingAs($user)
            ->post('/terminal/'.$backend->id.'/start')
            ->assertStatus(403);
    }

    public function test_start_returns_422_on_runtime_exception(): void
    {
        config(['admin.emails' => ['admin@example.com']]);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);

        $mock = $this->mock(TerminalService::class);
        $mock->shouldReceive('start')->andThrow(new \RuntimeException('tmux binary not found.'));

        $this->actingAs($user)
            ->post('/terminal/'.$backend->id.'/start')
            ->assertStatus(422)
            ->assertJson(['error' => 'tmux binary not found.']);
    }

    public function test_start_uuid_usable_in_input_route(): void
    {
        config(['admin.emails' => ['admin@example.com']]);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);

        $session = TerminalSession::create([
            'uuid' => 'cccccccc-dddd-eeee-ffff-000000000000',
            'backend_id' => $backend->id,
            'user_id' => $user->id,
        ]);

        $mock = $this->mock(TerminalService::class);
        $mock->shouldReceive('start')->andReturn($session);
        $mock->shouldReceive('write')->andReturnNull();

        $res = $this->actingAs($user)
            ->post('/terminal/'.$backend->id.'/start')
            ->assertStatus(200)
            ->json();

        $this->assertSame($session->uuid, $res['session']);

        $this->actingAs($user)
            ->post('/terminal/'.$res['session'].'/input', ['input' => 'ls'])
            ->assertStatus(200);
    }

    public function test_output_endpoint_owner_gets_content(): void
    {
        $owner = User::factory()->create();
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);
        $session = TerminalSession::create([
            'uuid' => '33333333-4444-5555-6666-777777777777',
            'backend_id' => $backend->id,
            'user_id' => $owner->id,
        ]);

        $dir = storage_path('app/terminal');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.DIRECTORY_SEPARATOR.$session->uuid.'.log', 'hello output');

        $this->actingAs($owner)
            ->get('/terminal/'.$session->uuid.'/output')
            ->assertStatus(200)
            ->assertJson(['output' => 'hello output']);
    }

    public function test_output_endpoint_non_owner_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);
        $session = TerminalSession::create([
            'uuid' => '44444444-5555-6666-7777-888888888888',
            'backend_id' => $backend->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get('/terminal/'.$session->uuid.'/output')
            ->assertStatus(403);
    }

    public function test_output_endpoint_unknown_uuid_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/terminal/00000000-0000-0000-0000-000000000000/output')
            ->assertStatus(404);
    }

    public function test_enter_carriage_return_reaches_service_intact(): void
    {
        $user = User::factory()->create();
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);
        $session = TerminalSession::create([
            'uuid' => '55555555-6666-7777-8888-999999999999',
            'backend_id' => $backend->id,
            'user_id' => $user->id,
        ]);

        $mock = $this->mock(TerminalService::class);
        $mock->shouldReceive('write')
            ->once()
            ->withArgs(function ($s, $input, $u) use ($session) {
                return $s->uuid === $session->uuid && $input === "\r";
            });

        $this->actingAs($user)
            ->post('/terminal/'.$session->uuid.'/input', ['input' => "\r"])
            ->assertStatus(200);
    }

    public function test_output_endpoint_partial_offset_returns_new_bytes(): void
    {
        $owner = User::factory()->create();
        $backend = Backend::create(['repo' => 'org/repo', 'path' => null]);
        $session = TerminalSession::create([
            'uuid' => '66666666-7777-8888-9999-aaaaaaaaaaaa',
            'backend_id' => $backend->id,
            'user_id' => $owner->id,
        ]);

        $dir = storage_path('app/terminal');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.DIRECTORY_SEPARATOR.$session->uuid.'.log', 'hello output');

        $res = $this->actingAs($owner)
            ->get('/terminal/'.$session->uuid.'/output?offset=6')
            ->assertStatus(200)
            ->json();

        $this->assertSame('output', $res['output']);
        $this->assertSame(12, $res['offset']);
    }
}
