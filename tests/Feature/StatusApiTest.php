<?php

namespace Tests\Feature;

use App\Services\GitService;
use Tests\TestCase;

class StatusApiTest extends TestCase
{
    public function test_status_endpoint_returns_success(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('getCurrentBranch')
            ->once()
            ->andReturn('main');
        $gitServiceMock->shouldReceive('getStatus')
            ->once()
            ->andReturn(['On branch main']);

        $response = $this->getJson('/api/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'current_branch' => 'main',
                    'git_status' => ['On branch main'],
                ],
            ]);
    }

    public function test_status_endpoint_handles_git_service_exception(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('getCurrentBranch')
            ->once()
            ->andThrow(new \Exception('Git command failed'));

        $response = $this->getJson('/api/status');

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to get status',
            ]);
    }

    public function test_status_endpoint_returns_correct_structure(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('getCurrentBranch')
            ->once()
            ->andReturn('main');
        $gitServiceMock->shouldReceive('getStatus')
            ->once()
            ->andReturn(['Clean working directory']);

        $response = $this->getJson('/api/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'current_branch',
                    'git_status',
                ],
            ]);
    }

    public function test_status_endpoint_with_different_branch(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('getCurrentBranch')
            ->once()
            ->andReturn('develop');
        $gitServiceMock->shouldReceive('getStatus')
            ->once()
            ->andReturn(['On branch develop']);

        $response = $this->getJson('/api/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'current_branch' => 'develop',
                ],
            ]);
    }
}
