<?php

namespace Tests\Feature;

use App\Services\GitService;
use Tests\Helpers\WebhookHelper;
use Tests\TestCase;

class WebhookDeployTest extends TestCase
{
    public function test_update_deploy_with_valid_signature(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('pullMainRepo')
            ->once()
            ->andReturn(['Pull successful']);
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->once()
            ->with('sei-compilados')
            ->andReturn(['Submodule updated successfully']);

        $data = WebhookHelper::createValidWebhookData([
            'branch' => 'main',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/deploy', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Deploy updated successfully',
                'data' => [
                    'branch' => 'main',
                ],
            ]);
    }

    public function test_update_deploy_with_invalid_signature(): void
    {
        $data = WebhookHelper::createValidWebhookData([
            'branch' => 'main',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/deploy', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'invalid_signature_'.base64_encode('fake'),
        ], $signed['payload']);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid signature',
            ]);
    }

    public function test_update_deploy_without_signature(): void
    {
        $data = WebhookHelper::createValidWebhookData([
            'branch' => 'main',
        ]);

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

        $response = $this->call('POST', '/api/webhook/deploy', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Missing signature',
            ]);
    }

    public function test_update_deploy_with_invalid_branch(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('pullMainRepo')
            ->never();
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->never();

        $data = WebhookHelper::createValidWebhookData([
            'branch' => 'invalid-branch',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/deploy', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid branch',
                'message' => 'Only main branch is supported',
            ]);
    }

    public function test_update_deploy_with_empty_payload(): void
    {
        $response = $this->call('POST', '/api/webhook/deploy', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Empty payload',
            ]);
    }

    public function test_update_deploy_with_submodule_failure(): void
    {
        $gitServiceMock = $this->mock(GitService::class);
        $gitServiceMock->shouldReceive('pullMainRepo')
            ->once()
            ->andReturn(['Pull successful']);
        $gitServiceMock->shouldReceive('updateSubmodule')
            ->once()
            ->with('sei-compilados')
            ->andThrow(new \Exception('Submodule update failed'));

        $data = WebhookHelper::createValidWebhookData([
            'branch' => 'main',
        ]);

        $signed = WebhookHelper::createSignedPayload($data);

        $response = $this->call('POST', '/api/webhook/deploy', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signed['signature'],
        ], $signed['payload']);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Deploy updated successfully',
                'data' => [
                    'branch' => 'main',
                    'main_pull' => ['Pull successful'],
                    'submodule_update' => [],
                ],
            ]);
    }
}
