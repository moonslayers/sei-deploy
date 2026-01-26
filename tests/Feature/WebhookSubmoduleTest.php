<?php

namespace Tests\Feature;

use App\Services\GitService;
use Tests\Helpers\WebhookHelper;
use Tests\TestCase;

class WebhookSubmoduleTest extends TestCase
{
    public function test_update_submodule_with_valid_signature(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->once()
            ->with('sei-compilados')
            ->andReturn(['Submodule updated successfully']);

        $data = WebhookHelper::createValidWebhookData([
            'repository' => 'sei-compilados',
            'branch' => 'main',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Submodule updated successfully',
                'data' => [
                    'repository' => 'sei-compilados',
                    'branch' => 'main',
                ],
            ]);
    }

    public function test_update_submodule_with_invalid_signature(): void
    {
        $data = WebhookHelper::createValidWebhookData([
            'repository' => 'sei-compilados',
            'branch' => 'main',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'invalid_signature_'.base64_encode('fake'),
        ], $signed['payload']);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid signature',
            ]);
    }

    public function test_update_submodule_without_signature(): void
    {
        $data = WebhookHelper::createValidWebhookData([
            'repository' => 'sei-compilados',
            'branch' => 'main',
        ]);

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Missing signature',
            ]);
    }

    public function test_update_submodule_with_invalid_repository(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->never();

        $data = WebhookHelper::createValidWebhookData([
            'repository' => 'invalid-repo',
            'branch' => 'main',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid repository',
                'message' => 'Only sei-compilados repository is supported',
            ]);
    }

    public function test_update_submodule_with_empty_payload(): void
    {
        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Empty payload',
            ]);
    }

    public function test_update_submodule_with_project_deploys_successfully(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->once()
            ->with('sei-compilados')
            ->andReturn(['Submodule updated successfully']);

        $gitServiceMock->shouldReceive('deployToFrontend')
            ->once()
            ->with('pmr-frontend')
            ->andReturn([
                'deployment' => 'success',
                'project' => 'pmr-frontend',
            ]);

        $data = WebhookHelper::createValidWebhookData([
            'repository' => 'sei-compilados',
            'branch' => 'main',
            'project' => 'pmr-frontend',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Submodule updated and deployed successfully',
                'data' => [
                    'repository' => 'sei-compilados',
                    'branch' => 'main',
                    'project' => 'pmr-frontend',
                ],
            ]);
    }

    public function test_update_submodule_with_nonexistent_project_returns_error(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->never();

        $gitServiceMock->shouldReceive('deployToFrontend')
            ->never();

        $data = WebhookHelper::createValidWebhookData([
            'repository' => 'sei-compilados',
            'branch' => 'main',
            'project' => 'nonexistent-frontend',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Project folder not found',
                'message' => "Folder 'nonexistent-frontend' does not exist in sei-compilados",
            ]);
    }

    public function test_update_submodule_without_project_skips_deployment(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->once()
            ->with('sei-compilados')
            ->andReturn(['Submodule updated successfully']);

        $gitServiceMock->shouldReceive('deployToFrontend')
            ->never();

        $data = WebhookHelper::createValidWebhookData([
            'repository' => 'sei-compilados',
            'branch' => 'main',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/submodule', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Submodule updated successfully',
                'data' => [
                    'repository' => 'sei-compilados',
                    'branch' => 'main',
                ],
            ]);
    }
}
