<?php

namespace App\Http\Controllers;

use App\Models\Backend;
use App\Services\BackendService;
use App\Services\GitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private GitService $gitService;

    private BackendService $backendService;

    public function __construct(GitService $gitService, BackendService $backendService)
    {
        $this->gitService = $gitService;
        $this->backendService = $backendService;
    }

    public function updateSubmodule(Request $request): JsonResponse
    {
        try {
            $data = $request->input('webhook_data', []);
            $repository = $data['repository'] ?? 'sei-compilados';
            $branch = $data['branch'] ?? 'main';
            $project = $data['project'] ?? null;

            Log::info('Webhook received: update compiled repository', [
                'repository' => $repository,
                'branch' => $branch,
                'project' => $project,
                'timestamp' => $data['timestamp'],
            ]);

            if ($repository !== 'sei-compilados') {
                Log::warning("Invalid repository specified: {$repository}");

                return response()->json([
                    'error' => 'Invalid repository',
                    'message' => 'Only sei-compilados repository is supported',
                ], 400);
            }

            if ($project !== null) {
                $projectPath = dirname(base_path()).DIRECTORY_SEPARATOR.'sei-compilados'.DIRECTORY_SEPARATOR.$project;
                if (! is_dir($projectPath)) {
                    Log::error('Project folder not found in compiled repository', [
                        'project' => $project,
                        'path' => $projectPath,
                    ]);

                    return response()->json([
                        'error' => 'Project folder not found',
                        'message' => "Folder '{$project}' does not exist in sei-compilados",
                    ], 400);
                }
            }

            $output = $this->gitService->pullCompiledRepo();

            if ($project !== null) {
                $deployOutputs = [];
                $deployOutputs[$project] = $this->gitService->deployToFrontend($project);
            } else {
                $deployOutputs = $this->gitService->deployAllFrontends();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Compiled repository updated and all frontends deployed successfully',
                'data' => [
                    'repository' => $repository,
                    'branch' => $branch,
                    'project' => $project,
                    'git_output' => $output,
                    'deployments' => $deployOutputs,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update compiled repository', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update compiled repository',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateDeploy(Request $request): JsonResponse
    {
        try {
            $data = $request->input('webhook_data', []);
            $branch = $data['branch'] ?? 'main';

            Log::info('Webhook received: update deploy', [
                'branch' => $branch,
                'timestamp' => $data['timestamp'],
            ]);

            if ($branch !== 'main') {
                Log::warning("Invalid branch specified: {$branch}");

                return response()->json([
                    'error' => 'Invalid branch',
                    'message' => 'Only main branch is supported',
                ], 400);
            }

            $output = $this->gitService->pullMainRepo();

            $composerOutput = $this->gitService->installMainDependencies();

            $filamentOutput = $this->gitService->publishFilamentAssets();

            $livewireOutput = $this->gitService->publishLivewireAssets();

            $frontendOutput = $this->gitService->buildFrontendAssets();

            $migrateOutput = $this->gitService->runMigrations();

            $compiledOutput = [];
            $deployOutputs = [];
            try {
                $compiledOutput = $this->gitService->pullCompiledRepo();
                $deployOutputs = $this->gitService->deployAllFrontends();
            } catch (\Exception $e) {
                Log::warning('Failed to update compiled repository or deploy frontends after deploy', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update deploy',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Deploy updated successfully',
                'data' => [
                    'branch' => $branch,
                    'main_pull' => $output,
                    'composer_install' => $composerOutput,
                    'filament_assets' => $filamentOutput,
                    'livewire_assets' => $livewireOutput,
                    'frontend_build' => $frontendOutput,
                    'migrate' => $migrateOutput,
                    'compiled_update' => $compiledOutput,
                    'deployments' => $deployOutputs,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update deploy', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update deploy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $currentBranch = $this->gitService->getCurrentBranch();
            $status = $this->gitService->getStatus();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'current_branch' => $currentBranch,
                    'git_status' => $status,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateBackend(string $repoName): JsonResponse
    {
        try {
            Log::info('Webhook received: update backend', [
                'repository' => $repoName,
                'timestamp' => now()->toIso8601String(),
            ]);

            $matchedBackend = Backend::all()->first(function ($b) use ($repoName) {
                return str_ends_with($b->repo, '/'.$repoName) || $b->repo === $repoName;
            });

            if ($matchedBackend === null) {
                Log::warning("Invalid backend repository: {$repoName}");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid backend repository',
                    'error' => "Backend '{$repoName}' is not in the configured list",
                ], 400);
            }

            $backendPath = $matchedBackend->resolved_path;
            $repoPath = $matchedBackend->path
                ? dirname($backendPath)
                : $backendPath;

            if (! is_dir($backendPath)) {
                Log::error('Backend directory not found', [
                    'backend' => $matchedBackend->repo,
                    'expected_path' => $backendPath,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Backend directory not found',
                    'error' => "Directory '{$backendPath}' does not exist",
                ], 400);
            }

            $result = $this->backendService->updateBackend($matchedBackend->repo, $repoPath, $backendPath);

            if ($result['status'] === 'error') {
                return response()->json($result, 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Backend updated successfully',
                'data' => [
                    'repository' => $matchedBackend,
                    'directory' => basename($backendPath),
                    'path' => $backendPath,
                    'results' => $result['results'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update backend', [
                'repository' => $repoName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update backend',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
